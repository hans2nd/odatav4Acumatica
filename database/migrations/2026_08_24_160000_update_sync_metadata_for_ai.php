<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            // Drop existing unique constraint
            $table->dropUnique(['entity_key']);
            
            // Add connection_id for strict metadata isolation
            $table->unsignedBigInteger('connection_id')->nullable()->after('id');
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            
            // New unique compound constraint
            $table->unique(['entity_key', 'connection_id'], 'idx_meta_entity_conn');
        });

        Schema::table('sync_metadata_statistics', function (Blueprint $table) {
            $table->dropUnique(['entity_key']);
            $table->unsignedBigInteger('connection_id')->nullable()->after('id');
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            $table->unique(['entity_key', 'connection_id'], 'idx_stat_entity_conn');
        });
    }

    public function down(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->dropUnique('idx_meta_entity_conn');
            $table->dropForeign(['connection_id']);
            $table->dropColumn('connection_id');
            $table->unique('entity_key');
        });

        Schema::table('sync_metadata_statistics', function (Blueprint $table) {
            $table->dropUnique('idx_stat_entity_conn');
            $table->dropForeign(['connection_id']);
            $table->dropColumn('connection_id');
            $table->unique('entity_key');
        });
    }
};
