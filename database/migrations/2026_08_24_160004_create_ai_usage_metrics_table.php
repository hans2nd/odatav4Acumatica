<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            
            $table->string('provider', 100)->nullable();
            $table->string('model', 100)->nullable();
            
            $table->integer('request_tokens')->nullable();
            $table->integer('response_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            
            $table->decimal('estimated_cost', 10, 6)->nullable();
            
            $table->float('duration_ms')->nullable();
            $table->string('status', 50)->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_metrics');
    }
};
