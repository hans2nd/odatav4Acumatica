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
        Schema::table('sync_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_logs', 'session_id')) {
                $table->string('session_id')->nullable()->after('id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            if (Schema::hasColumn('sync_logs', 'session_id')) {
                $table->dropColumn('session_id');
            }
        });
    }
};
