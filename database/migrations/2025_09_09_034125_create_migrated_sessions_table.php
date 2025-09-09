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
        Schema::create('migrated_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->index();
            $table->string('case_id')->index();
            $table->integer('service_type_id');
            $table->date('session_date');
            $table->integer('duration_minutes');
            $table->string('location')->nullable();
            $table->string('session_status', 50)->nullable();
            $table->string('attendees')->nullable();
            $table->text('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->json('api_response')->nullable(); // Store full API response
            $table->string('migration_batch_id')->nullable()->index();
            $table->timestamp('migrated_at')->nullable();
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
