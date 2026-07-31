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
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity')->index();
            $table->string('status')->default('pending')->index();
            $table->integer('processed_rows')->default(0);
            $table->integer('total_rows')->nullable();
            $table->integer('progress')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
};
