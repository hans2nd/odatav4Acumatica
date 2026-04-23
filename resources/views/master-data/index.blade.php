@extends('layouts.app')

@section('title', 'Master Data — Enterprise Sync Engine')
@section('page-title', 'Master Data')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
    @foreach($entities as $key => $info)
        <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex flex-col fade-in">
            <div class="flex items-center justify-between mb-md">
                <div class="flex items-center gap-sm">
                    <div class="p-2 bg-surface-container-low rounded">
                        <span class="material-symbols-outlined text-secondary">{{ $info['config']['icon'] ?? 'database' }}</span>
                    </div>
                    <div>
                        <h3 class="font-body-md text-body-md font-semibold text-on-surface">{{ $info['config']['description'] }}</h3>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">{{ $info['config']['odata_entity'] }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-end justify-between mt-auto">
                <div>
                    <span class="font-display text-[24px] font-bold text-on-surface leading-none">{{ number_format($info['records']) }}</span>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">records</p>
                </div>

                <div class="flex items-center gap-2">
                    @if($info['table_exists'])
                        <a href="{{ route('master-data.show', $key) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-surface-container-low text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors">
                            <span class="material-symbols-outlined text-sm">visibility</span> View
                        </a>
                    @endif
                    <button onclick="syncEntity('{{ $key }}', this)"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">sync</span> Sync
                    </button>
                </div>
            </div>

            @if(!$info['table_exists'])
                <div class="mt-sm px-2 py-1 bg-yellow-50 border border-yellow-200 rounded">
                    <span class="font-label-sm text-label-sm text-yellow-700">Table not created yet — run sync to create</span>
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
