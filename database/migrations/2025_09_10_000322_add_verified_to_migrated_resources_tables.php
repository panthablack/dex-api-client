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
        // Add verified column to migrated_clients table
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
            $table->timestamp('verified_at')->nullable()->after('verified');
            $table->text('verification_error')->nullable()->after('verified_at');
        });

        // Add verified column to migrated_cases table
        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
            $table->timestamp('verified_at')->nullable()->after('verified');
            $table->text('verification_error')->nullable()->after('verified_at');
        });

        // Add verified column to migrated_sessions table
        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('created_at');
            $table->timestamp('verified_at')->nullable()->after('verified');
            $table->text('verification_error')->nullable()->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migrated_clients', function (Blueprint $table) {
            $table->dropColumn(['verified', 'verified_at', 'verification_error']);
        });

        Schema::table('migrated_cases', function (Blueprint $table) {
            $table->dropColumn(['verified', 'verified_at', 'verification_error']);
        });

        Schema::table('migrated_sessions', function (Blueprint $table) {
            $table->dropColumn(['verified', 'verified_at', 'verification_error']);
        });
    }
};
