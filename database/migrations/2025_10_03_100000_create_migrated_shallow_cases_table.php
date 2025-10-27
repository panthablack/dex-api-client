<?php

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
        Schema::create('migrated_shallow_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_id')->unique();
            $table->string('outlet_name')->nullable();
            $table->date('created_date_time')->nullable();
            $table->string('client_attendance_profile_code')->nullable();
            $table->json('api_response'); // Full SearchCase result for this case
            $table->foreignIdFor(DataMigrationBatch::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migrated_shallow_cases');
    }
};
