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
        // Add foreign key relationships from migrated records to data migrations
        // This gives us direct links: migration -> batches -> records

        // Add migration_id to migrated tables for direct relationship
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable()->after('id')->constrained('data_migrations')->onDelete('cascade');
            $table->index(['migration_id', 'verification_status']);
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable()->after('id')->constrained('data_migrations')->onDelete('cascade');
            $table->index(['migration_id', 'verification_status']);
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable()->after('id')->constrained('data_migrations')->onDelete('cascade');
            $table->index(['migration_id', 'verification_status']);
        });

        // Populate the migration_id fields from existing batch relationships
        DB::statement("
            UPDATE migrated_clients mc
            JOIN data_migration_batches dmb ON mc.migration_batch_id = dmb.batch_id
            SET mc.migration_id = dmb.data_migration_id
        ");

        DB::statement("
            UPDATE migrated_cases mc
            JOIN data_migration_batches dmb ON mc.migration_batch_id = dmb.batch_id
            SET mc.migration_id = dmb.data_migration_id
        ");

        DB::statement("
            UPDATE migrated_sessions ms
            JOIN data_migration_batches dmb ON ms.migration_batch_id = dmb.batch_id
            SET ms.migration_id = dmb.data_migration_id
        ");

        // Make migration_id not nullable now that it's populated
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable(false)->change();
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable(false)->change();
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->foreignId('migration_id')->nullable(false)->change();
        });

        // Drop the verification_sessions table - no longer needed
        Schema::dropIfExists('verification_sessions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate verification_sessions table
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

            $table->index(['migration_id', 'status']);
            $table->index(['migration_id', 'created_at']);
        });

        // Remove migration_id columns
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->dropForeign(['migration_id']);
            $table->dropIndex(['migration_id', 'verification_status']);
            $table->dropColumn('migration_id');
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->dropForeign(['migration_id']);
            $table->dropIndex(['migration_id', 'verification_status']);
            $table->dropColumn('migration_id');
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->dropForeign(['migration_id']);
            $table->dropIndex(['migration_id', 'verification_status']);
            $table->dropColumn('migration_id');
        });
    }
};