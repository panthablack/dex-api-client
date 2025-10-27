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
        Schema::create('enrichment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrichment_process_id')->constrained('enrichment_processes')->cascadeOnDelete();
            $table->unsignedInteger('batch_number'); // 1, 2, 3, ...
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->json('item_ids'); // Array of case_ids or session_ids to enrich
            $table->unsignedInteger('batch_size')->default(100); // Expected size (e.g., 100)
            $table->unsignedInteger('items_processed')->default(0); // Number enriched in this batch
            $table->unsignedInteger('items_failed')->default(0); // Number that failed in this batch
            $table->text('error_message')->nullable(); // If batch failed, why?
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['enrichment_process_id', 'status']);
            $table->index('batch_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrichment_batches');
    }
};
