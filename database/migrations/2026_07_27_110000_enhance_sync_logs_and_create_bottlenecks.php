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
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->string('current_stage')->nullable()->after('status');
            $table->integer('current_batch')->default(0)->after('current_stage');
            $table->integer('total_batch')->nullable()->after('current_batch');
            
            $table->integer('records_updated')->default(0)->after('records_inserted');
            $table->integer('records_deleted')->default(0)->after('records_updated');
            $table->integer('records_skipped')->default(0)->after('records_deleted');
            $table->integer('records_failed')->default(0)->after('records_skipped');
            
            $table->integer('retry_count')->default(0)->after('records_failed');
            $table->integer('resume_count')->default(0)->after('retry_count');
            
            $table->float('rows_per_second')->nullable()->after('resume_count');
            $table->float('peak_rows_per_second')->nullable()->after('rows_per_second');
            $table->float('average_rows_per_second')->nullable()->after('peak_rows_per_second');
            
            $table->float('peak_memory_mb')->nullable()->after('average_rows_per_second');
            $table->string('current_operation')->nullable()->after('peak_memory_mb');
            $table->json('current_checkpoint')->nullable()->after('current_operation');
            
            $table->longText('stack_trace')->nullable()->after('error_message');
            $table->string('triggered_by')->default('manual')->after('stack_trace');
            $table->string('execution_source')->default('web')->after('triggered_by');
        });

        Schema::create('sync_bottlenecks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('sync_logs')->onDelete('cascade');
            $table->string('entity_key', 100);
            $table->string('pipeline'); // e.g., 'processing', 'persistence', 'provider'
            $table->string('stage'); // e.g., 'fetchPage', 'upsertBatch'
            $table->float('duration_ms');
            $table->float('threshold_ms');
            $table->string('severity')->default('warning'); // warning, critical
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_bottlenecks');
        
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropColumn([
                'current_stage', 'current_batch', 'total_batch',
                'records_updated', 'records_deleted', 'records_skipped', 'records_failed',
                'retry_count', 'resume_count',
                'rows_per_second', 'peak_rows_per_second', 'average_rows_per_second',
                'peak_memory_mb', 'current_operation', 'current_checkpoint',
                'stack_trace', 'triggered_by', 'execution_source'
            ]);
        });
    }
};
