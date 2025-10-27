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
        Schema::create('enrichment_processes', function (Blueprint $table) {
            $table->id();
            $table->enum('resource_type', ['CASE', 'SESSION']); // Mirrors ResourceType enum
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->unsignedInteger('total_items')->default(0); // Total cases/sessions to enrich
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable(); // When pause was requested
            $table->timestamps();

            $table->index('resource_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrichment_processes');
    }
};
