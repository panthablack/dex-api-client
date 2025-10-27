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
        Schema::create('migrated_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('slk')->nullable();
            $table->boolean('consent_to_provide_details')->default(false);
            $table->boolean('consented_for_future_contacts')->default(false);
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            $table->boolean('is_using_psuedonym')->default(false);
            $table->date('birth_date')->nullable();
            $table->boolean('is_birth_date_an_estimate')->default(false);
            $table->string('gender_code')->nullable();
            $table->string('gender_details')->nullable();
            $table->json('residential_address')->nullable();
            $table->string('country_of_birth_code')->nullable();
            $table->string('language_spoken_at_home_code')->nullable();
            $table->string('aboriginal_or_torres_strait_islander_origin_code')->nullable();
            $table->boolean('has_disabilities')->default(false);
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
        Schema::dropIfExists('migrated_clients');
    }
};
