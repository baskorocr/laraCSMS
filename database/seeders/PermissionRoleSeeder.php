<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teamForeignKey = config('permission.column_names.team_foreign_key', 'company_id');
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        $permissions = [
            'view_charging',
            'control_charging',
            'stop_transaction',
            'view_reports',
            'manage_users',
            'manage_stations',
            'manage_roles',
            'manage_permissions',
            'view_transactions',
            'view_meter_values',
            'view_ocpp_logs',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            $teamForeignKey => 0,
        ]);
        $adminRole->syncPermissions($permissions);

        $admin = User::updateOrCreate(
            ['email' => 'admin@csms.local'],
            [
                'name' => 'Global Admin',
                'company_id' => null,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $permissionRegistrar->setPermissionsTeamId(0);
        $admin->syncRoles([$adminRole]);

        $companies = Company::all();

        foreach ($companies as $company) {
            $companyAdminRole = Role::firstOrCreate([
                'name' => 'company_admin',
                'guard_name' => 'web',
                $teamForeignKey => $company->id,
            ]);

            $companyUserRole = Role::firstOrCreate([
                'name' => 'company_user',
                'guard_name' => 'web',
                $teamForeignKey => $company->id,
            ]);

            $companyAdminRole->syncPermissions([
                'view_charging',
                'control_charging',
                'stop_transaction',
                'view_reports',
                'manage_users',
                'manage_stations',
                'manage_roles',
                'view_transactions',
                'view_meter_values',
                'view_ocpp_logs',
            ]);

            $companyUserRole->syncPermissions([
                'view_charging',
                'view_reports',
                'view_transactions',
                'view_meter_values',
            ]);

            $companyAdmin = User::updateOrCreate(
                ['email' => "admin.{$company->code}@csms.local"],
                [
                    'name' => "{$company->name} Admin",
                    'company_id' => $company->id,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $companyUser = User::updateOrCreate(
                ['email' => "user.{$company->code}@csms.local"],
                [
                    'name' => "{$company->name} User",
                    'company_id' => $company->id,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $permissionRegistrar->setPermissionsTeamId($company->id);
            $companyAdmin->syncRoles([$companyAdminRole]);
            $companyUser->syncRoles([$companyUserRole]);
        }

        $permissionRegistrar->setPermissionsTeamId(0);
        $permissionRegistrar->forgetCachedPermissions();
    }
}
