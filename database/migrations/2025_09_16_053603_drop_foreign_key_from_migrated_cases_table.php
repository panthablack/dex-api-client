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
        Schema::table('migrated_cases', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['client_id']);

            // Make client_id nullable since cases are independent of clients
            $table->string('client_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migrated_cases', function (Blueprint $table) {
            // Make client_id non-nullable again
            $table->string('client_id')->nullable(false)->change();

            // Recreate the foreign key constraint
            $table->foreign('client_id')->references('client_id')->on('migrated_clients')->onDelete('cascade');
        });
    }
};
