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
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false)->after('description');
        });

        // Set is_superadmin to true for existing Super Administrator role
        \Illuminate\Support\Facades\DB::table('roles')
            ->where('name', 'Super Administrator')
            ->update(['is_superadmin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_superadmin');
        });
    }
};
