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
            $table->dropColumn('interval_minutes');
            
            $table->string('frequency', 20)->nullable()->after('entity_key')->comment('daily, weekly, monthly');
            $table->time('time')->nullable()->after('frequency');
            $table->integer('day_of_week')->nullable()->after('time')->comment('1=Mon...7=Sun');
            $table->integer('day_of_month')->nullable()->after('day_of_week')->comment('1-31');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_schedules', function (Blueprint $table) {
            $table->integer('interval_minutes')->default(0)->after('entity_key');
            
            $table->dropColumn(['frequency', 'time', 'day_of_week', 'day_of_month']);
        });
    }
};
