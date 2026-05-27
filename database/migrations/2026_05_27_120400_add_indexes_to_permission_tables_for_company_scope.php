<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $teamForeignKey = config('permission.column_names.team_foreign_key', 'company_id');

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamForeignKey) {
            $table->index([$teamForeignKey, 'name'], 'roles_company_name_idx');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamForeignKey) {
            $table->index([$teamForeignKey, 'model_id', 'model_type'], 'model_has_roles_company_model_idx');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropIndex('roles_company_name_idx');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->dropIndex('model_has_roles_company_model_idx');
        });
    }
};
