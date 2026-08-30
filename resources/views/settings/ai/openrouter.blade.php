@extends('layouts.app')

@section('title', 'AI Settings - OpenRouter Configuration')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto" x-data="openRouterSettings()">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">AI & Analytics Configuration</h1>
        <p class="text-slate-500 text-sm">Configure the LLM provider and target database for ChatEDP</p>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 bg-emerald-100 border border-emerald-300 text-emerald-800 rounded">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-white p-6 shadow-sm rounded-lg border border-slate-200">
        <div class="mb-6 pb-4 border-b border-slate-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Status</h2>
                <div class="mt-1 flex items-center">
                    @if($isConfigured)
                        <span class="w-3 h-3 bg-emerald-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-emerald-600">Configured</span>
                    @else
                        <span class="w-3 h-3 bg-amber-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-amber-600">Not Configured</span>
                    @endif
                </div>
            </div>
            
            @if(auth()->user()->hasPermission('ai_settings.test'))
            <div>
                <button type="button" @click="testConnection" class="btn bg-indigo-500 hover:bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium transition flex items-center shadow-sm">
                    <span class="material-symbols-outlined text-sm mr-2" :class="{'animate-spin': isTesting}">sync</span>
                    <span x-text="isTesting ? 'Testing...' : 'Test Connection'"></span>
                </button>
            </div>
            @endif
        </div>

        <form action="{{ route('settings.ai.openrouter.update') }}" method="POST">
            @csrf
            
            <div class="space-y-4 max-w-2xl">
                <!-- Analytics Connection -->
                <div class="mb-6 pb-6 border-b border-slate-200">
                    <h3 class="text-md font-semibold text-slate-800 mb-3">Database Target</h3>
                    <label class="block text-sm font-medium mb-1 text-slate-700" for="analytics_connection_id">Analytics Connection</label>
                    <select id="analytics_connection_id" name="analytics_connection_id" class="form-select w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="" disabled {{ !$analyticsConnectionId ? 'selected' : '' }}>Select a connection...</option>
                        @foreach($connections as $conn)
                            <option value="{{ $conn->id }}" {{ $analyticsConnectionId == $conn->id ? 'selected' : '' }}>
                                {{ $conn->name }} ({{ $conn->type }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 mt-1">ChatEDP will only query this connection. Ensure it is a read-only replica if possible.</p>
                    @error('analytics_connection_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <h3 class="text-md font-semibold text-slate-800 mb-3 mt-4">OpenRouter Settings</h3>
                <!-- API Key -->
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700" for="api_key">API Key</label>
                    <input id="api_key" name="api_key" type="password" class="form-input w-full bg-slate-50 border border-slate-300 text-slate-900 rounded py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ $isConfigured ? '••••••••••••••••••••••••••••••••••••••••' : 'sk-or-v1-...' }}">
                    @if($isConfigured)
                        <p class="text-xs text-slate-500 mt-1">Leave blank to keep the current API key. Only enter a value to replace it.</p>
                    @endif
                    @error('api_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Base URL -->
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700" for="base_url">Base URL</label>
                    <input id="base_url" name="base_url" type="url" value="{{ old('base_url', $baseUrl) }}" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" required>
                    @error('base_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Model -->
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700" for="model">Model</label>
                    <div class="relative" x-data="{
                            open: false,
                            search: '',
                            get filteredModels() {
                                if (this.search === '') {
                                    return this.models;
                                }
                                return this.models.filter(m => 
                                    m.name.toLowerCase().includes(this.search.toLowerCase()) || 
                                    m.id.toLowerCase().includes(this.search.toLowerCase())
                                );
                            },
                            selectModel(id) {
                                this.currentModel = id;
                                this.search = '';
                                this.open = false;
                                this.fetchModelProviders(id);
                            },
                            get currentModelName() {
                                const m = this.models.find(model => model.id === this.currentModel);
                                return m ? (m.name + ' (' + m.id + ')') : (this.currentModel || 'Select a model...');
                            }
                        }">
                        <input type="hidden" name="model" :value="currentModel">
                        
                        <div @click="if(!isLoadingModels) { open = !open; if(open) $nextTick(() => $refs.searchInput.focus()); }" 
                             @click.outside="open = false"
                             class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 flex justify-between items-center cursor-pointer"
                             :class="{'bg-slate-100 text-slate-500 cursor-not-allowed': isLoadingModels}">
                            <span x-text="currentModelName" class="truncate mr-4"></span>
                            
                            <div class="flex items-center space-x-2 shrink-0">
                                <span x-show="isLoadingModels" class="material-symbols-outlined animate-spin text-slate-400 text-sm">sync</span>
                                <span class="material-symbols-outlined text-slate-400 text-lg" x-text="open ? 'expand_less' : 'expand_more'"></span>
                            </div>
                        </div>

                        <div x-show="open" 
                             class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded shadow-lg max-h-60 overflow-y-auto"
                             style="display: none;">
                            <div class="sticky top-0 bg-white p-2 border-b border-slate-100">
                                <input type="text" x-model="search" x-ref="searchInput" @click.stop class="form-input w-full bg-slate-50 border border-slate-300 text-sm rounded py-1.5 px-2" placeholder="Search models by name or ID...">
                            </div>
                            <ul>
                                <template x-for="m in filteredModels" :key="m.id">
                                    <li @click="selectModel(m.id)" 
                                        class="px-3 py-2 cursor-pointer hover:bg-indigo-50 transition border-b border-slate-50 last:border-0"
                                        :class="{'bg-indigo-50': currentModel === m.id}">
                                        <div x-text="m.name" class="font-medium text-sm text-slate-800" :class="{'text-indigo-700': currentModel === m.id}"></div>
                                        <div x-text="m.id" class="text-xs text-slate-500" :class="{'text-indigo-500': currentModel === m.id}"></div>
                                    </li>
                                </template>
                                <li x-show="filteredModels.length === 0" class="px-3 py-4 text-sm text-center text-slate-500">
                                    No models found matching "<span x-text="search"></span>"
                                </li>
                            </ul>
                        </div>
                    </div>
                    @error('model') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <!-- Timeout -->
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700" for="timeout">Request Timeout (seconds)</label>
                    <input id="timeout" name="timeout" type="number" min="5" max="120" value="{{ old('timeout', $timeout) }}" class="form-input w-full bg-white border border-slate-300 text-slate-900 rounded py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" required>
                    @error('timeout') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Test Result Diagnostics -->
            <div x-show="testResult" class="mt-8 p-4 rounded border max-w-2xl" :class="testResult && testResult.success ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'" style="display: none;">
                <h3 class="text-sm font-semibold mb-3" :class="testResult && testResult.success ? 'text-emerald-800' : 'text-red-800'" x-text="testResult && testResult.success ? 'Configuration valid' : 'Configuration invalid'"></h3>
                
                <div class="text-sm space-y-1.5" :class="testResult && testResult.success ? 'text-emerald-700' : 'text-red-700'">
                    <template x-if="testResult && !testResult.success && testResult.reason">
                        <div><span class="font-semibold">Reason:</span> <span x-text="testResult.reason"></span></div>
                    </template>
                    <template x-if="testResult && testResult.success">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div><span class="font-semibold">Model:</span> <span x-text="testResult.model"></span></div>
                            <div><span class="font-semibold">Provider:</span> <span x-text="testResult.provider || 'Unknown'"></span></div>
                            <div><span class="font-semibold">Status:</span> <span x-text="testResult.status"></span></div>
                            <div><span class="font-semibold">Latency:</span> <span x-text="testResult.latency_ms + ' ms'"></span></div>
                        </div>
                    </template>
                    <div class="mt-2 pt-2 border-t" :class="testResult && testResult.success ? 'border-emerald-200' : 'border-red-200'">
                        <span class="font-semibold">Message:</span> <span x-text="testResult ? testResult.message : ''"></span>
                    </div>
                </div>
            </div>

            <!-- Last Configuration Test -->
            <div class="mt-4 p-4 bg-slate-50 rounded border border-slate-200 max-w-2xl" x-show="!testResult">
                <h3 class="text-sm font-semibold text-slate-700 mb-2">Last Configuration Test</h3>
                <div class="text-sm text-slate-600">
                    <span class="font-medium" x-text="lastTestResult">{{ $lastTest }}</span>
                    <span x-show="lastTestTime" class="text-slate-400 ml-2">— <span x-text="lastTestTime">{{ $lastTestTime }}</span></span>
                </div>
            </div>

            @if(auth()->user()->hasPermission('ai_settings.update'))
            <div class="mt-8 pt-5 border-t border-slate-200 flex justify-between items-center">
                <button type="submit" class="btn bg-indigo-500 hover:bg-indigo-600 text-white rounded px-5 py-2 font-medium shadow-sm transition">
                    Save Configuration
                </button>
                
                @if($isConfigured)
                <button type="button" @click="disconnect" class="btn bg-rose-500 hover:bg-rose-600 text-white rounded px-4 py-2 text-sm font-medium transition flex items-center shadow-sm">
                    <span class="material-symbols-outlined text-sm mr-2">link_off</span>
                    Disconnect from OpenRouter
                </button>
                @endif
            </div>
            @endif
        </form>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('openRouterSettings', () => ({
            isTesting: false,
            lastTestResult: '{{ $lastTest }}',
            lastTestTime: '{{ $lastTestTime }}',
            testResult: null,
            
            models: [],
            isLoadingModels: true,
            currentModel: '{{ old('model', $model) }}',
            init() {
                this.fetchModels();
            },

            fetchModels() {
                this.isLoadingModels = true;
                fetch('https://openrouter.ai/api/v1/models')
                    .then(res => res.json())
                    .then(data => {
                        this.models = data.data;
                        this.isLoadingModels = false;
                        
                        if (this.currentModel && !this.models.find(m => m.id === this.currentModel)) {
                            this.models.push({ id: this.currentModel, name: this.currentModel + ' (Custom)' });
                        }
                        
                        this.models.sort((a, b) => a.name.localeCompare(b.name));
                    })
                    .catch(err => {
                        this.isLoadingModels = false;
                        console.error('Failed to fetch models', err);
                        if (this.currentModel) {
                            this.models = [{ id: this.currentModel, name: this.currentModel }];
                        }
                    });
            },
            
            testConnection() {
                this.isTesting = true;
                this.testResult = null;
                
                // Collect current form state
                const formData = {
                    api_key: document.getElementById('api_key').value,
                    model: this.currentModel,
                    base_url: document.getElementById('base_url').value,
                    timeout: document.getElementById('timeout').value
                };
                
                fetch('{{ route("settings.ai.openrouter.test") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(formData)
                })
                .then(res => res.json())
                .then(data => {
                    this.isTesting = false;
                    this.testResult = data;
                    
                    if (data.success) {
                        this.lastTestResult = data.message;
                        this.lastTestTime = new Date().toLocaleString();
                        Swal.fire('Success', data.message, 'success');
                    } else {
                        Swal.fire('Test Failed', data.message || 'Configuration invalid', 'error');
                    }
                })
                .catch(err => {
                    this.isTesting = false;
                    this.testResult = {
                        success: false,
                        reason: 'network_error',
                        message: 'Failed to communicate with the server.'
                    };
                    Swal.fire('Error', 'Failed to communicate with the server.', 'error');
                });
            },
            
            disconnect() {
                Swal.fire({
                    title: 'Konfirmasi',
                    text: "Anda akan memutuskan koneksi dari OpenRouter. Ini akan menonaktifkan fitur AI (ChatEDP).",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f43f5e',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Putuskan Koneksi',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("settings.ai.openrouter.disconnect") }}';
                        
                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        
                        form.appendChild(csrf);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        }));
    });
</script>
@endsection
