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
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->string('logical_name', 100)->nullable()->after('entity_key');
            $table->json('aliases')->nullable()->after('logical_name');
            $table->text('description')->nullable()->after('aliases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->dropColumn(['logical_name', 'aliases', 'description']);
        });
    }
};
