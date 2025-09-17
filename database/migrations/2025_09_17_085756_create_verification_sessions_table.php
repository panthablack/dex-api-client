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
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_id')->constrained('data_migrations')->onDelete('cascade');
            $table->enum('type', ['full', 'continue'])->default('full');
            $table->enum('status', ['starting', 'in_progress', 'completed', 'failed', 'stopping', 'stopped'])->default('starting');
            $table->text('current_activity')->nullable();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('verified_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->json('resource_progress')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['migration_id', 'status']);
            $table->index(['migration_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_sessions');
    }
};
