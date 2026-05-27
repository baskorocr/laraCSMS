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
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_point_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('evse_id')->default(1);
            $table->unsignedSmallInteger('connector_id');
            $table->string('connector_type')->default('CCS2');
            $table->string('status')->default('Available');
            $table->decimal('max_power_kw', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'charge_point_id', 'evse_id', 'connector_id'], 'connectors_scope_unique');
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
