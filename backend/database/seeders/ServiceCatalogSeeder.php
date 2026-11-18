<?php

namespace Database\Seeders;

use App\Domains\Service\Models\ServiceCategory;
use App\Domains\Service\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Air Conditioning & HVAC',
                'slug' => 'air-conditioning-hvac',
                'description' => 'Comprehensive split and standing AC installation, cleaning, refilling, and inverter maintenance',
                'icon_url' => 'https://assets.kaamly.com/icons/ac.svg',
                'display_order' => 1,
                'services' => [
                    [
                        'name' => 'AC Master Service & Chemical Wash',
                        'slug' => 'ac-master-service-chemical-wash',
                        'description' => 'Complete indoor and outdoor unit pressure jet chemical wash with filter sanitation.',
                        'pricing_model' => 'fixed',
                        'base_price' => 2500.00,
                        'minimum_price' => 2000.00,
                        'maximum_price' => 3500.00,
                        'estimated_duration_minutes' => 60,
                    ],
                    [
                        'name' => 'AC Gas Refill (R410A / R32 / R22)',
                        'slug' => 'ac-gas-refill',
                        'description' => 'Precision refrigerant top-up or complete vacuum refill with high-pressure nitrogen leak test.',
                        'pricing_model' => 'fixed',
                        'base_price' => 6500.00,
                        'minimum_price' => 5000.00,
                        'maximum_price' => 9000.00,
                        'estimated_duration_minutes' => 90,
                    ],
                    [
                        'name' => 'Inverter AC Diagnostic & Circuit Inspection',
                        'slug' => 'inverter-ac-diagnostic-inspection',
                        'description' => 'Diagnostic visit by certified electronics technician to inspect inverter PCB, compressor motor, and sensors.',
                        'pricing_model' => 'inspection_quote',
                        'base_price' => 1000.00,
                        'minimum_price' => 1000.00,
                        'maximum_price' => 50000.00,
                        'estimated_duration_minutes' => 45,
                    ],
                ],
            ],
            [
                'name' => 'Electrical Services',
                'slug' => 'electrical-services',
                'description' => 'Wiring, circuit breakers, light fixtures, UPS, inverter setup and load management',
                'icon_url' => 'https://assets.kaamly.com/icons/electrical.svg',
                'display_order' => 2,
                'services' => [
                    [
                        'name' => 'Ceiling Fan / Chandelier Installation',
                        'slug' => 'ceiling-fan-chandelier-installation',
                        'description' => 'Safe mounting, bracket reinforcement, and circuit balancing for fans and lighting.',
                        'pricing_model' => 'fixed',
                        'base_price' => 800.00,
                        'minimum_price' => 600.00,
                        'maximum_price' => 2500.00,
                        'estimated_duration_minutes' => 45,
                    ],
                    [
                        'name' => 'Distribution Board & Breaker Repair',
                        'slug' => 'distribution-board-breaker-repair',
                        'description' => 'Replacement of tripped or burnt MCB/ELCB circuit breakers and phase check.',
                        'pricing_model' => 'fixed',
                        'base_price' => 1500.00,
                        'minimum_price' => 1200.00,
                        'maximum_price' => 4000.00,
                        'estimated_duration_minutes' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Plumbing & Water Systems',
                'slug' => 'plumbing-water-systems',
                'description' => 'Water geyser setup, drainage unclogging, motor pump installation, and sanitary fittings',
                'icon_url' => 'https://assets.kaamly.com/icons/plumbing.svg',
                'display_order' => 3,
                'services' => [
                    [
                        'name' => 'Water Geyser Installation & Servicing',
                        'slug' => 'water-geyser-installation-servicing',
                        'description' => 'Descaling, thermostat calibration, safety valve fitting, and connection leak test.',
                        'pricing_model' => 'fixed',
                        'base_price' => 2000.00,
                        'minimum_price' => 1500.00,
                        'maximum_price' => 4500.00,
                        'estimated_duration_minutes' => 75,
                    ],
                    [
                        'name' => 'Underground / Concealed Pipe Leakage Inspection',
                        'slug' => 'pipe-leakage-inspection',
                        'description' => 'Acoustic / moisture detection to isolate wall seepage followed by quotation for pipe replacement.',
                        'pricing_model' => 'inspection_quote',
                        'base_price' => 1500.00,
                        'minimum_price' => 1500.00,
                        'maximum_price' => 80000.00,
                        'estimated_duration_minutes' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Deep Cleaning & Sanitization',
                'slug' => 'deep-cleaning-sanitization',
                'description' => 'Residential apartment and villa full deep scrubbing, floor polishing, and kitchen degreasing',
                'icon_url' => 'https://assets.kaamly.com/icons/cleaning.svg',
                'display_order' => 4,
                'services' => [
                    [
                        'name' => 'Full Apartment Deep Cleaning (2-3 Bed)',
                        'slug' => 'full-apartment-deep-cleaning',
                        'description' => 'Two-person team with industrial equipment: tile scrubbing, kitchen grease removal, bathroom descaling.',
                        'pricing_model' => 'fixed',
                        'base_price' => 9500.00,
                        'minimum_price' => 8000.00,
                        'maximum_price' => 16000.00,
                        'estimated_duration_minutes' => 240,
                    ],
                ],
            ],
        ];

        foreach ($categories as $catData) {
            $services = $catData['services'];
            unset($catData['services']);

            $category = ServiceCategory::firstOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );

            foreach ($services as $srvData) {
                $srvData['category_id'] = $category->id;
                Service::firstOrCreate(
                    ['slug' => $srvData['slug']],
                    $srvData
                );
            }
        }
    }
}
