<?php

use App\Enums\DataMigrationBatchStatus;
use App\Enums\ResourceType;
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
        Schema::create('data_migration_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_migration_id')->constrained('data_migrations')->onDelete('cascade');
            $table->enum('resource_type', ResourceType::getValues());
            $table->integer('batch_number'); // Sequential batch number
            $table->integer('batch_size');
            $table->integer('page_index'); // DSS API page index
            $table->integer('page_size')->default(100);
            $table->enum('status', DataMigrationBatchStatus::getValues())->default('pending');
            $table->integer('items_received')->default(0);
            $table->integer('items_stored')->default(0);
            $table->json('api_filters'); // Batch level filters
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['data_migration_id', 'resource_type', 'batch_number'], 'migration_batch_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_migration_batches');
    }
};
