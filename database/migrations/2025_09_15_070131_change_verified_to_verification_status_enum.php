<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update migrated_clients table
        Schema::table('migrated_clients', function (Blueprint $table) {
            // Add new verification_status column
            $table->enum('verification_status', ['pending', 'verified', 'failed'])
                  ->default('pending')
                  ->after('created_at');
        });

        // Update migrated_cases table
        Schema::table('migrated_cases', function (Blueprint $table) {
            // Add new verification_status column
            $table->enum('verification_status', ['pending', 'verified', 'failed'])
                  ->default('pending')
                  ->after('created_at');
        });

        // Update migrated_sessions table
        Schema::table('migrated_sessions', function (Blueprint $table) {
            // Add new verification_status column
            $table->enum('verification_status', ['pending', 'verified', 'failed'])
                  ->default('pending')
                  ->after('created_at');
        });

        // Migrate existing data: convert boolean 'verified' to enum 'verification_status'
        DB::statement("UPDATE migrated_clients SET verification_status = CASE
            WHEN verified = 1 THEN 'verified'
            WHEN verified = 0 AND verification_error IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END");

        DB::statement("UPDATE migrated_cases SET verification_status = CASE
            WHEN verified = 1 THEN 'verified'
            WHEN verified = 0 AND verification_error IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END");

        DB::statement("UPDATE migrated_sessions SET verification_status = CASE
            WHEN verified = 1 THEN 'verified'
            WHEN verified = 0 AND verification_error IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END");

        // Drop old verified column
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->dropColumn('verified');
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->dropColumn('verified');
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->dropColumn('verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back verified boolean column
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
        });

        // Migrate data back: convert enum to boolean
        DB::statement("UPDATE migrated_clients SET verified = CASE
            WHEN verification_status = 'verified' THEN 1
            ELSE 0
        END");

        DB::statement("UPDATE migrated_cases SET verified = CASE
            WHEN verification_status = 'verified' THEN 1
            ELSE 0
        END");

        DB::statement("UPDATE migrated_sessions SET verified = CASE
            WHEN verification_status = 'verified' THEN 1
            ELSE 0
        END");

        // Drop verification_status column
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });
    }
};
