<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocpp_messages_log', function (Blueprint $table) {
            $table->unique(
                ['charge_point_id', 'direction', 'message_uid'],
                'ocpp_messages_charge_point_direction_uid_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ocpp_messages_log', function (Blueprint $table) {
            $table->dropUnique('ocpp_messages_charge_point_direction_uid_unique');
        });
    }
};

