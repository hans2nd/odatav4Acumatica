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
        Schema::table('connections', function (Blueprint $table) {
            if (!Schema::hasColumn('connections', 'is_analytics_source')) {
                $table->boolean('is_analytics_source')->default(false)->after('is_default');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            if (Schema::hasColumn('connections', 'is_analytics_source')) {
                $table->dropColumn('is_analytics_source');
            }
        });
    }
};
