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
        Schema::create('sync_states', function (Blueprint $table) {
            $table->id();
            
            // Primary Information
            $table->unsignedBigInteger('sync_schedule_id')->unique();
            $table->string('entity_name');

            // Current Position (Composite Watermark)
            $table->timestamp('watermark_timestamp')->nullable();
            $table->string('watermark_primary_key')->nullable();

            // Execution Information
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();

            // Statistics
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedBigInteger('total_rows_read')->default(0);
            $table->unsignedBigInteger('total_rows_inserted')->default(0);
            $table->unsignedBigInteger('total_rows_updated')->default(0);
            $table->unsignedBigInteger('total_rows_failed')->default(0);

            // Execution Status
            $table->enum('status', ['Idle', 'Running', 'Completed', 'Failed', 'Paused'])->default('Idle');

            // Concurrency
            $table->string('lock_owner')->nullable();
            $table->timestamp('lock_acquired_at')->nullable();

            // Retry
            $table->unsignedInteger('retry_count')->default(0);

            // Audit
            $table->timestamps();

            // Constraints & Indexes
            $table->foreign('sync_schedule_id')->references('id')->on('sync_schedules')->onDelete('cascade');
            $table->index('entity_name');
            $table->index('status');
            $table->index('watermark_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
