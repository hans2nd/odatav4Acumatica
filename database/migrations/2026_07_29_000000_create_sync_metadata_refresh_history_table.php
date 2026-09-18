<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_metadata_refresh_history', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->index();
            $table->string('provider')->nullable();
            $table->string('refresh_reason')->nullable();
            $table->string('cache_key')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('cache_duration_ms')->nullable();
            $table->integer('database_duration_ms')->nullable();
            $table->integer('provider_network_ms')->nullable();
            $table->integer('provider_processing_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->string('status'); // PENDING, REFRESHING, READY, STALE, FAILED
            $table->text('error_message')->nullable();
            $table->string('previous_hash')->nullable();
            $table->string('current_hash')->nullable();
            $table->string('metadata_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_metadata_refresh_history');
    }
};
