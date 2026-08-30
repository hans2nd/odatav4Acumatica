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
            $table->json('semantic_profile')->nullable()->after('chatedp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->dropColumn('semantic_profile');
        });
    }
};
