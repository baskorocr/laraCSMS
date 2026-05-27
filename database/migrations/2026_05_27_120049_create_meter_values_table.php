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
        Schema::create('meter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreignId('charge_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sampled_at');
            $table->string('measurand')->default('Energy.Active.Import.Register');
            $table->string('unit')->default('Wh');
            $table->decimal('value', 14, 3);
            $table->string('context')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sampled_at']);
            $table->index(['transaction_id', 'sampled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_values');
    }
};
