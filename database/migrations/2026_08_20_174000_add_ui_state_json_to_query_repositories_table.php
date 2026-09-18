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
        Schema::table('query_repositories', function (Blueprint $table) {
            $table->json('ui_state_json')->nullable()->after('parameter_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_repositories', function (Blueprint $table) {
            $table->dropColumn('ui_state_json');
        });
    }
};
