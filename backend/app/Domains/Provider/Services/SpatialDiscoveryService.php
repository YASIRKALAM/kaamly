<?php

namespace App\Domains\Provider\Services;

use App\Domains\Booking\Models\BookingRequest;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Service\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use DateTime;

class SpatialDiscoveryService
{
    /**
     * Execute the 6-gate PostGIS spatial discovery query.
     *
     * @param float $latitude Customer latitude
     * @param float $longitude Customer longitude
     * @param string $serviceId Target service UUID
     * @param DateTime $scheduledStart Appointment start
     * @param DateTime|null $scheduledEnd Appointment end (defaults to start + service duration + 30 min buffer)
     * @param float $radiusKm Maximum search radius in kilometers (default 15 km)
     * @param int $limit Maximum results to return
     * @return Collection
     */
    public function discoverProviders(
        float $latitude,
        float $longitude,
        string $serviceId,
        DateTime $scheduledStart,
        ?DateTime $scheduledEnd = null,
        float $radiusKm = 15.0,
        int $limit = 20
    ): Collection {
        $service = Service::findOrFail($serviceId);
        $durationMinutes = $service->estimated_duration_minutes ?? 60;

        if ($scheduledEnd === null) {
            $scheduledEnd = (clone $scheduledStart)->modify("+" . ($durationMinutes + 30) . " minutes");
        }

        $radiusMeters = $radiusKm * 1000.0;
        $dayOfWeek = (int) $scheduledStart->format('w');
        $targetTime = $scheduledStart->format('H:i:s');
        $targetDate = $scheduledStart->format('Y-m-d');
        $startIso = $scheduledStart->format('Y-m-d H:i:sO');
        $endIso = $scheduledEnd->format('Y-m-d H:i:sO');

        $rawQuery = "
            SELECT 
                pp.id AS provider_id,
                pp.user_id,
                pp.business_name,
                u.first_name,
                u.last_name,
                pp.rating_average,
                pp.total_reviews,
                pp.completed_jobs_count,
                pp.acceptance_rate,
                psp.custom_price AS effective_price,
                ROUND((ST_Distance(pp.current_location::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography) / 1000.0)::numeric, 2) AS distance_km
            FROM provider_profiles pp
            JOIN users u ON pp.user_id = u.id
            JOIN provider_services ps ON pp.id = ps.provider_profile_id AND ps.service_id = :service_id AND ps.is_active = true
            JOIN provider_service_prices psp ON ps.id = psp.provider_service_id AND psp.is_current = true AND psp.approval_status = 'approved'
            WHERE pp.status = 'approved'
              AND pp.is_online = true
              AND pp.is_available = true
              AND u.status = 'active'
              AND pp.current_location IS NOT NULL
              -- Gate 1: Spatial Proximity via PostGIS ST_DWithin
              AND ST_DWithin(pp.current_location::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, :radius_meters)
              -- Gate 2: Location Freshness <= 30 minutes
              AND pp.location_updated_at >= NOW() - INTERVAL '30 minutes'
              -- Gate 3: Working Hours Match
              AND EXISTS (
                  SELECT 1 FROM provider_working_hours pwh
                  WHERE pwh.provider_profile_id = pp.id
                    AND pwh.day_of_week = :day_of_week
                    AND pwh.is_active = true
                    AND :target_time >= pwh.start_time
                    AND :target_time <= pwh.end_time
              )
              -- Gate 4: No Scheduled Day Off
              AND NOT EXISTS (
                  SELECT 1 FROM provider_day_off_schedules pdos
                  WHERE pdos.provider_profile_id = pp.id
                    AND pdos.off_date = :target_date
              )
              -- Gate 5: No Conflicting Booking Overlap (PostgreSQL Range Overlap Operator &&)
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b
                  WHERE b.provider_profile_id = pp.id
                    AND b.status IN ('accepted', 'confirmed', 'provider_on_the_way', 'in_progress', 'rescheduled')
                    AND b.scheduled_range && tstzrange(:start_iso, :end_iso, '[)')
              )
              -- Gate 6: COD Commission Debt Check (Strictly < Configurable COD Debt Threshold)
              AND COALESCE((
                  SELECT SUM(CASE WHEN le.direction = 'debit' THEN le.amount ELSE -le.amount END)
                  FROM ledger_entries le
                  JOIN ledger_transactions lt ON le.ledger_transaction_id = lt.id
                  JOIN ledger_accounts la ON le.ledger_account_id = la.id
                  WHERE la.account_code = '1030'
                    AND lt.status = 'posted'
                    AND lt.reference_id IN (SELECT b_sub.id FROM bookings b_sub WHERE b_sub.provider_profile_id = pp.id)
              ), 0) < :cod_debt_threshold
            ORDER BY 
                distance_km ASC,
                pp.rating_average DESC,
                pp.completed_jobs_count DESC
            LIMIT :limit;
        ";

        $results = DB::select($rawQuery, [
            'lon' => $longitude,
            'lat' => $latitude,
            'service_id' => $serviceId,
            'radius_meters' => $radiusMeters,
            'day_of_week' => $dayOfWeek,
            'target_time' => $targetTime,
            'target_date' => $targetDate,
            'start_iso' => $startIso,
            'end_iso' => $endIso,
            'cod_debt_threshold' => (float) config('marketplace.cod_debt_threshold', 2000.00),
            'limit' => $limit,
        ]);

        return collect($results);
    }

    /**
     * Dispatch booking request to nearest eligible providers
     */
    public function dispatchBookingRequest(BookingRequest $bookingRequest, float $radiusKm = 15.0, int $topK = 5): Collection
    {
        $address = CustomerAddress::findOrFail($bookingRequest->address_id);
        
        // Extract lat/long from customer address geometry
        $coords = DB::table('customer_addresses')
            ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
            ->where('id', $address->id)
            ->first();

        if (!$coords || $coords->lat === null) {
            return collect();
        }

        $candidates = $this->discoverProviders(
            latitude: (float) $coords->lat,
            longitude: (float) $coords->lon,
            serviceId: $bookingRequest->service_id,
            scheduledStart: new DateTime($bookingRequest->requested_schedule_start->toIso8601String()),
            scheduledEnd: new DateTime($bookingRequest->requested_schedule_end->toIso8601String()),
            radiusKm: $radiusKm,
            limit: $topK
        );

        $bookingRequest->update(['status' => 'broadcasted']);

        return $candidates;
    }
}
