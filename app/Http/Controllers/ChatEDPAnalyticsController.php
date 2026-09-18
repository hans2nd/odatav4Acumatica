<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AiAuditLog;
use App\Models\AiQueryLibrary;
use App\Models\QueryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChatEDPAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $timeFilter = $request->get('time', 'all'); // 'all', '7days', '30days'
        
        $query = AiAuditLog::query();
        $libraryQuery = AiQueryLibrary::query();
        
        if ($timeFilter === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
            $libraryQuery->where('last_hit_at', '>=', Carbon::now()->subDays(7));
        } elseif ($timeFilter === '30days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
            $libraryQuery->where('last_hit_at', '>=', Carbon::now()->subDays(30));
        }

        // Metrics
        $totalRequests = (clone $query)->count();
        
        // Use JSON extraction for _execution_source if possible, or string match
        // Note: SQLite JSON_EXTRACT uses different syntax than MySQL. For robustness in simple queries without raw DB specific SQL, we might fetch and filter if row count is low, but instructions say "Use database aggregation for COUNT, AVG, GROUP BY. Do NOT load all audit rows into PHP".
        // Let's use standard MySQL JSON extract (since this app is likely MySQL/Postgres)
        // If query_plan_json->_execution_source is not indexed, it scans.
        
        $sourceStats = (clone $query)->select([
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(query_plan_json, '$._execution_source')) as source"),
            DB::raw("COUNT(*) as count"),
            DB::raw("AVG(duration_ms) as avg_duration")
        ])->groupBy('source')->get();

        $libraryHitCount = 0;
        $repositoryReuseCount = 0;
        $compilerCount = 0;
        $avgDurationLibrary = 0;
        $avgDurationRepository = 0;
        $avgDurationCompiler = 0;

        foreach ($sourceStats as $stat) {
            $source = $stat->source ?? 'compiler';
            if ($source === 'library_hit') {
                $libraryHitCount = $stat->count;
                $avgDurationLibrary = round($stat->avg_duration, 2);
            } elseif ($source === 'repository_match') {
                $repositoryReuseCount = $stat->count;
                $avgDurationRepository = round($stat->avg_duration, 2);
            } else {
                $compilerCount += $stat->count; // null or 'compiler'
                $avgDurationCompiler = round($stat->avg_duration, 2); // Approximation if multiple non-hits exist
            }
        }

        $compilerBypassCount = $libraryHitCount + $repositoryReuseCount;
        $compilerBypassRate = $totalRequests > 0 ? round(($compilerBypassCount / $totalRequests) * 100, 1) : 0;

        // Top library entries
        $topEntries = (clone $libraryQuery)->with('queryRepository')->orderByDesc('hit_count')->take(10)->get();
        
        // Recent library hits
        $recentHits = (clone $libraryQuery)->with('queryRepository')->orderByDesc('last_hit_at')->take(10)->get();

        // Invalid/stale entries
        $staleEntries = AiQueryLibrary::whereHas('queryRepository', function($q) {
            $q->whereColumn('query_repositories.version', '!=', 'ai_query_libraries.repository_version')
              ->orWhere('query_repositories.is_active', false);
        })->with('queryRepository')->take(50)->get();

        return view('chatedp.analytics', compact(
            'timeFilter',
            'totalRequests',
            'libraryHitCount',
            'repositoryReuseCount',
            'compilerCount',
            'compilerBypassCount',
            'compilerBypassRate',
            'avgDurationLibrary',
            'avgDurationRepository',
            'avgDurationCompiler',
            'topEntries',
            'recentHits',
            'staleEntries'
        ));
    }
}
