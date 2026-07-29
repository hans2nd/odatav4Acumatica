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
            $table->index('started_at');
            $table->index('completed_at');
            $table->index('duration_seconds');
            $table->index('source');
            $table->index('current_stage');
            // Composite indexes for fast analytics grouping
            $table->index(['entity_key', 'status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['duration_seconds']);
            $table->dropIndex(['source']);
            $table->dropIndex(['current_stage']);
            $table->dropIndex(['entity_key', 'status', 'started_at']);
        });
    }
};
