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
        Schema::create('ocpp_messages_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('charge_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ocpp_version', 10);
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->unsignedTinyInteger('message_type_id');
            $table->string('action')->nullable();
            $table->string('message_uid')->nullable();
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['company_id', 'received_at']);
            $table->index(['charge_point_id', 'received_at']);
            $table->index('message_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocpp_messages_log');
    }
};
