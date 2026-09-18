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
        Schema::table('sync_entity_fields', function (Blueprint $table) {
            $table->string('label', 150)->nullable()->after('semantic_type');
            $table->text('description')->nullable()->after('label');
            $table->json('aliases')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_entity_fields', function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'aliases']);
        });
    }
};
