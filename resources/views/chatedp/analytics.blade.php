@extends('layouts.app')

@section('title', 'ChatEDP Analytics & Telemetry')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">ChatEDP Analytics</h1>
            <p class="text-slate-500 text-sm">Execution telemetry, Query Library reuse, and compiler performance</p>
        </div>
        <!-- Simple Time Filter -->
        <div class="flex items-center space-x-2">
            <span class="text-sm text-slate-500 font-medium">Time Period:</span>
            <a href="{{ route('settings.chatedp.analytics', ['time' => 'all']) }}" class="px-3 py-1.5 text-xs font-medium rounded {{ $timeFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50' }}">All Time</a>
            <a href="{{ route('settings.chatedp.analytics', ['time' => '30days']) }}" class="px-3 py-1.5 text-xs font-medium rounded {{ $timeFilter === '30days' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50' }}">Last 30 Days</a>
            <a href="{{ route('settings.chatedp.analytics', ['time' => '7days']) }}" class="px-3 py-1.5 text-xs font-medium rounded {{ $timeFilter === '7days' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50' }}">Last 7 Days</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Requests -->
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Total ChatEDP Requests</div>
            <div class="text-3xl font-bold text-slate-800">{{ number_format($totalRequests) }}</div>
            <div class="text-xs text-slate-500 mt-2">Executed via ChatEDP interface</div>
        </div>

        <!-- Compiler Executions Avoided -->
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-indigo-500 uppercase tracking-wider mb-1">Compiler Executions Avoided</div>
            <div class="text-3xl font-bold text-indigo-600">{{ number_format($compilerBypassCount) }}</div>
            <div class="text-xs text-indigo-500 mt-2">Bypass Rate: <span class="font-bold">{{ $compilerBypassRate }}%</span></div>
        </div>

        <!-- Library Hits -->
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-1">Library HITs</div>
            <div class="text-3xl font-bold text-emerald-600">{{ number_format($libraryHitCount) }}</div>
            <div class="text-xs text-slate-500 mt-2">Avg duration: {{ $avgDurationLibrary }} ms</div>
        </div>

        <!-- Compiler Executions -->
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1">Compiler Executions</div>
            <div class="text-3xl font-bold text-amber-600">{{ number_format($compilerCount) }}</div>
            <div class="text-xs text-slate-500 mt-2">Avg duration: {{ $avgDurationCompiler }} ms</div>
        </div>
    </div>

    <!-- Execution Breakdown & Duration -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Execution Sources & Performance</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded border border-slate-100 bg-slate-50">
                <div class="text-sm font-medium text-slate-700">Library HIT (`library_hit`)</div>
                <div class="text-xl font-bold text-emerald-600 mt-1">{{ number_format($libraryHitCount) }}</div>
                <div class="text-xs text-slate-500 mt-1">Avg Execution: {{ $avgDurationLibrary }} ms</div>
            </div>
            <div class="p-4 rounded border border-slate-100 bg-slate-50">
                <div class="text-sm font-medium text-slate-700">Repository Match (`repository_match`)</div>
                <div class="text-xl font-bold text-indigo-600 mt-1">{{ number_format($repositoryReuseCount) }}</div>
                <div class="text-xs text-slate-500 mt-1">Avg Execution: {{ $avgDurationRepository }} ms</div>
            </div>
            <div class="p-4 rounded border border-slate-100 bg-slate-50">
                <div class="text-sm font-medium text-slate-700">Compiler (`compiler`)</div>
                <div class="text-xl font-bold text-amber-600 mt-1">{{ number_format($compilerCount) }}</div>
                <div class="text-xs text-slate-500 mt-1">Avg Execution: {{ $avgDurationCompiler }} ms</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Top Library Entries -->
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Top Query Library Entries</h2>
            @if($topEntries->isEmpty())
                <p class="text-sm text-slate-500">No query library hits recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                            <tr>
                                <th class="p-2">Repository Query</th>
                                <th class="p-2 text-right">Hit Count</th>
                                <th class="p-2 text-right">Last Hit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($topEntries as $entry)
                                <tr>
                                    <td class="p-2 font-medium text-slate-800">
                                        {{ $entry->queryRepository ? $entry->queryRepository->name : 'Query #' . $entry->query_repository_id }}
                                    </td>
                                    <td class="p-2 text-right font-bold text-indigo-600">{{ number_format($entry->hit_count) }}</td>
                                    <td class="p-2 text-right text-xs text-slate-500">
                                        {{ $entry->last_hit_at ? $entry->last_hit_at->diffForHumans() : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Recent Library Hits -->
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Recent Query Library Activity</h2>
            @if($recentHits->isEmpty())
                <p class="text-sm text-slate-500">No recent activity recorded.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                            <tr>
                                <th class="p-2">Repository Query</th>
                                <th class="p-2 text-right">Status</th>
                                <th class="p-2 text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentHits as $entry)
                                <tr>
                                    <td class="p-2 font-medium text-slate-800">
                                        {{ $entry->queryRepository ? $entry->queryRepository->name : 'Query #' . $entry->query_repository_id }}
                                    </td>
                                    <td class="p-2 text-right">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">Active</span>
                                    </td>
                                    <td class="p-2 text-right text-xs text-slate-500">
                                        {{ $entry->last_hit_at ? $entry->last_hit_at->diffForHumans() : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Stale / Invalid Entries -->
    @if(!$staleEntries->isEmpty())
    <div class="bg-amber-50 rounded-lg border border-amber-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-amber-900 mb-2">Invalid / Stale Library Entries</h2>
        <p class="text-xs text-amber-700 mb-4">These library entries link to updated or disabled repository definitions and are automatically bypassed during routing.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-amber-100 text-amber-900 uppercase text-xs font-semibold">
                    <tr>
                        <th class="p-2">Library ID</th>
                        <th class="p-2">Repository Query</th>
                        <th class="p-2 text-right">Library Version</th>
                        <th class="p-2 text-right">Repo Version</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-200">
                    @foreach($staleEntries as $stale)
                        <tr>
                            <td class="p-2 font-mono text-xs">#{{ $stale->id }}</td>
                            <td class="p-2 font-medium text-amber-900">
                                {{ $stale->queryRepository ? $stale->queryRepository->name : 'Deleted Repository' }}
                            </td>
                            <td class="p-2 text-right font-mono text-xs">{{ $stale->repository_version }}</td>
                            <td class="p-2 text-right font-mono text-xs font-bold text-red-600">
                                {{ $stale->queryRepository ? $stale->queryRepository->version : 'N/A' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
