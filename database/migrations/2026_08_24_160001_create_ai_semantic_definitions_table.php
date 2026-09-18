<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_semantic_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->enum('type', ['metric', 'dimension']);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('expression'); // e.g., 'SUM(amount)', 'users.status'
            $table->string('source_table', 150);
            $table->string('source_column', 150)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            $table->unique(['connection_id', 'name']); // Names must be unique per connection
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_semantic_definitions');
    }
};
