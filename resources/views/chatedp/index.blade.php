@extends('layouts.app')

@section('title', 'ChatEDP - Data Analysis Assistant')

@section('content')
<style>
    /* ===== ChatEDP Premium Theme ===== */
    .chatedp-root {
        --primary: #6366f1;
        --primary-light: #818cf8;
        --primary-dark: #4f46e5;
        --primary-50: #eef2ff;
        --surface: #ffffff;
        --surface-hover: #f8fafc;
        --border: #e2e8f0;
        --border-light: #f1f5f9;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --bg-chat: #f8fafc;
        --gradient-start: #6366f1;
        --gradient-end: #8b5cf6;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* Sidebar */
    .chatedp-sidebar {
        background: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
        transition: width 0.3s ease;
    }
    .chatedp-sidebar .sidebar-btn {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        transition: all 0.2s ease;
    }
    .chatedp-sidebar .sidebar-btn:hover {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }
    .chatedp-sidebar .conv-item {
        border-radius: 10px;
        padding: 10px 14px;
        transition: all 0.15s ease;
        cursor: pointer;
        position: relative;
    }
    .chatedp-sidebar .conv-item:hover {
        background: rgba(255,255,255,0.08);
    }
    .chatedp-sidebar .conv-item.active {
        background: rgba(99, 102, 241, 0.25);
        border: 1px solid rgba(129, 140, 248, 0.3);
    }
    .chatedp-sidebar .conv-item .delete-btn {
        opacity: 0;
        transition: opacity 0.15s;
    }
    .chatedp-sidebar .conv-item:hover .delete-btn {
        opacity: 1;
    }

    /* Chat area */
    .chat-bg {
        background-color: var(--bg-chat);
        background-image: 
            radial-gradient(at 20% 80%, rgba(99,102,241,0.04) 0%, transparent 50%),
            radial-gradient(at 80% 20%, rgba(139,92,246,0.04) 0%, transparent 50%);
    }

    /* Message bubbles */
    .msg-user {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: #fff;
        border-radius: 20px 20px 6px 20px;
        box-shadow: 0 2px 12px rgba(99,102,241,0.25);
    }
    .msg-assistant {
        background: var(--surface);
        color: var(--text-primary);
        border: 1px solid var(--border);
        border-radius: 20px 20px 20px 6px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }

    /* Avatar */
    .avatar-user {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        box-shadow: 0 2px 8px rgba(99,102,241,0.3);
    }
    .avatar-assistant {
        background: linear-gradient(135deg, #06b6d4, #6366f1);
        box-shadow: 0 2px 8px rgba(6,182,212,0.3);
    }

    /* Results card */
    .results-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .results-card table thead th {
        background: #f8fafc;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        padding: 12px 16px;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    .results-card table tbody td {
        padding: 10px 16px;
        font-size: 0.8125rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-light);
        white-space: nowrap;
    }
    .results-card table tbody tr {
        transition: background 0.1s;
    }
    .results-card table tbody tr:hover {
        background: #f8fafc;
    }
    .results-card table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Suggestion chips */
    .suggestion-chip {
        background: var(--primary-50);
        border: 1px solid rgba(99,102,241,0.15);
        border-radius: 12px;
        padding: 12px 16px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .suggestion-chip:hover {
        border-color: var(--primary-light);
        background: rgba(99,102,241,0.08);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99,102,241,0.12);
    }

    /* Welcome card */
    .welcome-card {
        background: linear-gradient(135deg, rgba(99,102,241,0.05) 0%, rgba(139,92,246,0.05) 100%);
        border: 1px solid rgba(99,102,241,0.1);
        border-radius: 20px;
        padding: 40px;
    }
    .welcome-icon {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 0 8px 24px rgba(99,102,241,0.25);
    }

    /* Input area */
    .input-wrapper {
        background: var(--surface);
        border: 2px solid var(--border);
        border-radius: 16px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .input-wrapper:focus-within {
        border-color: var(--primary-light);
        box-shadow: 0 0 0 4px rgba(99,102,241,0.08), 0 2px 12px rgba(0,0,0,0.04);
    }
    .input-wrapper input {
        border: none;
        outline: none;
        background: transparent;
        font-size: 0.9375rem;
        color: var(--text-primary);
    }
    .input-wrapper input::placeholder {
        color: var(--text-muted);
    }
    .send-btn {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(99,102,241,0.35);
    }
    .send-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* Export buttons */
    .export-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.15s ease;
        cursor: pointer;
        text-decoration: none;
    }
    .export-btn-excel {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .export-btn-excel:hover {
        background: #d1fae5;
        border-color: #6ee7b7;
    }
    .export-btn-report {
        background: var(--primary-50);
        color: var(--primary);
        border: 1px solid rgba(99,102,241,0.2);
    }
    .export-btn-report:hover {
        background: rgba(99,102,241,0.1);
        border-color: var(--primary-light);
    }

    /* Loading animation */
    .loading-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary-light);
        animation: loadPulse 1.4s ease-in-out infinite;
    }
    .loading-dot:nth-child(2) { animation-delay: 0.2s; }
    .loading-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes loadPulse {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
        40% { transform: scale(1); opacity: 1; }
    }

    /* Smart suggestion in message */
    .smart-suggestion {
        background: linear-gradient(135deg, rgba(99,102,241,0.04), rgba(139,92,246,0.04));
        border: 1px solid rgba(99,102,241,0.12);
        border-radius: 12px;
        padding: 14px;
        margin-top: 12px;
    }
    .smart-suggestion button {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid rgba(99,102,241,0.1);
        background: var(--surface);
        transition: all 0.15s ease;
        margin-top: 8px;
    }
    .smart-suggestion button:hover {
        border-color: var(--primary-light);
        background: var(--primary-50);
        transform: translateX(4px);
    }

    /* Error message */
    .msg-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        border-radius: 12px;
        padding: 12px 16px;
    }

    /* Prose overrides for assistant */
    .msg-assistant .prose-custom strong {
        color: var(--text-primary);
        font-weight: 700;
    }

    /* Markdown list styling inside assistant messages */
    .msg-assistant .prose-custom ol,
    .msg-assistant .prose-custom ul {
        padding-left: 1.5em;
        margin: 0.5em 0;
    }
    .msg-assistant .prose-custom ol { list-style: decimal; }
    .msg-assistant .prose-custom ul { list-style: disc; }
    .msg-assistant .prose-custom li { margin: 0.25em 0; }

    /* Fade in animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInUp {
        animation: fadeInUp 0.3s ease-out;
    }

    /* Chart container */
    .chart-container {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        margin-top: 12px;
    }
</style>

<div class="h-[calc(100vh-64px)] flex chatedp-root" x-data="chatedp()">
    <!-- Sidebar -->
    <div class="chatedp-sidebar w-72 flex flex-col shrink-0 overflow-hidden" :class="sidebarOpen ? 'w-72' : 'w-0'" style="transition: width 0.3s ease;">
        <!-- Sidebar Header -->
        <div class="p-4 shrink-0">
            <button @click="newConversation" class="sidebar-btn w-full flex items-center justify-center gap-2 text-white rounded-xl px-4 py-3 text-sm font-semibold">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                Percakapan Baru
            </button>
        </div>

        <!-- Sidebar Search -->
        <div class="px-4 pb-3 shrink-0">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-indigo-300 text-lg">search</span>
                <input type="text" x-model="searchQuery" placeholder="Cari percakapan..." 
                       class="w-full bg-white/5 border border-white/10 text-white placeholder-indigo-300/60 rounded-lg py-2 pl-9 pr-3 text-sm focus:outline-none focus:border-white/20 focus:bg-white/10 transition">
            </div>
        </div>

        <!-- Conversation List -->
        <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-4">
            <template x-for="(group, groupName) in filteredGroupedHistory" :key="groupName">
                <div x-show="group.length > 0">
                    <div class="px-2 py-2 text-[11px] font-bold text-indigo-300/70 uppercase tracking-widest" x-text="groupName"></div>
                    <div class="space-y-1">
                        <template x-for="conv in group" :key="conv.id">
                            <div @click="loadConversation(conv.id)" 
                                 class="conv-item group flex items-center gap-2"
                                 :class="conversationId === conv.id ? 'active' : ''">
                                <span class="material-symbols-outlined text-indigo-300/60 text-base shrink-0" x-text="conversationId === conv.id ? 'chat_bubble' : 'chat_bubble_outline'"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="truncate text-sm text-white/90 font-medium" x-text="conv.title || 'Percakapan'"></div>
                                    <div class="text-[11px] text-indigo-300/50 mt-0.5" x-text="formatDate(conv.updated_at)"></div>
                                </div>
                                <button @click.stop="deleteConversation(conv.id)" class="delete-btn text-red-300/70 hover:text-red-300 p-1 rounded-md hover:bg-red-400/10 transition" title="Hapus">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-white/5 shrink-0">
            <div class="flex items-center gap-2 text-indigo-300/50 text-xs">
                <span class="material-symbols-outlined text-sm">auto_awesome</span>
                <span>Powered by AI</span>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Bar -->
        <div class="bg-white border-b border-slate-200/80 px-6 py-3 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 transition" title="Toggle sidebar">
                    <span class="material-symbols-outlined text-xl">menu</span>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                            <span class="material-symbols-outlined text-white text-base">analytics</span>
                        </span>
                        ChatEDP
                    </h1>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 bg-slate-100 px-3 py-1 rounded-full font-medium">Natural Language Analytics</span>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 overflow-y-auto chat-bg" id="chat-container">
            <div class="max-w-4xl mx-auto px-6 py-6 space-y-5">

            <!-- Welcome State -->
            <template x-if="messages.length === 0">
                <div class="flex flex-col items-center justify-center min-h-[60vh] animate-fadeInUp">
                    <div class="welcome-card text-center max-w-xl w-full">
                        <div class="welcome-icon">
                            <span class="material-symbols-outlined text-white text-3xl" style="font-variation-settings: 'FILL' 1;">smart_toy</span>
                        </div>
                        <h2 class="text-xl font-bold text-slate-800 mb-2">Halo! Saya ChatEDP 👋</h2>
                        <p class="text-sm text-slate-500 mb-6">Asisten analisis data Anda. Tanyakan apa saja tentang data bisnis yang tersedia.</p>
                        
                        @if(!empty($suggestedQuestions) && count($suggestedQuestions) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                                @foreach($suggestedQuestions as $sq)
                                    <button @click="setInput({{ json_encode($sq['prompt']) }}); setTimeout(() => sendMessage(), 50)" class="suggestion-chip group">
                                        <div class="flex items-start gap-3">
                                            <span class="material-symbols-outlined text-indigo-400 mt-0.5 text-lg shrink-0" style="font-variation-settings: 'FILL' 1;">trending_up</span>
                                            <div>
                                                <span class="font-semibold text-slate-700 text-sm block group-hover:text-indigo-700 transition">{{ $sq['title'] }}</span>
                                                <span class="text-xs text-slate-400 mt-1 block">"{{ $sq['prompt'] }}"</span>
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-400 mt-2">Contoh: "Berapa total penjualan bulan ini?" atau "Top 5 customer berdasarkan revenue"</p>
                        @endif
                    </div>
                </div>
            </template>

            <!-- Message Loop -->
            <template x-for="(msg, index) in messages" :key="index">
                <div class="animate-fadeInUp">
                    <!-- User Message -->
                    <template x-if="msg.role === 'user'">
                        <div class="flex items-end justify-end gap-3 mb-1">
                            <div class="msg-user px-5 py-3 max-w-[75%]">
                                <div class="text-sm leading-relaxed" x-text="msg.content"></div>
                            </div>
                            <div class="avatar-user w-8 h-8 rounded-full flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-white text-base">person</span>
                            </div>
                        </div>
                    </template>

                    <!-- Assistant Message -->
                    <template x-if="msg.role === 'assistant'">
                        <div class="flex items-start gap-3 mb-1">
                            <div class="avatar-assistant w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-1">
                                <span class="material-symbols-outlined text-white text-base" style="font-variation-settings: 'FILL' 1;">smart_toy</span>
                            </div>
                            <div class="max-w-[85%] space-y-3">
                                <!-- Text content -->
                                <template x-if="msg.content && !msg.content.startsWith('⚠️')">
                                    <div class="msg-assistant px-5 py-4">
                                        <div class="prose-custom text-sm leading-relaxed" x-html="formatMessage(msg.content)"></div>
                                    </div>
                                </template>

                                <!-- Error content -->
                                <template x-if="msg.content && msg.content.startsWith('⚠️')">
                                    <div class="msg-error flex items-start gap-2">
                                        <span class="material-symbols-outlined text-red-500 text-lg mt-0.5 shrink-0">error</span>
                                        <div class="text-sm" x-text="msg.content.replace('⚠️ ', '')"></div>
                                    </div>
                                </template>

                                <!-- Smart Suggestions -->
                                <template x-if="msg.metadata && msg.metadata.suggestions && msg.metadata.suggestions.length > 0">
                                    <div class="smart-suggestion">
                                        <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-1.5 mb-1">
                                            <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">lightbulb</span>
                                            Mungkin Anda bermaksud
                                        </p>
                                        <template x-for="(sugg, sIdx) in msg.metadata.suggestions" :key="sIdx">
                                            <button @click="setInput(sugg.prompt); setTimeout(() => sendMessage(), 50)">
                                                <span class="font-semibold text-sm text-indigo-700 block" x-text="sugg.title"></span>
                                                <span class="text-xs text-slate-500" x-text="'\"' + sugg.prompt + '\"'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <!-- Results Table -->
                                <template x-if="msg.results && msg.results.length > 0">
                                    <div>
                                        <div class="results-card">
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-left border-collapse">
                                                    <thead>
                                                        <tr>
                                                            <template x-if="msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns">
                                                                <template x-for="col in msg.metadata.presentation.columns" :key="col.key">
                                                                    <th x-text="col.label"></th>
                                                                </template>
                                                            </template>
                                                            <template x-if="!(msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns)">
                                                                <template x-for="col in Object.keys(msg.results[0])" :key="col">
                                                                    <th x-text="col"></th>
                                                                </template>
                                                            </template>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(row, idx) in msg.results" :key="idx">
                                                            <tr>
                                                                <template x-if="msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns">
                                                                    <template x-for="col in msg.metadata.presentation.columns" :key="col.key">
                                                                        <td x-text="typeof row[col.key] === 'object' && row[col.key] !== null && 'formatted_value' in row[col.key] ? row[col.key].formatted_value : row[col.key]"></td>
                                                                    </template>
                                                                </template>
                                                                <template x-if="!(msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns)">
                                                                    <template x-for="col in Object.keys(row)" :key="col">
                                                                        <td x-text="typeof row[col] === 'object' && row[col] !== null && 'formatted_value' in row[col] ? row[col].formatted_value : row[col]"></td>
                                                                    </template>
                                                                </template>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Export Actions -->
                                        <div class="mt-3 flex gap-2 flex-wrap" x-show="msg.export_token">
                                            <a :href="`/api/chatedp/export?token=` + msg.export_token" target="_blank" class="export-btn export-btn-excel">
                                                <span class="material-symbols-outlined text-sm">download</span>
                                                Export Excel
                                            </a>
                                            <a :href="`/api/chatedp/report?token=` + msg.export_token" target="_blank" class="export-btn export-btn-report">
                                                <span class="material-symbols-outlined text-sm">summarize</span>
                                                Report
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                <!-- Chart -->
                                <template x-if="msg.chart_config">
                                    <div class="chart-container">
                                        <canvas :id="'chart-' + index" class="w-full" style="height: 280px;"></canvas>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Loading Indicator -->
            <div x-show="isLoading" class="flex items-start gap-3 animate-fadeInUp">
                <div class="avatar-assistant w-8 h-8 rounded-full flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-white text-base" style="font-variation-settings: 'FILL' 1;">smart_toy</span>
                </div>
                <div class="msg-assistant px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1.5">
                            <div class="loading-dot"></div>
                            <div class="loading-dot"></div>
                            <div class="loading-dot"></div>
                        </div>
                        <span class="text-sm text-slate-400 font-medium">Menganalisis data...</span>
                    </div>
                </div>
            </div>

            </div>
        </div>

        <!-- Input Area -->
        <div class="bg-white/80 backdrop-blur-sm border-t border-slate-200/80 p-4 shrink-0">
            <form @submit.prevent="sendMessage" class="max-w-4xl mx-auto">
                <div class="input-wrapper flex items-center gap-3 px-4 py-2">
                    <span class="material-symbols-outlined text-slate-300 text-xl shrink-0">chat</span>
                    <input type="text" x-model="input" placeholder="Tanyakan sesuatu tentang data Anda..." 
                           class="flex-1 py-2 text-sm"
                           :disabled="isLoading"
                           @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); sendMessage(); }">
                    <button type="submit" :disabled="!input.trim() || isLoading" class="send-btn">
                        <span class="material-symbols-outlined text-lg">send</span>
                    </button>
                </div>
                <div class="flex items-center justify-between mt-2 px-2">
                    <span class="text-[11px] text-slate-400">
                        <kbd class="px-1.5 py-0.5 bg-slate-100 rounded text-[10px] font-semibold">Enter</kbd> untuk kirim
                    </span>
                    <span class="text-[11px] text-slate-400">AI bisa membuat kesalahan. Periksa informasi penting.</span>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chatedp', () => ({
            conversationId: null,
            messages: [],
            input: '',
            isLoading: false,
            history: [],
            searchQuery: '',
            sidebarOpen: true,
            groupedHistory: {
                'Hari Ini': [],
                'Kemarin': [],
                '7 Hari Terakhir': [],
                'Sebelumnya': []
            },

            init() {
                this.loadHistory();
                this.createNewConversation();
            },

            get filteredGroupedHistory() {
                if (!this.searchQuery.trim()) return this.groupedHistory;
                const q = this.searchQuery.toLowerCase();
                const filtered = {};
                for (const [key, convs] of Object.entries(this.groupedHistory)) {
                    filtered[key] = convs.filter(c => (c.title || '').toLowerCase().includes(q));
                }
                return filtered;
            },

            loadHistory() {
                fetch('/api/chatedp/conversations', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    this.history = data.conversations || [];
                    this.groupHistory();
                })
                .catch(err => console.error("Could not load history", err));
            },

            groupHistory() {
                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                const last7Days = new Date(today);
                last7Days.setDate(last7Days.getDate() - 7);

                this.groupedHistory = {
                    'Hari Ini': [],
                    'Kemarin': [],
                    '7 Hari Terakhir': [],
                    'Sebelumnya': []
                };

                this.history.forEach(conv => {
                    const convDate = new Date(conv.updated_at);
                    if (convDate >= today) {
                        this.groupedHistory['Hari Ini'].push(conv);
                    } else if (convDate >= yesterday) {
                        this.groupedHistory['Kemarin'].push(conv);
                    } else if (convDate >= last7Days) {
                        this.groupedHistory['7 Hari Terakhir'].push(conv);
                    } else {
                        this.groupedHistory['Sebelumnya'].push(conv);
                    }
                });
            },

            loadConversation(id) {
                if (this.conversationId === id) return;
                this.isLoading = true;
                this.messages = [];
                
                fetch(`/api/chatedp/conversations/${id}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    this.conversationId = data.conversation.id;
                    this.messages = (data.messages || []).map(m => {
                        let parsedMeta = null;
                        try {
                            parsedMeta = typeof m.metadata === 'string' ? JSON.parse(m.metadata) : m.metadata;
                        } catch(e) {}
                        
                        return {
                            role: m.role,
                            content: m.content,
                            results: null,
                            export_token: null,
                            chart_config: null,
                            metadata: parsedMeta
                        };
                    });
                    this.isLoading = false;
                    this.scrollToBottom();
                })
                .catch(err => {
                    this.isLoading = false;
                });
            },

            deleteConversation(id) {
                if (!confirm('Hapus percakapan ini?')) return;
                fetch(`/api/chatedp/conversations/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (res.ok) {
                        this.history = this.history.filter(c => c.id !== id);
                        this.groupHistory();
                        if (this.conversationId === id) {
                            this.messages = [];
                            this.createNewConversation();
                        }
                    }
                })
                .catch(err => console.error('Delete failed', err));
            },

            formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                const now = new Date();
                const diff = now - date;
                const mins = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                
                if (mins < 1) return 'Baru saja';
                if (mins < 60) return `${mins} menit lalu`;
                if (hours < 24) return `${hours} jam lalu`;
                return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
            },

            setInput(text) {
                this.input = text;
            },

            newConversation() {
                this.messages = [];
                this.createNewConversation();
            },

            createNewConversation() {
                fetch('/api/chatedp/conversations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ title: 'Analisis Baru' })
                })
                .then(res => res.json())
                .then(data => {
                    this.conversationId = data.conversation.id;
                    this.loadHistory();
                });
            },

            sendMessage() {
                if (!this.input.trim() || !this.conversationId) return;

                const userText = this.input;
                this.messages.push({ role: 'user', content: userText });
                this.input = '';
                this.isLoading = true;
                
                this.scrollToBottom();

                fetch('/api/chatedp/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        conversation_id: this.conversationId,
                        message: userText
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.isLoading = false;
                    
                    if (data.error) {
                        this.messages.push({ role: 'assistant', content: '⚠️ ' + data.error });
                    } else {
                        const msgIdx = this.messages.length;
                        let parsedMeta = null;
                        try {
                            parsedMeta = typeof data.message.metadata === 'string' ? JSON.parse(data.message.metadata) : data.message.metadata;
                        } catch(e) {}

                        this.messages.push({ 
                            role: 'assistant', 
                            content: data.message.content,
                            results: data.results,
                            export_token: data.export_token,
                            chart_config: data.chart_config,
                            metadata: parsedMeta
                        });
                        
                        // Render chart if present after DOM updates
                        if (data.chart_config && data.results && data.results.length > 0) {
                            setTimeout(() => {
                                this.renderChart(msgIdx, data.chart_config, data.results);
                            }, 150);
                        }
                    }
                    this.scrollToBottom();
                    this.loadHistory(); // Refresh sidebar
                })
                .catch(err => {
                    this.isLoading = false;
                    this.messages.push({ role: 'assistant', content: '⚠️ Network Error: Tidak dapat terhubung ke server.' });
                    this.scrollToBottom();
                });
            },

            scrollToBottom() {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }, 80);
            },

            formatMessage(text) {
                if (!text) return '';
                // Bold
                let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                // Ordered list items (numbered)
                formatted = formatted.replace(/^(\d+)\.\s/gm, '<li style="list-style: decimal inside; margin-left: 0;">');
                // Newlines to <br>
                formatted = formatted.replace(/\n/g, '<br>');
                return formatted;
            },

            renderChart(index, config, data) {
                const canvas = document.getElementById('chart-' + index);
                if (!canvas) return;

                const labels = data.map(row => {
                    const val = row[config.x_field];
                    return typeof val === 'object' && val !== null ? (val.formatted_value || val.value || 'Unknown') : (val || 'Unknown');
                });
                const values = data.map(row => {
                    const val = row[config.y_field];
                    return typeof val === 'object' && val !== null ? parseFloat(val.value || 0) : (parseFloat(val) || 0);
                });

                const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 280);
                gradient.addColorStop(0, 'rgba(99, 102, 241, 0.6)');
                gradient.addColorStop(1, 'rgba(139, 92, 246, 0.1)');

                new Chart(canvas, {
                    type: config.type || 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: config.y_label || config.y_field,
                            data: values,
                            backgroundColor: gradient,
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: { family: 'Inter', size: 12, weight: '500' },
                                    color: '#64748b',
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                titleFont: { family: 'Inter', size: 13 },
                                bodyFont: { family: 'Inter', size: 12 },
                                cornerRadius: 10,
                                padding: 12,
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: 'Inter', size: 11 }, color: '#94a3b8' }
                            },
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { family: 'Inter', size: 11 }, color: '#94a3b8' },
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }));
    });
</script>
@endsection
