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
            $table->string('outlet_name')->nullable();
            $table->json('client_ids')->nullable(); // Array of client IDs
            $table->integer('outlet_activity_id');
            $table->integer('total_number_of_unidentified_clients')->nullable();
            $table->string('client_attendance_profile_code')->nullable();
            $table->date('created_date_time')->nullable();
            $table->date('end_date')->nullable();
            $table->string('exit_reason_code')->nullable();
            $table->string('ag_business_type_code')->nullable();
            $table->string('program_activity_name')->nullable();
            $table->json('sessions');
            $table->json('api_response')->nullable(); // Store full API response
            $table->foreignIdFor(DataMigrationBatch::class)->constrained();
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
