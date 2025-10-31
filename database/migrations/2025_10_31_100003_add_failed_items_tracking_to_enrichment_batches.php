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
        Schema::table('enrichment_batches', function (Blueprint $table) {
            // Add failed_item_ids to track which specific items failed
            $table->json('failed_item_ids')->nullable()->after('items_failed');

            // Add items_skipped to track items discovered as already enriched during processing
            $table->unsignedInteger('items_skipped')->default(0)->after('items_failed');
        });

        // Update the status enum to include PARTIAL
        // Use conditional logic to handle both MySQL and SQLite
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE enrichment_batches MODIFY status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'PARTIAL', 'FAILED') DEFAULT 'PENDING'");
        } else {
            // SQLite doesn't support enums, so we'll just add the values to the check constraint
            // We'll need to drop and recreate the column with a string type instead
            // For now, SQLite will allow any string, which is sufficient
            // The constraint is enforced at the application level via the model
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrichment_batches', function (Blueprint $table) {
            $table->dropColumn(['failed_item_ids', 'items_skipped']);
        });

        // Revert status enum for MySQL
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE enrichment_batches MODIFY status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED') DEFAULT 'PENDING'");
        }
    }
};
