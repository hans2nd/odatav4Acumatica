<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->json('business_domains')->nullable()->after('chatedp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->dropColumn('business_domains');
        });
    }
};
