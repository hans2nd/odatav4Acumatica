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
        Schema::table('sync_states', function (Blueprint $table) {
            $table->timestamp('lock_expires_at')->nullable()->after('lock_acquired_at');
            $table->timestamp('heartbeat_at')->nullable()->after('lock_expires_at');
            
            $table->index('lock_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_states', function (Blueprint $table) {
            $table->dropIndex(['lock_expires_at']);
            $table->dropColumn(['heartbeat_at', 'lock_expires_at']);
        });
    }
};
