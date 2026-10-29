<?php

use App\Models\Menu;
use App\Services\MenuService;
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
        if (!Schema::hasTable('security_access_logs')) {
            Schema::create('security_access_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('event_type', 50)->index(); // api_access, api_unauthorized, login_success, login_failed, session_revoked
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_identifier', 191)->nullable();
                $table->string('endpoint', 255)->nullable();
                $table->string('method', 16)->default('GET');
                $table->smallInteger('status_code')->default(200);
                $table->boolean('is_anonymous')->default(false)->index();
                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->string('client_type', 60)->nullable();
                $table->string('details', 500)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        // Register Security & Access Monitor in Sidebar Menu under Settings
        if (Schema::hasTable('menus')) {
            $settingsParent = Menu::where('title', 'Settings')->whereNull('parent_id')->first();

            Menu::updateOrCreate(
                ['route_name' => 'security-monitor.index'],
                [
                    'parent_id'      => $settingsParent ? $settingsParent->id : null,
                    'title'          => 'Security & Access Monitor',
                    'icon'           => 'shield_lock',
                    'sort_order'     => 25,
                    'type'           => 'link',
                    'is_active'      => true,
                    'is_visible'     => true,
                    'permission_key' => 'users.view',
                ]
            );

            MenuService::clearMenuCache();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            Menu::where('route_name', 'security-monitor.index')->delete();
            MenuService::clearMenuCache();
        }

        Schema::dropIfExists('security_access_logs');
    }
};

