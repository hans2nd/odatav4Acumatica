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
        Schema::create('query_repositories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('database_connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('sql_text');
            $table->string('query_type', 50)->default('read_only');
            $table->json('parameter_json')->nullable();
            $table->json('output_column_json')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_repositories');
    }
};
