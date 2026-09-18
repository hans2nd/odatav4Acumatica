<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->string('source_connection_id', 100)->nullable()->default('acumatica_default');
            $table->string('source_object', 255)->comment('OData entity / source table name');
            $table->string('destination_connection_id', 100)->nullable()->default('database_default');
            $table->string('destination_table', 255);
            $table->string('sync_mode', 50)->default('full')->comment('full or incremental');
            $table->string('watermark_type', 50)->nullable();
            $table->string('watermark_column', 100)->nullable();
            $table->unsignedInteger('batch_size')->default(2000);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('type', 50)->default('master')->comment('master or transaction');
            $table->string('icon', 100)->default('database');
            $table->unsignedInteger('schedule_minutes')->nullable();
            $table->json('extra_attributes')->nullable()->comment('Catch-all for unmapped JSON properties');
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        Schema::create('sync_entity_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sync_entity_id');
            $table->string('source_field', 255);
            $table->string('destination_field', 255)->nullable();
            $table->string('data_type', 100)->nullable();
            $table->string('semantic_type', 100)->nullable();
            $table->boolean('is_key')->default(false);
            $table->boolean('is_nullable')->default(true);
            $table->text('transform_rule')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('sync_entity_id')
                ->references('id')
                ->on('sync_entities')
                ->onDelete('cascade');

            $table->unique(['sync_entity_id', 'source_field']);
            $table->index('sync_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_entity_fields');
        Schema::dropIfExists('sync_entities');
    }
};
