<?php

use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
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
        Schema::create('data_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Client Migration 2025-09-09"
            $table->enum('resource_type', ResourceType::getValues());
            $table->json('filters'); // Date ranges, specific criteria
            $table->enum('status', DataMigrationStatus::getValues())->default('pending');
            $table->integer('total_items')->default(0);
            $table->integer('batch_size')->default(100);
            $table->json('summary')->nullable(); // Store detailed summary
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_migrations');
    }
};
