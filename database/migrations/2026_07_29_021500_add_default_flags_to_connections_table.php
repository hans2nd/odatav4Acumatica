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
            if (!Schema::hasColumn('connections', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_connected');
            }
            if (!Schema::hasColumn('connections', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_default');
            }
            if (!Schema::hasColumn('connections', 'read_only')) {
                $table->boolean('read_only')->default(false)->after('is_system');
            }
            if (!Schema::hasColumn('connections', 'deletable')) {
                $table->boolean('deletable')->default(true)->after('read_only');
            }
            if (!Schema::hasColumn('connections', 'editable')) {
                $table->boolean('editable')->default(true)->after('deletable');
            }
            if (!Schema::hasColumn('connections', 'status')) {
                $table->string('status')->default('Disconnected')->after('editable');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('connections', 'is_default')) $columns[] = 'is_default';
            if (Schema::hasColumn('connections', 'is_system')) $columns[] = 'is_system';
            if (Schema::hasColumn('connections', 'read_only')) $columns[] = 'read_only';
            if (Schema::hasColumn('connections', 'deletable')) $columns[] = 'deletable';
            if (Schema::hasColumn('connections', 'editable')) $columns[] = 'editable';
            if (Schema::hasColumn('connections', 'status')) $columns[] = 'status';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
