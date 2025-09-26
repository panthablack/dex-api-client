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
        Schema::create('migrated_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('case_id')->index();
            $table->string('session_id')->unique()->index();
            $table->date('session_date');
            $table->integer('service_type_id');
            $table->integer('total_number_of_unidentified_clients');
            $table->string('fees_charged')->nullable();
            $table->string('money_business_community_education_workshop_code')->nullable();
            $table->boolean('interpreter_present')->default(false);
            $table->string('service_setting_code')->nullable();
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

            // Foreign key to migrated cases
            $table->foreign('case_id')->references('case_id')->on('migrated_cases')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migrated_sessions');
    }
};
