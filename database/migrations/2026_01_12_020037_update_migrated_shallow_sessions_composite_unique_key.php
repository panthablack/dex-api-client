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
        Schema::table('migrated_shallow_sessions', function (Blueprint $table) {
            // Drop the unique constraint on session_id
            $table->dropUnique(['session_id']);

            // Add composite unique constraint on (case_id, session_id)
            $table->unique(['case_id', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migrated_shallow_sessions', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique(['case_id', 'session_id']);

            // Restore the unique constraint on session_id
            $table->unique('session_id');
        });
    }
};
