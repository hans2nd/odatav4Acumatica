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
        Schema::table('ai_semantic_definitions', function (Blueprint $table) {
            // Drop the foreign key first because MySQL might be using the unique index 
            // ('connection_id', 'name') to satisfy the foreign key requirement.
            $table->dropForeign(['connection_id']);
            
            // Drop the old constraint based only on connection_id and name
            $table->dropUnique(['connection_id', 'name']);
            
            // Add the new composite constraint scoping by table and type
            $table->unique(
                ['connection_id', 'source_table', 'type', 'name'], 
                'ai_semantic_definitions_composite_unique'
            );
            
            // Re-add the foreign key
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_semantic_definitions', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->dropUnique('ai_semantic_definitions_composite_unique');
            
            $table->unique(['connection_id', 'name']);
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
        });
    }
};
