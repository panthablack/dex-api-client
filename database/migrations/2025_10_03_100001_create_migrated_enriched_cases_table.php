<?php

use App\Enums\VerificationStatus;
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
        Schema::create('migrated_enriched_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_id')->unique();

            // Reference to shallow case
            $table->foreignId('shallow_case_id')
                ->nullable()
                ->constrained('migrated_shallow_cases')
                ->nullOnDelete();

            // All existing MigratedCase fields
            $table->string('outlet_name')->nullable();
            $table->json('client_ids')->nullable();
            $table->integer('outlet_activity_id');
            $table->integer('total_number_of_unidentified_clients')->nullable();
            $table->string('client_attendance_profile_code')->nullable();
            $table->date('created_date_time')->nullable();
            $table->date('end_date')->nullable();
            $table->string('exit_reason_code')->nullable();
            $table->string('ag_business_type_code')->nullable();
            $table->string('program_activity_name')->nullable();
            $table->json('sessions');

            // Enrichment tracking (simplified - no error fields)
            $table->json('api_response'); // Full GetCase result
            $table->timestamp('enriched_at')->nullable();

            // Verification (existing pattern)
            $table->enum('verification_status', [
                VerificationStatus::PENDING->value,
                VerificationStatus::VERIFIED->value,
                VerificationStatus::FAILED->value
            ])->default(VerificationStatus::PENDING->value);
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
        Schema::dropIfExists('migrated_enriched_cases');
    }
};
