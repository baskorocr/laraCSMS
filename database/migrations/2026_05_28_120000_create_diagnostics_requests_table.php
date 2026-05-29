<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostics_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_point_id')->constrained()->cascadeOnDelete();
            $table->string('charge_point_code', 100);
            $table->string('message_id', 120)->nullable();
            $table->text('location');
            $table->unsignedSmallInteger('retries')->default(3);
            $table->unsignedSmallInteger('retry_interval')->default(60);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->string('status', 50)->default('Requested');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('ocpp_command_request_id')->nullable();
            $table->timestamps();

            $table->index(['charge_point_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostics_requests');
    }
};
