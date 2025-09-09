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
        Schema::create('migrated_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_id')->unique()->index();
            $table->string('client_id')->index();
            $table->integer('outlet_activity_id');
            $table->string('referral_source_code', 50);
            $table->json('reasons_for_assistance'); // Array of reasons
            $table->integer('total_unidentified_clients')->nullable();
            $table->string('client_attendance_profile_code', 50)->nullable();
            $table->date('end_date')->nullable();
            $table->string('exit_reason_code', 50)->nullable();
            $table->string('ag_business_type_code', 10)->nullable();
            $table->json('api_response')->nullable(); // Store full API response
            $table->string('migration_batch_id')->nullable()->index();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamps();
            
            // Foreign key to migrated clients
            $table->foreign('client_id')->references('client_id')->on('migrated_clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migrated_cases');
    }
};
