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
        Schema::create('sync_metadata_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_key', 100)->unique(); // e.g., 'stock_items'
            $table->string('provider', 50)->default('acumatica');
            $table->string('provider_version', 50)->nullable();
            $table->string('metadata_version', 100)->nullable(); // Generated hash or UUID
            $table->string('metadata_hash', 64)->nullable(); // SHA256 of schema
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
        });

        Schema::create('sync_metadata_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('sync_metadata_definitions')->onDelete('cascade');
            $table->string('field_name', 150);
            $table->string('data_type', 50)->nullable();
            $table->boolean('is_primary_key')->default(false);
            $table->boolean('is_nullable')->default(true);
            $table->integer('max_length')->nullable();
            $table->integer('precision')->nullable();
            $table->integer('scale')->nullable();
            $table->string('relationship_type', 50)->nullable(); // e.g., navigation, foreign_key
            $table->string('related_entity', 100)->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'field_name']);
        });

        Schema::create('sync_metadata_history', function (Blueprint $table) {
            $table->id();
            $table->string('entity_key', 100);
            $table->string('previous_version', 100)->nullable();
            $table->string('new_version', 100);
            $table->json('changes_detected')->nullable(); // JSON payload of what changed
            $table->string('source', 50)->default('provider');
            $table->timestamps();
        });

        Schema::create('sync_metadata_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('entity_key', 100);
            $table->integer('cache_hits')->default(0);
            $table->integer('cache_misses')->default(0);
            $table->integer('provider_calls')->default(0);
            $table->integer('refresh_count')->default(0);
            $table->integer('validation_errors')->default(0);
            $table->float('last_discovery_time_ms')->default(0);
            $table->timestamps();
            
            $table->unique('entity_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_metadata_statistics');
        Schema::dropIfExists('sync_metadata_history');
        Schema::dropIfExists('sync_metadata_fields');
        Schema::dropIfExists('sync_metadata_definitions');
    }
};
