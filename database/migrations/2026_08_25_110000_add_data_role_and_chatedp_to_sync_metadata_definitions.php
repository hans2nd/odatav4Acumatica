<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->string('data_role', 50)->default('other')->after('description');
            $table->boolean('chatedp_enabled')->default(false)->after('data_role');
        });
    }

    public function down(): void
    {
        Schema::table('sync_metadata_definitions', function (Blueprint $table) {
            $table->dropColumn(['data_role', 'chatedp_enabled']);
        });
    }
};
