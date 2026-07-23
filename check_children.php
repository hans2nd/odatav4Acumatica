<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = app(App\Http\Controllers\UserManagementController::class);
$menus = \App\Models\Menu::with(['permissions.action'])->orderBy('sort_order')->get();
$menuPermissionIds = \Illuminate\Support\Facades\DB::table('menu_permissions')->pluck('permission_id')->toArray();
$otherPermissions = \App\Models\Permission::with('action')->whereNotIn('id', $menuPermissionIds)->get();

$allEntities = array_merge(config('acumatica.default_entities', []), config('acumatica.entities', []));

$entityPermissions = [];
$remainingOther = [];

foreach ($otherPermissions as $perm) {
    $parts = explode('.', $perm->permission_key);
    $prefix = $parts[0];

    if (isset($allEntities[$prefix])) {
        $entityPermissions[$prefix][] = $perm;
    } else {
        $remainingOther[] = $perm;
    }
}

$menusArray = $menus->toArray();

$masterDataMenuId = null;
foreach ($menusArray as $menu) {
    if ($menu['route_name'] === 'master-data.index') {
        $masterDataMenuId = $menu['id'];
    }
}

foreach ($entityPermissions as $entityKey => $perms) {
    $config = $allEntities[$entityKey];
    $type = $config['type'];
    
    $parentId = null;
    if ($type === 'master' && $masterDataMenuId) {
        $parentId = $masterDataMenuId;
    }

    $virtualMenu = [
        'id' => 'virtual-' . $entityKey,
        'parent_id' => $parentId,
        'title' => $config['description'] ?? $entityKey,
        'icon' => $config['icon'] ?? 'database',
        'permissions' => "PERMISSIONS ARRAY",
        'children' => [],
        'sort_order' => 9999,
    ];
    
    $menusArray[] = $virtualMenu;
}

$buildArrayTree = function ($items, $parentId = null) use (&$buildArrayTree) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            $children = $buildArrayTree($items, $item['id']);
            $item['children'] = $children;
            $branch[] = $item;
        }
    }
    return $branch;
};

$menuTree = $buildArrayTree($menusArray);

function findMasterData($tree) {
    foreach ($tree as $node) {
        if ($node['title'] === 'Synchronization') {
            foreach ($node['children'] as $child) {
                if ($child['title'] === 'Master Data') {
                    return $child;
                }
            }
        }
    }
    return null;
}

$masterData = findMasterData($menuTree);
echo "Master Data children: \n";
if ($masterData) {
    echo json_encode(array_map(function($m) { return $m['title']; }, $masterData['children']), JSON_PRETTY_PRINT);
} else {
    echo "Master Data not found\n";
}
