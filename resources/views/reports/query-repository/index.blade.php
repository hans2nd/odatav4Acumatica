@extends('layouts.app')

@section('title', 'Query Repository')
@section('page-title', 'Query Repository')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-h2 text-surface-900">Query Repository</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Manage and execute reusable read-only SQL queries.</p>
        </div>
        @if(auth()->user()->hasPermission('query_repository.create'))
        <a href="{{ route('query-repository.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-colors">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Add Query
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200 text-label-sm text-surface-500">
                        <th class="py-3 px-4 font-medium">Name / Category</th>
                        <th class="py-3 px-4 font-medium">Connection</th>
                        <th class="py-3 px-4 font-medium">Version</th>
                        <th class="py-3 px-4 font-medium">Status</th>
                        <th class="py-3 px-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 text-body-sm text-surface-700">
                    @forelse($queries as $query)
                    <tr class="hover:bg-surface-50/50 transition-colors group">
                        <td class="py-3 px-4">
                            <div class="font-medium text-surface-900">{{ $query->name }}</div>
                            <div class="text-surface-400 text-xs">{{ $query->category ?? 'Uncategorized' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            {{ $query->connection->name ?? 'Unknown' }}
                        </td>
                        <td class="py-3 px-4">
                            v{{ $query->version }}
                        </td>
                        <td class="py-3 px-4">
                            @if($query->is_active)
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Active</span>
                            @else
                                <span class="px-2 py-1 bg-surface-100 text-surface-700 rounded-full text-xs font-medium">Inactive</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if(auth()->user()->hasPermission('query_repository.view'))
                                <a href="{{ route('query-repository.show', $query->id) }}" class="p-2 text-surface-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors" title="View Logs">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                @endif
                                @if(auth()->user()->hasPermission('query_repository.update'))
                                <a href="{{ route('query-repository.edit', $query->id) }}" class="p-2 text-surface-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                @endif
                                @if(auth()->user()->hasPermission('query_repository.delete'))
                                <form action="{{ route('query-repository.destroy', $query->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this query?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-surface-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-surface-500">
                            No queries found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
