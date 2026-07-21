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
            return $this->promptToken();
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return $this->formatResponse('Migrations Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Migrations', $e->getMessage(), true);
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
            return $this->promptToken();
        }

        try {
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
            $output = Artisan::output();
            return $this->formatResponse('Migrate Fresh Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Migrate Fresh', $e->getMessage(), true);
        }
    }

    /**
     * Clear application cache.
     */
    public function optimize(Request $request)
    {
        $token = $request->query('token');
        if ($token !== 'deploy123') {
            return $this->promptToken();
        }

        try {
            Artisan::call('optimize:clear');
            $output = Artisan::output();
            return $this->formatResponse('Optimize Clear Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Optimize Clear', $e->getMessage(), true);
        }
    }

    private function promptToken()
    {
        return response('
            <!DOCTYPE html>
            <html>
            <head><title>Authentication Required</title></head>
            <body>
                <script>
                    let token = prompt("Enter security token:");
                    if (token) {
                        window.location.href = window.location.pathname + "?token=" + encodeURIComponent(token);
                    } else {
                        document.body.innerHTML = "<h3>Unauthorized</h3><p>A valid token is required.</p>";
                    }
                </script>
            </body>
            </html>
        ', 401);
    }

    private function formatResponse($title, $output, $isError = false)
    {
        $color = $isError ? 'text-red-600' : 'text-green-600';
        $bg = $isError ? 'bg-red-50' : 'bg-green-50';
        
        return response('
            <!DOCTYPE html>
            <html>
            <head>
                <title>' . $title . '</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-gray-100 p-8 font-sans">
                <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6 ' . $bg . ' border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold ' . $color . '">' . $title . '</h2>
                        <a href="/" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition font-semibold text-sm">
                            &larr; Back to Dashboard
                        </a>
                    </div>
                    <div class="p-6">
                        <h3 class="text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wider">Execution Log</h3>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono whitespace-pre-wrap">' . htmlspecialchars($output) . '</pre>
                    </div>
                </div>
            </body>
            </html>
        ');
    }
}
