<?php

use App\Enums\VerificationStatus;
use App\Models\DataMigrationBatch;
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
            $table->string('client_id')->nullable();
            $table->integer('outlet_activity_id');
            $table->string('referral_source_code', 50);
            $table->json('reasons_for_assistance'); // Array of reasons
            $table->integer('total_unidentified_clients')->nullable();
            $table->string('client_attendance_profile_code', 50)->nullable();
            $table->date('end_date')->nullable();
            $table->string('exit_reason_code', 50)->nullable();
            $table->string('ag_business_type_code', 10)->nullable();
            $table->json('api_response')->nullable(); // Store full API response
            $table->foreignIdFor(DataMigrationBatch::class);
            $table->enum('verification_status', [
                VerificationStatus::PENDING,
                VerificationStatus::VERIFIED,
                VerificationStatus::FAILED
            ])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_error')->nullable();
            $table->timestamps();
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
