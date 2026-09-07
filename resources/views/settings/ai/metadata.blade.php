@extends('layouts.app')

@section('title', 'AI Settings - ChatEDP Table Configuration')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto" x-data="metadataManager()">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-500" style="font-size:28px">database</span>
            ChatEDP Table Configuration
        </h1>
        <p class="text-slate-500 text-sm mt-1">Configure which tables are available for ChatEDP analysis and define their business meaning</p>
    </div>

    @if (session('success'))
    <div class="mb-4 p-4 bg-emerald-100 border border-emerald-300 text-emerald-800 rounded flex items-center gap-2">
        <span class="material-symbols-outlined text-emerald-600" style="font-size:18px">check_circle</span>
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded flex items-center gap-2">
        <span class="material-symbols-outlined text-red-600" style="font-size:18px">error</span>
        {{ session('error') }}
    </div>
    @endif

    {{-- Connection & Refresh --}}
    <div class="bg-white p-4 shadow-sm rounded-lg border border-slate-200 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="text-sm text-slate-500">Analytics Connection:</span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-700">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                {{ $connectionName }}
            </span>
            @if(count($systemTables ?? []) > 0)
                <span class="text-xs text-slate-400">{{ count($systemTables) }} system table(s) hidden</span>
            @endif
        </div>
        <form action="{{ route('settings.ai.metadata.refresh') }}" method="POST">
            @csrf
            <button type="submit" class="btn bg-indigo-500 hover:bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium transition flex items-center gap-1.5 shadow-sm" {{ $connectionId ? '' : 'disabled' }}>
                <span class="material-symbols-outlined text-sm">sync</span>
                Refresh Schema
            </button>
        </form>
    </div>

    {{-- Filters --}}
    <div class="bg-white p-4 shadow-sm rounded-lg border border-slate-200 mb-6">
        <form method="GET" action="{{ route('settings.ai.metadata') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Table name, logical name, description..." class="form-input w-full bg-slate-50 border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Data Role</label>
                <select name="role" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All Roles</option>
                    @foreach($dataRoles as $key => $label)
                        <option value="{{ $key }}" {{ ($roleFilter ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[130px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">ChatEDP Status</label>
                <select name="status" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All</option>
                    <option value="enabled" {{ ($statusFilter ?? '') === 'enabled' ? 'selected' : '' }}>Enabled</option>
                    <option value="disabled" {{ ($statusFilter ?? '') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pb-1">
                <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" name="show_system" value="1" {{ ($showSystem ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-500 focus:ring-indigo-500">
                    <span class="text-xs">System Tables</span>
                </label>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn bg-slate-100 hover:bg-slate-200 text-slate-700 rounded px-4 py-2 text-sm font-medium transition flex items-center gap-1.5 border border-slate-300">
                    <span class="material-symbols-outlined text-sm">filter_list</span>
                    Filter
                </button>
                <a href="{{ route('settings.ai.metadata') }}" class="text-xs text-slate-400 hover:text-slate-600 transition">Clear</a>
            </div>
        </form>
    </div>

    {{-- Table List --}}
    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        @if(count($tables) === 0)
            <div class="text-center py-16">
                <span class="material-symbols-outlined text-slate-300" style="font-size:48px">table_chart</span>
                <p class="mt-3 text-sm text-slate-400">No tables found. Try refreshing the schema or adjusting filters.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Physical Table</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Logical Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Data Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ChatEDP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($tables as $table)
                    @php
                        $role = $table->data_role ?? 'other';
                        $roleBadgeClasses = [
                            'sales_transaction' => 'bg-blue-100 text-blue-700',
                            'customer_master' => 'bg-emerald-100 text-emerald-700',
                            'product_master' => 'bg-amber-100 text-amber-700',
                            'inventory' => 'bg-violet-100 text-violet-700',
                            'reference' => 'bg-purple-100 text-purple-700',
                            'other' => 'bg-slate-100 text-slate-500',
                            'ignore' => 'bg-red-100 text-red-600',
                        ];
                        $badgeClass = $roleBadgeClasses[$role] ?? $roleBadgeClasses['other'];
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <code class="text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono">{{ $table->entity_key }}</code>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $table->logical_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $dataRoles[$role] ?? 'Other' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($table->chatedp_enabled)
                                <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Enabled
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-slate-400 text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                    Disabled
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs max-w-[200px] truncate" title="{{ $table->description }}">
                            {{ Str::limit($table->description, 60) ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" @click="openConfigure({{ $table->id }})" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded transition">
                                <span class="material-symbols-outlined" style="font-size:14px">settings</span>
                                Configure
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Configure Slide-over Panel --}}
    <div x-show="showPanel" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-hidden" style="display:none" @keydown.escape.window="showPanel = false">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showPanel = false"></div>

        {{-- Panel --}}
        <div class="absolute inset-y-0 right-0 max-w-xl w-full" x-show="showPanel" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <div class="h-full bg-white shadow-2xl flex flex-col">

                {{-- Panel Header --}}
                <div class="px-6 py-4 bg-gradient-to-r from-indigo-500 to-violet-500 flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="material-symbols-outlined" style="font-size:20px">tune</span>
                            Configure Table
                        </h2>
                        <p class="text-indigo-200 text-xs mt-0.5" x-text="detail.table?.entity_key"></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="button" @click="suggestMetadata" class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-white rounded shadow-sm transition flex items-center gap-1.5">
                            <span class="material-symbols-outlined" style="font-size:16px">model_training</span>
                            Suggest Semantics
                        </button>
                        <button @click="showPanel = false" class="text-white/70 hover:text-white transition">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                {{-- Panel Body --}}
                <div class="flex-1 overflow-y-auto">
                    {{-- Loading --}}
                    <div x-show="loading || loadingAi" class="flex items-center justify-center py-20">
                        <div class="text-center">
                            <span class="material-symbols-outlined animate-spin text-indigo-400" style="font-size:32px">sync</span>
                            <p class="mt-2 text-sm text-slate-400" x-text="loadingAi ? 'Generating Suggestions...' : 'Loading configuration...'"></p>
                        </div>
                    </div>

                    {{-- Suggestion Review Panel --}}
                    <div x-show="!loading && !loadingAi && aiSuggestions" class="bg-indigo-50 border-b border-indigo-100 p-6" style="display: none;">
                        <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-1.5 mb-4">
                            <span class="material-symbols-outlined text-indigo-500" style="font-size:18px">psychology</span>
                            Suggested Semantics
                        </h3>
                        <div class="space-y-4">
                            <!-- metrics -->
                            <template x-if="aiSuggestions?.metrics?.length > 0">
                                <div>
                                    <label class="block text-[10px] font-medium text-indigo-500 uppercase tracking-wider mb-2">Suggested Metrics</label>
                                    <div class="space-y-2">
                                        <template x-for="(m, idx) in aiSuggestions.metrics" :key="idx">
                                            <div class="flex items-start gap-3 bg-white p-2.5 rounded border border-indigo-100 shadow-sm">
                                                <input type="checkbox" x-model="aiCheckboxes.metrics[idx]" class="mt-1 rounded text-indigo-500">
                                                <div class="flex-1 text-sm">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-slate-800" x-text="m.name"></span>
                                                        <span class="text-xs text-slate-400 font-mono" x-text="'(' + (m.expression || (m.aggregation + '(' + m.source_column + ')')) + ')'"></span>
                                                        <span x-show="m.confidence" :class="m.confidence === 'high' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase" x-text="m.confidence"></span>
                                                    </div>
                                                    <div class="text-xs text-slate-500 mt-0.5">
                                                        <span x-show="m.reason" class="text-slate-400" x-text="'Reason: ' + m.reason + ' | '"></span>
                                                        Default: <span x-text="m.is_default ? 'Yes' : 'No'"></span>
                                                        <span x-show="m.aliases && m.aliases.length"> | Aliases: <span x-text="(m.aliases || []).join(', ')"></span></span>
                                                    </div>
                                                </div>
                                                <button type="button" @click="aiSuggestions.metrics.splice(idx, 1); aiCheckboxes.metrics.splice(idx, 1)" class="text-slate-400 hover:text-red-500">
                                                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <!-- dimensions -->
                            <template x-if="aiSuggestions?.dimensions?.length > 0">
                                <div>
                                    <label class="block text-[10px] font-medium text-indigo-500 uppercase tracking-wider mb-2">Suggested Dimensions</label>
                                    <div class="space-y-2">
                                        <template x-for="(d, idx) in aiSuggestions.dimensions" :key="idx">
                                            <div class="flex items-start gap-3 bg-white p-2.5 rounded border border-indigo-100 shadow-sm">
                                                <input type="checkbox" x-model="aiCheckboxes.dimensions[idx]" class="mt-1 rounded text-indigo-500">
                                                <div class="flex-1 text-sm">
                                                    <div class="font-medium text-slate-800" x-text="d.name + ' → ' + d.source_column"></div>
                                                    <div class="text-xs text-slate-500 mt-0.5">
                                                        Aliases: <span x-text="(d.aliases || []).join(', ')"></span>
                                                    </div>
                                                </div>
                                                <button type="button" @click="aiSuggestions.dimensions.splice(idx, 1); aiCheckboxes.dimensions.splice(idx, 1)" class="text-slate-400 hover:text-red-500">
                                                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="pt-3 flex gap-2">
                                <button type="button" @click="applySuggestions" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm font-medium transition shadow-sm">
                                    Apply Selected Suggestions
                                </button>
                                <button type="button" @click="aiSuggestions = null" class="px-4 py-2 bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 rounded text-sm font-medium transition">
                                    Discard
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Content --}}
                    <form :action="formAction" method="POST" x-show="!loading && !loadingAi" class="divide-y divide-slate-200">
                        @csrf

                        {{-- Physical Table (read-only) --}}
                        <div class="px-6 py-4">
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">Physical Table</label>
                            <div class="bg-slate-50 border border-slate-200 rounded px-3 py-2">
                                <code class="text-sm text-slate-700 font-mono" x-text="detail.table?.entity_key"></code>
                            </div>
                        </div>

                        {{-- Basic Information --}}
                        <div class="px-6 py-5">
                            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-1.5 mb-4">
                                <span class="material-symbols-outlined text-indigo-500" style="font-size:18px">edit_note</span>
                                Basic Information
                            </h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Logical Name</label>
                                    <input type="text" name="logical_name" :value="detail.table?.logical_name" :placeholder="detail.table?.entity_key" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Description</label>
                                    <input type="text" name="description" :value="detail.table?.description" placeholder="Business description..." class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Aliases <span class="text-slate-400">(one per line)</span></label>
                                    <textarea name="aliases" rows="3" placeholder="data mentah penjualan&#10;raw sales&#10;penjualan mentah" class="form-textarea w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" x-text="(detail.aliases || []).join('\n')"></textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Business Meaning --}}
                        <div class="px-6 py-5">
                            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-1.5 mb-4">
                                <span class="material-symbols-outlined text-emerald-500" style="font-size:18px">category</span>
                                Business Meaning
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Data Role</label>
                                    <select name="data_role" x-model="selectedRole" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        @foreach($dataRoles as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Business Domains <span class="text-[10px] text-slate-400 font-normal">(Leave empty for Global)</span></label>
                                    <select name="business_domains[]" x-model="detail.business_domains" multiple class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" style="min-height: 80px;">
                                        <option value="F&B">F&B</option>
                                        <option value="Retail">Retail</option>
                                        <option value="Supply Chain">Supply Chain</option>
                                        <option value="Logistics">Logistics</option>
                                        <option value="Warehouse">Warehouse</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Finance">Finance</option>
                                    </select>
                                </div>
                                <div class="flex items-end pb-1">
                                    <label class="relative inline-flex items-center cursor-pointer select-none">
                                        <input type="checkbox" name="chatedp_enabled" value="1" x-model="chatedpEnabled" class="sr-only peer">
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                        <span class="ml-2.5 text-sm font-medium text-slate-700">Enable ChatEDP</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Metrics & Dimensions Configuration (available for all roles) --}}
                            <div class="px-6 py-5 bg-blue-50/50">
                                <h3 class="text-sm font-semibold text-blue-800 flex items-center gap-1.5 mb-1">
                                    <span class="material-symbols-outlined text-blue-500" style="font-size:18px">trending_up</span>
                                    Metrics
                                </h3>
                                <p class="text-xs text-slate-500 mb-4">Define business metric definitions for ChatEDP analysis (e.g., "Revenue" → SUM(NetAmount))</p>

                                <div class="space-y-3 mb-3">
                                    <template x-for="(metric, idx) in metrics" :key="idx">
                                        <div class="p-3 border border-slate-200 rounded-lg bg-white relative">
                                            <div class="flex items-center justify-between mb-2 pb-2 border-b border-slate-100">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" name="metric_default" :value="idx" :checked="metric.metadata?.is_default" class="form-radio text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                                    <span class="text-xs font-semibold text-slate-700">Set as Default Metric</span>
                                                </label>
                                                <button type="button" @click="metrics.splice(idx, 1)" class="p-1 text-red-400 hover:text-red-600 transition shrink-0" title="Remove Metric">
                                                    <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Semantic Name</label>
                                                    <input type="text" :name="'metric_names['+idx+']'" :value="metric.name" placeholder="e.g., Revenue" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Source Column</label>
                                                    <select :name="'metric_columns['+idx+']'" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                        <option value="">Select column...</option>
                                                        <template x-for="col in detail.columns" :key="col">
                                                            <option :value="col" :selected="col === metric.source_column" x-text="col"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Aliases (comma-separated)</label>
                                                    <input type="text" :name="'metric_aliases['+idx+']'" :value="metric.metadata?.aliases?.join(', ')" placeholder="e.g., net, netto, sales" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Aggregation</label>
                                                    <select :name="'metric_aggregations['+idx+']'" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                        <option value="SUM" :selected="metric.metadata?.aggregation === 'SUM'">SUM</option>
                                                        <option value="COUNT" :selected="metric.metadata?.aggregation === 'COUNT'">COUNT</option>
                                                        <option value="AVG" :selected="metric.metadata?.aggregation === 'AVG'">AVG</option>
                                                        <option value="MIN" :selected="metric.metadata?.aggregation === 'MIN'">MIN</option>
                                                        <option value="MAX" :selected="metric.metadata?.aggregation === 'MAX'">MAX</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Expression <span class="text-slate-400 normal-case">(optional, for calculated metrics)</span></label>
                                                <input type="text" :name="'metric_expressions['+idx+']'" :value="metric.expression" placeholder="e.g., SUM(NetAmount - CostAmount)" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm font-mono focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Description <span class="text-slate-400 normal-case">(optional)</span></label>
                                                <input type="text" :name="'metric_descriptions['+idx+']'" :value="metric.description" placeholder="Brief description of this metric" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="metrics.push({name:'', source_column:'', expression:'', description:'', metadata: {aliases: [], aggregation: 'SUM', is_default: false}})" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                                    <span class="material-symbols-outlined" style="font-size:16px">add_circle</span>
                                    Add Metric
                                </button>

                                <h3 class="text-sm font-semibold text-blue-800 flex items-center gap-1.5 mt-6 mb-1">
                                    <span class="material-symbols-outlined text-blue-500" style="font-size:18px">view_column</span>
                                    Dimensions
                                </h3>
                                <p class="text-xs text-slate-500 mb-4">Define semantic names for grouping dimensions (e.g., "Customer" → CustomerName)</p>

                                <div class="space-y-3 mb-3">
                                    <template x-for="(dim, idx) in dimensions" :key="idx">
                                        <div class="p-3 border border-slate-200 rounded-lg bg-white relative">
                                            <div class="flex items-center justify-end mb-2 pb-2 border-b border-slate-100">
                                                <button type="button" @click="dimensions.splice(idx, 1)" class="p-1 text-red-400 hover:text-red-600 transition shrink-0" title="Remove Dimension">
                                                    <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Semantic Name</label>
                                                    <input type="text" :name="'dimension_names['+idx+']'" :value="dim.name" placeholder="e.g., Customer" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Source Column</label>
                                                    <select :name="'dimension_columns['+idx+']'" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                        <option value="">Select column...</option>
                                                        <template x-for="col in detail.columns" :key="col">
                                                            <option :value="col" :selected="col === dim.source_column" x-text="col"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Aliases (comma-separated)</label>
                                                <input type="text" :name="'dimension_aliases['+idx+']'" :value="dim.metadata?.aliases?.join(', ')" placeholder="e.g., customer name, client" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-1.5 px-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="dimensions.push({name:'', source_column:'', metadata: {aliases: []}})" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                                    <span class="material-symbols-outlined" style="font-size:16px">add_circle</span>
                                    Add Dimension
                                </button>
                            </div>

                        {{-- Footer --}}
                        <div class="px-6 py-4 bg-slate-50 flex items-center justify-end gap-3 shrink-0">
                            <button type="button" @click="showPanel = false" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded hover:bg-slate-50 transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-500 hover:bg-indigo-600 rounded transition flex items-center gap-1.5 shadow-sm">
                                <span class="material-symbols-outlined" style="font-size:16px">save</span>
                                Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function metadataManager() {
    return {
        showPanel: false,
        loading: false,
        loadingAi: false,
        aiSuggestions: null,
        aiCheckboxes: {
            logical_name: true,
            description: true,
            aliases: true,
            data_role: true,
            metrics: [],
            dimensions: []
        },
        detail: { table: {}, columns: [], aliases: [], business_domains: [], metrics: [], dimensions: [] },
        metrics: [],
        dimensions: [],
        chatedpEnabled: false,
        selectedRole: 'other',
        selectedTableId: null,
        formAction: '',

        async openConfigure(id) {
            this.loading = true;
            this.showPanel = true;
            this.selectedTableId = id;
            this.formAction = '/settings/ai/metadata/' + id;
            this.aiSuggestions = null;

            try {
                const res = await fetch('/settings/ai/metadata/' + id + '/detail');
                const data = await res.json();
                this.detail = data;
                this.selectedRole = data.table?.data_role || 'other';
                this.chatedpEnabled = Boolean(data.table?.chatedp_enabled == 1 || data.table?.chatedp_enabled === true);
                this.metrics = data.metrics?.length ? data.metrics.map(m => ({...m})) : [];
                this.dimensions = data.dimensions?.length ? data.dimensions.map(d => ({...d})) : [];
            } catch (e) {
                console.error('Failed to load table detail', e);
            }
            this.loading = false;
        },
        async suggestMetadata() {
            const tableId = this.selectedTableId || this.detail.table?.id;
            if (!tableId) {
                alert('No table selected.');
                return;
            }

            this.loadingAi = true;
            this.aiSuggestions = null;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                    || document.querySelector('input[name="_token"]')?.value;

                const res = await fetch(`/settings/ai/metadata/${tableId}/suggest`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken 
                    }
                });
                const data = await res.json();
                if(data.error) throw new Error(data.error);
                
                this.aiSuggestions = data;
                this.aiCheckboxes = {
                    metrics: (data.metrics || []).map(() => true),
                    dimensions: (data.dimensions || []).map(() => true)
                };
            } catch (e) {
                alert('Failed to get suggestions: ' + e.message);
            }
            this.loadingAi = false;
        },
        applySuggestions() {
            if (!this.aiSuggestions) return;
            
            if (this.aiSuggestions.metrics) {
                this.aiSuggestions.metrics.forEach((m, idx) => {
                    if (this.aiCheckboxes.metrics[idx]) {
                        this.metrics.push({
                            name: m.name,
                            source_column: m.source_column,
                            expression: m.expression || '',
                            description: m.description || '',
                            metadata: {
                                aggregation: m.aggregation || 'SUM',
                                aliases: m.aliases || [],
                                is_default: m.is_default || false
                            }
                        });
                    }
                });
            }
            if (this.aiSuggestions.dimensions) {
                this.aiSuggestions.dimensions.forEach((d, idx) => {
                    if (this.aiCheckboxes.dimensions[idx]) {
                        this.dimensions.push({
                            name: d.name,
                            source_column: d.source_column,
                            metadata: {
                                aliases: d.aliases || []
                            }
                        });
                    }
                });
            }
            
            this.aiSuggestions = null;
        }
    };
}
</script>
@endsection
