<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $companies = [
            [
                'name' => 'Acme Charge',
                'code' => 'acme',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
            [
                'name' => 'Volt Nusantara',
                'code' => 'volt',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $companyData) {
            $company = Company::updateOrCreate(
                ['code' => $companyData['code']],
                $companyData
            );

            DB::table('charge_points')->updateOrInsert(
                ['charge_point_id' => "CP-{$company->code}-001"],
                [
                    'company_id' => $company->id,
                    'name' => "{$company->name} Main CP",
                    'ocpp_version' => '1.6',
                    'status' => 'Available',
                    'is_online' => false,
                    'last_heartbeat_at' => null,
                    'metadata' => json_encode(['location' => 'HQ']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('master_ocpp_versions')->upsert([
            ['code' => '1.6', 'name' => 'OCPP 1.6 JSON', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '2.1', 'name' => 'OCPP 2.1 JSON', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'is_active', 'updated_at']);

        DB::table('master_connector_types')->upsert([
            [
                'code' => 'CCS2',
                'name' => 'Combined Charging System 2',
                'max_current_ampere' => 200,
                'max_voltage' => 1000,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CHADEMO',
                'name' => 'CHAdeMO',
                'max_current_ampere' => 125,
                'max_voltage' => 500,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TYPE2',
                'name' => 'Type 2 AC',
                'max_current_ampere' => 63,
                'max_voltage' => 400,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['name', 'max_current_ampere', 'max_voltage', 'is_active', 'updated_at']);

        DB::table('master_connector_statuses')->upsert([
            ['code' => 'Available', 'name' => 'Available', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Preparing', 'name' => 'Preparing', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Charging', 'name' => 'Charging', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SuspendedEV', 'name' => 'Suspended EV', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SuspendedEVSE', 'name' => 'Suspended EVSE', 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Finishing', 'name' => 'Finishing', 'sort_order' => 60, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Reserved', 'name' => 'Reserved', 'sort_order' => 70, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Occupied', 'name' => 'Occupied', 'sort_order' => 80, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Unavailable', 'name' => 'Unavailable', 'sort_order' => 90, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Faulted', 'name' => 'Faulted', 'sort_order' => 100, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'sort_order', 'is_active', 'updated_at']);

        DB::table('master_transaction_statuses')->upsert([
            ['code' => 'ongoing', 'name' => 'Ongoing', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'completed', 'name' => 'Completed', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'stopped', 'name' => 'Stopped', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'failed', 'name' => 'Failed', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'sort_order', 'is_active', 'updated_at']);

        DB::table('master_transaction_stop_reasons')->upsert([
            ['code' => 'Local', 'name' => 'Stopped locally', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Remote', 'name' => 'Stopped by remote command', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EVDisconnected', 'name' => 'Vehicle disconnected', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EmergencyStop', 'name' => 'Emergency stop', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DeAuthorized', 'name' => 'Authorization revoked', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PowerLoss', 'name' => 'Power loss', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Timeout', 'name' => 'Timeout', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'is_active', 'updated_at']);

        DB::table('master_ocpp_actions')->upsert([
            ['code' => 'BootNotification', 'name' => 'Boot Notification', 'profile' => 'Core', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Heartbeat', 'name' => 'Heartbeat', 'profile' => 'Core', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'StatusNotification', 'name' => 'Status Notification', 'profile' => 'Core', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Authorize', 'name' => 'Authorize', 'profile' => 'Authorization', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'StartTransaction', 'name' => 'Start Transaction', 'profile' => 'Transaction', 'supported_versions' => json_encode(['1.6']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'StopTransaction', 'name' => 'Stop Transaction', 'profile' => 'Transaction', 'supported_versions' => json_encode(['1.6']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MeterValues', 'name' => 'Meter Values', 'profile' => 'Smart Charging', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TransactionEvent', 'name' => 'Transaction Event', 'profile' => 'Transaction', 'supported_versions' => json_encode(['2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ReserveNow', 'name' => 'Reserve Now', 'profile' => 'Reservation', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GetDiagnostics', 'name' => 'Get Diagnostics', 'profile' => 'Diagnostics', 'supported_versions' => json_encode(['1.6', '2.1']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'profile', 'supported_versions', 'is_active', 'updated_at']);

        DB::table('master_meter_measurands')->upsert([
            ['code' => 'Energy.Active.Import.Register', 'name' => 'Energy Active Import Register', 'default_unit' => 'Wh', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Power.Active.Import', 'name' => 'Power Active Import', 'default_unit' => 'W', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Voltage', 'name' => 'Voltage', 'default_unit' => 'V', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Current.Import', 'name' => 'Current Import', 'default_unit' => 'A', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SoC', 'name' => 'State of Charge', 'default_unit' => 'Percent', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'default_unit', 'is_active', 'updated_at']);

        DB::table('master_reservation_statuses')->upsert([
            ['code' => 'Active', 'name' => 'Active', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Used', 'name' => 'Used', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Cancelled', 'name' => 'Cancelled', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Expired', 'name' => 'Expired', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'sort_order', 'is_active', 'updated_at']);

        DB::table('master_diagnostics_statuses')->upsert([
            ['code' => 'Requested', 'name' => 'Requested', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Uploading', 'name' => 'Uploading', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Uploaded', 'name' => 'Uploaded', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'UploadFailed', 'name' => 'Upload Failed', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Failed', 'name' => 'Failed', 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'sort_order', 'is_active', 'updated_at']);
    }
}
