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
        Schema::create('query_repository_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_repository_id')->nullable()->constrained('query_repositories')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->decimal('duration', 10, 4)->nullable(); // duration in seconds/ms
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_repository_logs');
    }
};
