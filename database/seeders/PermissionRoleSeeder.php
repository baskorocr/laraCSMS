<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        $sidebar = config('permissions.sidebar', []);
        $support = config('permissions.support', []);
        $adminPermissions = array_values(array_unique(array_merge($sidebar, $support)));
        $companyAdminPermissions = config('permissions.company_admin', []);
        $companyUserPermissions = config('permissions.company_user', []);

        foreach ($adminPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $this->removeOrphanPermissions($adminPermissions);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            $teamForeignKey => 0,
        ]);
        $adminRole->syncPermissions($adminPermissions);

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

            $companyAdminRole->syncPermissions($companyAdminPermissions);
            $companyUserRole->syncPermissions($companyUserPermissions);

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

    /**
     * @param array<int, string> $allowed
     */
    private function removeOrphanPermissions(array $allowed): void
    {
        $allowedLookup = array_fill_keys($allowed, true);

        Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->each(function (string $name) use ($allowedLookup): void {
                if (! isset($allowedLookup[$name])) {
                    Permission::where('name', $name)->where('guard_name', 'web')->delete();
                }
            });

        DB::table('role_has_permissions')
            ->whereNotIn('permission_id', Permission::query()->pluck('id'))
            ->delete();
    }
}
