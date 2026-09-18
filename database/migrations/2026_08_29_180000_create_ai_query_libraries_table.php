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
        Schema::create('ai_query_libraries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->char('signature_hash', 64);
            $table->json('canonical_plan_json');
            $table->unsignedBigInteger('query_repository_id')->nullable();
            $table->unsignedInteger('repository_version')->default(1);
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('connection_id')
                ->references('id')
                ->on('connections')
                ->onDelete('cascade');

            $table->foreign('query_repository_id')
                ->references('id')
                ->on('query_repositories')
                ->onDelete('set null');

            $table->unique(['connection_id', 'signature_hash'], 'uq_ai_query_lib_conn_sig');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_query_libraries');
    }
};
