<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionGeneratorService
{
    /**
     * Generate dynamic permissions based on config entities and system features.
     */
    public function generate()
    {
        DB::transaction(function () {
            $this->generateSystemResources();
            $this->generateEntityResources();
            $this->generateApiEndpoints();
        });
    }

    public function generateApiEndpoints()
    {
        $reportModule = Module::where('code', 'report')->first();
        if (!$reportModule) return;

        $endpoints = \App\Models\ApiEndpoint::all();
        $viewAction = Action::where('code', 'view')->first();

        // Get current endpoint resources to find deleted ones
        $existingEndpointSlugs = Resource::where('slug', 'like', 'api.endpoint.%')->pluck('slug')->toArray();
        $expectedSlugs = [];

        foreach ($endpoints as $ep) {
            $slug = "api.endpoint.{$ep->endpoint_slug}";
            $expectedSlugs[] = $slug;

            $resource = Resource::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => "API Endpoint: {$ep->name}", 
                    'module_id' => $reportModule->id,
                    'description' => "Dynamically generated API Endpoint"
                ]
            );
            
            if ($viewAction) {
                Permission::updateOrCreate(
                    ['resource_id' => $resource->id, 'action_id' => $viewAction->id],
                    [
                        'permission_key' => "{$resource->slug}.{$viewAction->code}",
                        'description' => "Can {$viewAction->name} {$resource->name}"
                    ]
                );
            }
        }

        // Clean up deleted endpoint resources
        $toDelete = array_diff($existingEndpointSlugs, $expectedSlugs);
        if (!empty($toDelete)) {
            Resource::whereIn('slug', $toDelete)->delete();
        }
    }

    private function generateSystemResources()
    {
        $systemModule = Module::where('code', 'system')->first();
        $reportModule = Module::where('code', 'report')->first();

        $systemResources = [
            ['name' => 'Settings', 'slug' => 'settings', 'module_id' => $systemModule->id, 'actions' => ['manage', 'view']],
            ['name' => 'Sync Engine', 'slug' => 'sync_engine', 'module_id' => $systemModule->id, 'actions' => ['manage', 'execute']],
            ['name' => 'Sync Logs', 'slug' => 'logs', 'module_id' => $systemModule->id, 'actions' => ['view', 'delete']],
            ['name' => 'Users & Roles', 'slug' => 'users', 'module_id' => $systemModule->id, 'actions' => ['manage', 'view']],
            ['name' => 'Report Builder', 'slug' => 'report_builder', 'module_id' => $reportModule->id, 'actions' => ['manage', 'view']],
            ['name' => 'Endpoint API', 'slug' => 'endpoint_api', 'module_id' => $reportModule->id, 'actions' => ['manage', 'view']],
            ['name' => 'Query Builder', 'slug' => 'query_builder', 'module_id' => $reportModule->id, 'actions' => ['manage', 'view']],
            ['name' => 'Query Repository', 'slug' => 'query_repository', 'module_id' => $reportModule->id, 'actions' => ['view', 'create', 'update', 'delete', 'execute']],
            ['name' => 'AI Settings', 'slug' => 'ai_settings', 'module_id' => $systemModule->id, 'actions' => ['view', 'update', 'test']],
            ['name' => 'AI Metadata', 'slug' => 'ai_metadata', 'module_id' => $systemModule->id, 'actions' => ['view', 'update']],
            ['name' => 'ChatEDP', 'slug' => 'chatedp', 'module_id' => $systemModule->id, 'actions' => ['access', 'view', 'manage']],
        ];

        foreach ($systemResources as $res) {
            $resource = Resource::updateOrCreate(
                ['slug' => $res['slug']],
                ['name' => $res['name'], 'module_id' => $res['module_id']]
            );

            foreach ($res['actions'] as $actionCode) {
                $action = Action::where('code', $actionCode)->first();
                if ($action) {
                    Permission::updateOrCreate(
                        ['resource_id' => $resource->id, 'action_id' => $action->id],
                        [
                            'permission_key' => "{$resource->slug}.{$action->code}",
                            'description' => "Can {$action->name} {$resource->name}"
                        ]
                    );
                }
            }
        }
    }

    private function generateEntityResources()
    {
        $masterModule = Module::where('code', 'master-data')->first();
        $transactionModule = Module::where('code', 'transaction')->first();
        
        // Read directly from EntityConfigService to get the freshest data after save/delete
        $entities = app(\App\Services\EntityConfigService::class)->getAll();

        // Default actions for any data entity
        $entityActions = ['view', 'create', 'update', 'delete', 'sync', 'export', 'import'];

        $expectedSlugs = [];

        foreach ($entities as $key => $config) {
            $type = $config['type'] ?? 'master';
            $moduleId = $type === 'master' ? $masterModule->id : $transactionModule->id;

            $resourceName = Str::title(str_replace('_', ' ', $key));
            $expectedSlugs[] = $key;

            $resource = Resource::updateOrCreate(
                ['slug' => $key],
                ['name' => $resourceName, 'module_id' => $moduleId, 'description' => $config['description'] ?? '']
            );

            foreach ($entityActions as $actionCode) {
                $action = Action::where('code', $actionCode)->first();
                if ($action) {
                    Permission::updateOrCreate(
                        ['resource_id' => $resource->id, 'action_id' => $action->id],
                        [
                            'permission_key' => "{$resource->slug}.{$action->code}",
                            'description' => "Can {$action->name} {$resource->name}"
                        ]
                    );
                }
            }
        }

        // Clean up deleted entity resources
        $existingEntitySlugs = Resource::whereIn('module_id', [$masterModule->id, $transactionModule->id])->pluck('slug')->toArray();
        $toDelete = array_diff($existingEntitySlugs, $expectedSlugs);
        if (!empty($toDelete)) {
            Resource::whereIn('slug', $toDelete)->delete();
        }
    }
}