<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ocpp_command_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_point_id')->constrained()->cascadeOnDelete();
            $table->string('ocpp_version', 10);
            $table->string('action');
            $table->string('message_uid')->nullable();
            $table->json('request_payload');
            $table->enum('status', ['pending', 'sent', 'acknowledged', 'error', 'timeout'])->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('response_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_description')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['charge_point_id', 'status']);
            $table->index('message_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocpp_command_requests');
    }
};
