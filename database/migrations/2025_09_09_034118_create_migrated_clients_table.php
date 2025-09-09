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
        Schema::create('migrated_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('is_birth_date_estimate')->default(false);
            $table->enum('gender', ['M', 'F', 'X', '9'])->nullable();
            $table->string('suburb')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country_of_birth', 10)->nullable();
            $table->string('primary_language', 10)->nullable();
            $table->string('indigenous_status', 1)->default('9');
            $table->boolean('interpreter_required')->default(false);
            $table->boolean('disability_flag')->default(false);
            $table->boolean('is_using_pseudonym')->default(false);
            $table->boolean('consent_to_provide_details')->default(false);
            $table->boolean('consent_to_be_contacted')->default(false);
            $table->string('client_type')->default('Individual');
            $table->json('api_response')->nullable(); // Store full API response
            $table->string('migration_batch_id')->nullable()->index();
            $table->timestamp('migrated_at')->nullable();
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
