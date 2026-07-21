<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemUtilityController extends Controller
{
    /**
     * Run basic database migrations.
     */
    public function migrate(Request $request)
    {
        // Basic security token to prevent unauthorized execution
        $token = $request->query('token');
        if ($token !== 'deploy123') {
            return response('Unauthorized', 401);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return "<pre>Migrations executed successfully:\n" . $output . "</pre>";
        } catch (\Exception $e) {
            return "<pre>Error running migrations:\n" . $e->getMessage() . "</pre>";
        }
    }

    /**
     * Run fresh database migrations with seeders.
     */
    public function migrateFresh(Request $request)
    {
        // Basic security token to prevent unauthorized execution
        $token = $request->query('token');
        if ($token !== 'deploy123') {
            return response('Unauthorized', 401);
        }

        try {
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
            $output = Artisan::output();
            return "<pre>Migrate Fresh executed successfully:\n" . $output . "</pre>";
        } catch (\Exception $e) {
            return "<pre>Error running migrate:fresh:\n" . $e->getMessage() . "</pre>";
        }
    }

    /**
     * Clear application cache.
     */
    public function optimize(Request $request)
    {
        $token = $request->query('token');
        if ($token !== 'deploy123') {
            return response('Unauthorized', 401);
        }

        try {
            Artisan::call('optimize:clear');
            $output = Artisan::output();
            return "<pre>Optimize Clear executed successfully:\n" . $output . "</pre>";
        } catch (\Exception $e) {
            return "<pre>Error running optimize:clear:\n" . $e->getMessage() . "</pre>";
        }
    }
}
