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
        Schema::create('migrated_enriched_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('case_id');

            // Reference to shallow session
            $table->foreignId('shallow_session_id')
                ->nullable()
                ->constrained('migrated_shallow_sessions')
                ->nullOnDelete();

            // All existing MigratedSession fields
            $table->date('session_date');
            $table->integer('service_type_id');
            $table->integer('total_number_of_unidentified_clients');
            $table->string('fees_charged')->nullable();
            $table->string('money_business_community_education_workshop_code')->nullable();
            $table->boolean('interpreter_present')->default(false);
            $table->string('service_setting_code')->nullable();

            // Enrichment tracking (simplified - no error fields)
            $table->json('api_response');
            $table->timestamp('enriched_at')->nullable();
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
