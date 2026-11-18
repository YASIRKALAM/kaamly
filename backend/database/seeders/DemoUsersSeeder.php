<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Role;
use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Provider\Models\ProviderBankAccount;
use App\Domains\Provider\Models\ProviderWorkingHour;
use App\Domains\Service\Models\Service;
use App\Domains\Service\Models\ProviderService;
use App\Domains\Service\Models\ProviderServicePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('KaamlyPassword2026!');

        // 1. Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@kaamly.com'],
            [
                'phone_number' => '+923001234567',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password_hash' => $defaultPassword,
                'user_type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        // 2. Finance Manager
        $finance = User::firstOrCreate(
            ['email' => 'finance@kaamly.com'],
            [
                'phone_number' => '+923001234568',
                'first_name' => 'Farhan',
                'last_name' => 'Finance',
                'password_hash' => $defaultPassword,
                'user_type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $financeRole = Role::where('name', 'finance_manager')->first();
        if ($financeRole) {
            $finance->roles()->syncWithoutDetaching([$financeRole->id]);
        }

        // 3. Demo Verified Provider (Tariq Mehmood - HVAC Specialist in Lahore)
        $providerUser = User::firstOrCreate(
            ['email' => 'provider.tariq@example.com'],
            [
                'phone_number' => '+923011234567',
                'first_name' => 'Tariq',
                'last_name' => 'Mehmood',
                'password_hash' => $defaultPassword,
                'user_type' => 'provider',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $providerRole = Role::where('name', 'provider')->first();
        if ($providerRole) {
            $providerUser->roles()->syncWithoutDetaching([$providerRole->id]);
        }

        $providerProfile = ProviderProfile::firstOrCreate(
            ['user_id' => $providerUser->id],
            [
                'business_name' => 'Tariq HVAC & Inverter Solutions',
                'cnic_number' => '35202-1234567-1',
                'status' => 'approved',
                'onboarding_step' => 4,
                'rating_average' => 4.85,
                'total_reviews' => 38,
                'completed_jobs_count' => 52,
                'acceptance_rate' => 96.50,
                'response_time_seconds' => 180,
                'is_online' => true,
                'is_available' => true,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]
        );
        // Lahore coordinates: 31.5204 N, 74.3587 E
        DB::statement("UPDATE provider_profiles SET current_location = ST_SetSRID(ST_MakePoint(74.3587, 31.5204), 4326), location_updated_at = NOW() WHERE id = '{$providerProfile->id}'");

        // Working Hours (Mon - Sat, 09:00 - 18:00)
        for ($day = 1; $day <= 6; $day++) {
            ProviderWorkingHour::firstOrCreate(
                ['provider_profile_id' => $providerProfile->id, 'day_of_week' => $day],
                ['start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_active' => true]
            );
        }

        // Bank Account
        ProviderBankAccount::firstOrCreate(
            ['provider_profile_id' => $providerProfile->id, 'bank_name' => 'Habib Bank Limited (HBL)'],
            [
                'account_title' => 'Tariq Mehmood',
                'account_number_encrypted' => encrypt('00427901234567'),
                'iban_encrypted' => encrypt('PK36HABB0000427901234567'),
                'is_verified' => true,
                'is_primary' => true,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]
        );

        // Associate Services & Approved Custom Price
        $acService = Service::where('slug', 'ac-master-service-chemical-wash')->first();
        if ($acService) {
            $ps = ProviderService::firstOrCreate(
                ['provider_profile_id' => $providerProfile->id, 'service_id' => $acService->id],
                ['is_active' => true]
            );

            ProviderServicePrice::firstOrCreate(
                ['provider_service_id' => $ps->id, 'custom_price' => 2400.00],
                [
                    'approval_status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'is_current' => true,
                ]
            );
        }

        // 4. Demo Active Customer (Ali Raza - Lahore)
        $customerUser = User::firstOrCreate(
            ['email' => 'customer.ali@example.com'],
            [
                'phone_number' => '+923021234567',
                'first_name' => 'Ali',
                'last_name' => 'Raza',
                'password_hash' => $defaultPassword,
                'user_type' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $customerUser->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        $customerProfile = CustomerProfile::firstOrCreate(
            ['user_id' => $customerUser->id],
            [
                'preferred_language' => 'en',
            ]
        );

        // Customer Address in Gulberg III, Lahore (31.5110 N, 74.3440 E)
        $customerAddress = CustomerAddress::firstOrCreate(
            ['customer_profile_id' => $customerProfile->id, 'label' => 'Home'],
            [
                'address_line_1' => 'House 45, Block B-3, Gulberg III',
                'area' => 'Gulberg III',
                'city' => 'Lahore',
                'postal_code' => '54000',
                'is_default' => true,
            ]
        );
        DB::statement("UPDATE customer_addresses SET location = ST_SetSRID(ST_MakePoint(74.3440, 31.5110), 4326) WHERE id = '{$customerAddress->id}'");

        $customerProfile->update(['default_address_id' => $customerAddress->id]);
    }
}
