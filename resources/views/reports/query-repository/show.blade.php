@extends('layouts.app')

@section('title', 'Query Details: ' . $query->name)
@section('page-title', 'Query Details: ' . $query->name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="{{ route('query-repository.index') }}" class="text-brand-600 hover:text-brand-700 flex items-center gap-1 text-sm font-medium mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Queries
            </a>
            <h2 class="text-h2 text-surface-900">{{ $query->name }}</h2>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->hasPermission('query_repository.update'))
            <a href="{{ route('query-repository.edit', $query->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-surface-200 text-surface-700 rounded-xl hover:bg-surface-50 transition-colors">
                <span class="material-symbols-outlined text-[20px]">edit</span>
                Edit Query
            </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-6">
                <h3 class="text-lg font-medium text-surface-900 mb-4">Query Details</h3>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-1">Category</span>
                        <span class="text-surface-900">{{ $query->category ?? 'None' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-1">Connection</span>
                        <span class="text-surface-900">{{ $query->connection->name ?? 'Unknown' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-1">Version</span>
                        <span class="text-surface-900">v{{ $query->version }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-1">Status</span>
                        @if($query->is_active)
                            <span class="text-green-600 font-medium">Active</span>
                        @else
                            <span class="text-surface-500">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="mb-6">
                    <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-1">Description</span>
                    <p class="text-surface-700 text-sm">{{ $query->description ?? 'No description provided.' }}</p>
                </div>

                <div>
                    <span class="block text-xs font-medium text-surface-500 uppercase tracking-wider mb-2">SQL Text</span>
                    <div class="bg-surface-900 text-surface-50 p-4 rounded-xl overflow-x-auto text-sm font-mono whitespace-pre-wrap">{{ $query->sql_text }}</div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-6">
                <h3 class="text-lg font-medium text-surface-900 mb-4">Detected Parameters</h3>
                @if(is_array($query->parameter_json) && count($query->parameter_json) > 0)
                <ul class="space-y-2">
                    @foreach($query->parameter_json as $param)
                    <li class="flex items-center gap-2 p-2 bg-surface-50 rounded-lg">
                        <span class="font-mono text-sm font-medium">{{ $param['name'] }}</span>
                        <span class="text-xs text-surface-500 bg-surface-200 px-2 py-0.5 rounded">{{ $param['type'] }}</span>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-sm text-surface-500">No parameters detected in this query.</p>
                @endif
            </div>
        </div>

        <div>
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-surface-200">
                    <h3 class="text-lg font-medium text-surface-900">Execution Logs</h3>
                </div>
                <div class="max-h-[600px] overflow-y-auto">
                    @forelse($query->logs()->latest()->take(50)->get() as $log)
                    <div class="p-4 border-b border-surface-100 last:border-b-0 hover:bg-surface-50 transition-colors">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium {{ $log->success ? 'text-green-700' : 'text-red-700' }}">
                                {{ $log->success ? 'Success' : 'Failed' }}
                            </span>
                            <span class="text-xs text-surface-400" title="{{ $log->executed_at }}">
                                {{ $log->executed_at->diffForHumans() }}
                            </span>
                        </div>
                        <div class="text-xs text-surface-600 mb-1">
                            By {{ $log->user->name ?? 'Unknown' }} &bull; {{ number_format($log->duration, 3) }}s
                        </div>
                        @if(!$log->success && $log->error_message)
                        <div class="text-xs text-red-600 bg-red-50 p-2 rounded mt-2 font-mono whitespace-pre-wrap">
                            {{ Str::limit($log->error_message, 150) }}
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="p-6 text-center text-surface-500 text-sm">
                        No execution logs yet.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
