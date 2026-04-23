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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_key', 100);
            $table->string('entity_description', 255)->nullable();
            $table->string('odata_entity', 255)->nullable();
            $table->string('table_name', 255)->nullable();
            $table->string('type', 20)->default('master');
            $table->string('status', 20)->default('pending'); // pending, running, success, failed
            $table->integer('records_fetched')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('entity_key');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
