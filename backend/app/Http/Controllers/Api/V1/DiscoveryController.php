<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DiscoveryController extends Controller
{
    /**
     * Search available nearby providers using the 6-gate PostGIS spatial discovery engine.
     */
    public function searchProviders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'service_id' => 'required|uuid|exists:services,id',
            'scheduled_start_at' => 'required|date|after:now',
            'scheduled_end_at' => 'nullable|date|after:scheduled_start_at',
            'radius_km' => 'nullable|numeric|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid search parameters.', $validator->errors(), 422);
        }

        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');
        $serviceId = $request->input('service_id');
        $radiusKm = (float) ($request->input('radius_km', 15.0));
        $radiusMeters = $radiusKm * 1000.0;

        $targetStart = new \DateTime($request->input('scheduled_start_at'));
        $targetEnd = $request->has('scheduled_end_at') 
            ? new \DateTime($request->input('scheduled_end_at'))
            : (clone $targetStart)->modify('+90 minutes'); // 60 min service + 30 min buffer

        $dayOfWeek = (int) $targetStart->format('w'); // 0 = Sunday ... 6 = Saturday
        $targetTime = $targetStart->format('H:i:s');
        $targetDate = $targetStart->format('Y-m-d');
        $targetStartIso = $targetStart->format('Y-m-d H:i:sO');
        $targetEndIso = $targetEnd->format('Y-m-d H:i:sO');

        $query = "
            SELECT 
                pp.id AS provider_id,
                pp.business_name,
                u.first_name,
                u.last_name,
                pp.rating_average,
                pp.total_reviews,
                pp.completed_jobs_count,
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
              -- Gate 5: No Conflicting Booking Overlap
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b
                  WHERE b.provider_profile_id = pp.id
                    AND b.status IN ('accepted', 'confirmed', 'provider_on_the_way', 'in_progress')
                    AND b.scheduled_range && tstzrange(:target_start, :target_end, '[)')
              )
            ORDER BY 
                distance_km ASC,
                pp.rating_average DESC,
                pp.completed_jobs_count DESC
            LIMIT 20;
        ";

        $results = DB::select($query, [
            'lon' => $lon,
            'lat' => $lat,
            'service_id' => $serviceId,
            'radius_meters' => $radiusMeters,
            'day_of_week' => $dayOfWeek,
            'target_time' => $targetTime,
            'target_date' => $targetDate,
            'target_start' => $targetStartIso,
            'target_end' => $targetEndIso,
        ]);

        return $this->successResponse($results, 'Matching service providers found.', [
            'count' => count($results),
            'radius_km' => $radiusKm,
        ]);
    }
}
