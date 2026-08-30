<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            
            $table->json('query_plan_json')->nullable();
            $table->string('sql_hash', 64)->nullable();
            
            $table->string('status', 50); // e.g., 'success', 'error', 'rejected'
            $table->float('duration_ms')->nullable();
            $table->integer('row_count')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
