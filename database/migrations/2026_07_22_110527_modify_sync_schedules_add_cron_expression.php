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
        Schema::table('sync_schedules', function (Blueprint $table) {
            $table->string('cron_expression')->default('* * * * *')->after('entity_key');
            $table->dropColumn(['frequency', 'interval', 'time', 'day_of_week', 'day_of_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_schedules', function (Blueprint $table) {
            $table->dropColumn('cron_expression');
            $table->string('frequency')->nullable();
            $table->integer('interval')->nullable();
            $table->time('time')->nullable();
            $table->string('day_of_week')->nullable();
            $table->string('day_of_month')->nullable();
        });
    }
};
