<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\MenuService;

return new class extends Migration
{
    public function up(): void
    {
        $syncParent = DB::table('menus')
            ->whereNull('parent_id')
            ->where('title', 'Synchronization')
            ->first();

        $parentId = $syncParent ? $syncParent->id : null;

        $exists = DB::table('menus')
            ->where('route_name', 'etl-pipeline.index')
            ->exists();

        if (!$exists) {
            DB::table('menus')->insert([
                'parent_id'       => $parentId,
                'title'           => 'ETL Syncron',
                'icon'            => 'conversion_path',
                'route_name'      => 'etl-pipeline.index',
                'sort_order'      => 25,
                'is_active'       => true,
                'is_visible'      => true,
                'permission_key'  => 'sync_engine.manage',
                'type'            => 'link',
                'badge'           => 'SP',
                'open_in_new_tab' => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        MenuService::clearMenuCache();
    }

    public function down(): void
    {
        DB::table('menus')->where('route_name', 'etl-pipeline.index')->delete();
        MenuService::clearMenuCache();
    }
};

