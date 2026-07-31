<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Changing a JSON column to TEXT to allow encrypted string storage.
        // We use raw statement for wider database engine compatibility.
        // On older MySQL, base64 strings (encrypted payload) are not valid JSON.
        
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE connections MODIFY credentials LONGTEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE connections ALTER COLUMN credentials TYPE TEXT USING credentials::text');
        } elseif ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE connections ALTER COLUMN credentials NVARCHAR(MAX) NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite is flexible with types, but we'll try to change it using Schema if dbal is installed,
            // otherwise we just leave it since SQLite doesn't strictly enforce json validity for text insertion.
            try {
                Schema::table('connections', function (Blueprint $table) {
                    $table->text('credentials')->nullable()->change();
                });
            } catch (\Exception $e) {
                // Ignore if doctrine/dbal is missing on sqlite
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE connections MODIFY credentials JSON NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE connections ALTER COLUMN credentials TYPE JSON USING credentials::json');
        } elseif ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE connections ALTER COLUMN credentials NVARCHAR(MAX) NULL');
        } elseif ($driver === 'sqlite') {
            try {
                Schema::table('connections', function (Blueprint $table) {
                    $table->json('credentials')->nullable()->change();
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
};
