<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocpp_command_requests', function (Blueprint $table) {
            $table->unique(['charge_point_id', 'message_uid'], 'ocpp_command_charge_point_message_uid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ocpp_command_requests', function (Blueprint $table) {
            $table->dropUnique('ocpp_command_charge_point_message_uid_unique');
        });
    }
};

