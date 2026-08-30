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
        Schema::table('sync_metadata_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_metadata_fields', 'semantic_type')) {
                $table->string('semantic_type', 50)->nullable()->after('data_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_metadata_fields', function (Blueprint $table) {
            if (Schema::hasColumn('sync_metadata_fields', 'semantic_type')) {
                $table->dropColumn('semantic_type');
            }
        });
    }
};
