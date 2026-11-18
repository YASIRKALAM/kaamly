<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'users.view', 'module' => 'users', 'description' => 'View all users'],
            ['name' => 'users.manage', 'module' => 'users', 'description' => 'Create, edit, suspend users'],
            
            // Provider Management
            ['name' => 'providers.verify', 'module' => 'providers', 'description' => 'Approve or reject provider onboarding documents'],
            ['name' => 'providers.view_sensitive', 'module' => 'providers', 'description' => 'View unmasked CNIC and bank details'],
            ['name' => 'providers.manage_status', 'module' => 'providers', 'description' => 'Suspend or reactivate service providers'],
            
            // Service Catalog
            ['name' => 'catalog.manage', 'module' => 'catalog', 'description' => 'Manage service categories, services, and base rates'],
            ['name' => 'catalog.approve_prices', 'module' => 'catalog', 'description' => 'Approve provider custom rate submissions'],

            // Bookings & Dispatch
            ['name' => 'bookings.view_all', 'module' => 'bookings', 'description' => 'View all marketplace bookings'],
            ['name' => 'bookings.manage', 'module' => 'bookings', 'description' => 'Force cancel, reassign, or adjust bookings'],

            // Financial Ledger & Settlements
            ['name' => 'ledger.view', 'module' => 'finance', 'description' => 'View double-entry ledger transactions and balances'],
            ['name' => 'settlements.process', 'module' => 'finance', 'description' => 'Review and execute provider payout batches'],
            ['name' => 'finance.adjust_balance', 'module' => 'finance', 'description' => 'Post manual financial correction transactions (Maker-Checker required > 10k)'],

            // Disputes & Support
            ['name' => 'disputes.arbitrate', 'module' => 'disputes', 'description' => 'Arbitrate and resolve customer-provider disputes'],
            ['name' => 'complaints.manage', 'module' => 'support', 'description' => 'Manage customer support complaints and tickets'],

            // System & Audit
            ['name' => 'system.configure', 'module' => 'system', 'description' => 'Update platform system settings'],
            ['name' => 'audit.view', 'module' => 'system', 'description' => 'View immutable platform audit trail'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $p) {
            $createdPermissions[$p['name']] = Permission::firstOrCreate(
                ['name' => $p['name']],
                ['module' => $p['module'], 'description' => $p['description']]
            );
        }

        // Create Roles
        $roles = [
            'super_admin' => [
                'display_name' => 'Super Administrator',
                'description' => 'Unrestricted access to all marketplace domains and configurations',
                'is_system_role' => true,
                'permissions' => array_keys($createdPermissions),
            ],
            'operations_manager' => [
                'display_name' => 'Operations Manager',
                'description' => 'Manages provider onboarding, service catalog, dispatch, and monitoring',
                'is_system_role' => true,
                'permissions' => [
                    'users.view', 'providers.verify', 'providers.manage_status',
                    'catalog.manage', 'catalog.approve_prices', 'bookings.view_all',
                    'bookings.manage', 'disputes.arbitrate', 'complaints.manage', 'audit.view'
                ],
            ],
            'finance_manager' => [
                'display_name' => 'Finance Manager',
                'description' => 'Manages ledger, escrow holdings, settlements, refunds, and adjustments',
                'is_system_role' => true,
                'permissions' => [
                    'ledger.view', 'settlements.process', 'finance.adjust_balance', 'audit.view'
                ],
            ],
            'customer_support' => [
                'display_name' => 'Customer Support Agent',
                'description' => 'Handles tickets, complaints, and basic dispute intake',
                'is_system_role' => true,
                'permissions' => [
                    'users.view', 'bookings.view_all', 'complaints.manage'
                ],
            ],
            'provider' => [
                'display_name' => 'Service Provider',
                'description' => 'Technician / artisan performing on-demand service jobs',
                'is_system_role' => true,
                'permissions' => [],
            ],
            'customer' => [
                'display_name' => 'Customer',
                'description' => 'Consumer hiring service professionals',
                'is_system_role' => true,
                'permissions' => [],
            ],
        ];

        foreach ($roles as $roleKey => $data) {
            $role = Role::firstOrCreate(
                ['name' => $roleKey],
                [
                    'display_name' => $data['display_name'],
                    'description' => $data['description'],
                    'is_system_role' => $data['is_system_role'],
                ]
            );

            $permissionIds = [];
            foreach ($data['permissions'] as $pName) {
                if (isset($createdPermissions[$pName])) {
                    $permissionIds[] = $createdPermissions[$pName]->id;
                }
            }

            if (!empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
