<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Services\ScheduleWorkerService;

class SystemUtilityController extends Controller
{
    /**
     * Run basic database migrations.
     */
    public function migrate(Request $request)
    {
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
        try {
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
            $output = Artisan::output();
            return $this->formatResponse('Migrate Fresh Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Migrate Fresh', $e->getMessage(), true);
        }
    }

    /**
     * Run fresh database migrations with the default installation seeder.
     */
    public function installDefault(Request $request)
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true, '--seeder' => 'Database\\Seeders\\DefaultInstallationSeeder']);
            $output = Artisan::output();
            return $this->formatResponse('Default Installation Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Default Installation', $e->getMessage(), true);
        }
    }

    /**
     * Clear application cache.
     */
    public function optimize(Request $request)
    {
        try {
            Artisan::call('optimize:clear');
            $output = Artisan::output();
            return $this->formatResponse('Optimize Clear Executed Successfully', $output);
        } catch (\Exception $e) {
            return $this->formatResponse('Error Running Optimize Clear', $e->getMessage(), true);
        }
    }

    /**
     * Clear Laravel Logs.
     */
    public function clearLogs(Request $request)
    {
        try {
            $logPath = storage_path('logs');
            $files = glob($logPath . '/*.log');
            $deleted = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    // Empty the file instead of deleting it to prevent permission issues
                    file_put_contents($file, '');
                    $deleted++;
                }
            }
            return $this->formatResponse('Logs Cleared Successfully', "Cleared contents of $deleted log file(s).");
        } catch (\Exception $e) {
            return $this->formatResponse('Error Clearing Logs', $e->getMessage(), true);
        }
    }

    /**
     * Run an arbitrary artisan command.
     * Note: Colons (:) in URLs can sometimes cause issues, so you can pass dashes (-) which will be converted to colons if the command exists.
     */
    public function runArtisan(Request $request, $command)
    {
        $allowedCommands = [
            'storage:link',
            'db:seed',
            'optimize:clear',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear'
        ];

        // Allow passing 'storage-link' instead of 'storage:link' in the URL
        $command = str_replace('-', ':', $command);

        if (!in_array($command, $allowedCommands)) {
            return $this->formatResponse("Unauthorized", "Execution of arbitrary commands is prohibited.", true);
        }

        try {
            if ($command === 'db:seed') {
                Artisan::call('db:seed', ['--force' => true]);
            } else {
                Artisan::call($command);
            }
            $output = Artisan::output();
            return $this->formatResponse("Artisan '$command' Executed Successfully", $output ?: "Command completed with no output.");
        } catch (\Exception $e) {
            return $this->formatResponse("Error Running Artisan '$command'", $e->getMessage(), true);
        }
    }

    public function runWebCron(Request $request)
    {
        try {
            // 1. Process Native Schedules
            Artisan::call('schedule:run');
            $scheduleOutput = Artisan::output();

            // 2. Process Queue for max 50 seconds to avoid HTTP timeout
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 50,
                '--memory' => 1024
            ]);
            
            $queueOutput = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Web Cron executed successfully',
                'schedule_output' => $scheduleOutput ?: 'No schedules due.',
                'queue_output' => $queueOutput ?: 'No jobs processed.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error executing Web Cron: ' . $e->getMessage()
            ], 500);
        }
    }


    private function formatResponse($title, $output, $isError = false)
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => !$isError,
                'title'   => $title,
                'message' => $output,
            ], $isError ? 500 : 200);
        }

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
