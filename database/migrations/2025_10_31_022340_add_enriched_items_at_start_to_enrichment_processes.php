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
        Schema::table('enrichment_processes', function (Blueprint $table) {
            // Track how many enriched items existed when this process started
            // Used to calculate newly_enriched vs already_enriched
            $table->unsignedInteger('enriched_items_at_start')->default(0)->after('total_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrichment_processes', function (Blueprint $table) {
            $table->dropColumn('enriched_items_at_start');
        });
    }
};
