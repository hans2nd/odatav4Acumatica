@extends('layouts.app')

@section('title', 'ChatEDP - Data Analysis Assistant')

@section('content')
<style>
    /* ===== ChatGPT-Inspired ChatEDP Theme ===== */
    .chatedp-root {
        --sidebar-bg: #171717;
        --sidebar-hover: #212121;
        --sidebar-active: #2a2a2a;
        --sidebar-border: #2e2e2e;
        --sidebar-text: #ececec;
        --sidebar-muted: #8e8e8e;
        --chat-bg: #212121;
        --chat-content-bg: #2f2f2f;
        --msg-user-bg: #303030;
        --msg-assistant-bg: transparent;
        --text-primary: #ececec;
        --text-secondary: #b4b4b4;
        --text-muted: #8e8e8e;
        --border-color: #3e3e3e;
        --accent: #7c3aed;
        --accent-light: #a78bfa;
        --input-bg: #303030;
        --input-border: #424242;
        --code-bg: #1a1a2e;
        font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    }

    /* ===== Sidebar ===== */
    .edp-sidebar {
        background: var(--sidebar-bg);
        width: 260px;
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        border-right: 1px solid var(--sidebar-border);
    }
    .edp-sidebar.collapsed { width: 0; overflow: hidden; padding: 0; }

    .edp-sidebar .new-chat-btn {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 10px 14px; border-radius: 10px;
        color: var(--sidebar-text); font-size: 0.875rem; font-weight: 500;
        border: 1px dashed rgba(255,255,255,0.12);
        background: transparent; cursor: pointer;
        transition: all 0.15s ease;
    }
    .edp-sidebar .new-chat-btn:hover {
        background: var(--sidebar-hover);
        border-color: rgba(255,255,255,0.2);
    }

    .edp-sidebar .search-box {
        width: 100%; padding: 8px 12px; border-radius: 8px;
        background: rgba(255,255,255,0.06); border: 1px solid transparent;
        color: var(--sidebar-text); font-size: 0.8125rem;
        outline: none; transition: all 0.15s;
    }
    .edp-sidebar .search-box::placeholder { color: var(--sidebar-muted); }
    .edp-sidebar .search-box:focus {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.12);
    }

    .conv-item {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; border-radius: 10px;
        cursor: pointer; transition: background 0.12s;
        position: relative;
    }
    .conv-item:hover { background: var(--sidebar-hover); }
    .conv-item.active { background: var(--sidebar-active); }
    .conv-item .conv-title {
        flex: 1; font-size: 0.8125rem; color: var(--sidebar-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .conv-item .conv-del {
        opacity: 0; flex-shrink: 0; padding: 4px; border-radius: 6px;
        color: var(--sidebar-muted); background: transparent; border: none;
        cursor: pointer; transition: all 0.12s; display: flex;
    }
    .conv-item:hover .conv-del { opacity: 1; }
    .conv-item .conv-del:hover { color: #f87171; background: rgba(248,113,113,0.1); }

    /* ===== Main Chat Area ===== */
    .edp-main { background: var(--chat-bg); }

    /* Top bar */
    .edp-topbar {
        background: var(--chat-bg);
        border-bottom: 1px solid var(--border-color);
        padding: 10px 16px;
    }

    /* Messages container: centered like ChatGPT */
    .msg-container { max-width: 768px; margin: 0 auto; padding: 0 24px; }

    /* === Message Row === */
    .msg-row {
        padding: 24px 0;
        animation: msgFade 0.35s ease-out;
    }
    .msg-row + .msg-row {
        border-top: 1px solid rgba(255,255,255,0.04);
    }

    /* User message */
    .msg-row.user .msg-body {
        display: flex; justify-content: flex-end;
    }
    .msg-row.user .msg-bubble {
        background: var(--msg-user-bg);
        color: var(--text-primary);
        border-radius: 20px;
        padding: 12px 20px;
        max-width: 80%;
        font-size: 0.9375rem;
        line-height: 1.65;
        border: 1px solid var(--border-color);
    }

    /* Assistant message: full-width, no bubble */
    .msg-row.assistant .msg-body {
        display: flex; gap: 16px; align-items: flex-start;
    }
    .msg-row.assistant .msg-avatar {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 0 0 2px rgba(124,58,237,0.2);
    }
    .msg-row.assistant .msg-text {
        flex: 1; min-width: 0;
        color: var(--text-primary);
        font-size: 0.9375rem;
        line-height: 1.75;
    }

    /* Prose inside assistant */
    .msg-row.assistant .msg-text strong { color: #fff; font-weight: 600; }
    .msg-row.assistant .msg-text ol,
    .msg-row.assistant .msg-text ul { padding-left: 1.5em; margin: 0.5em 0; }
    .msg-row.assistant .msg-text ol { list-style: decimal; }
    .msg-row.assistant .msg-text ul { list-style: disc; }
    .msg-row.assistant .msg-text li { margin: 0.35em 0; }

    /* Error message style */
    .msg-error-inline {
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.2);
        border-radius: 12px;
        padding: 14px 18px;
        color: #fca5a5;
        display: flex; align-items: flex-start; gap: 10px;
    }

    /* ===== Data Results Card ===== */
    .data-card {
        background: var(--chat-content-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        margin-top: 16px;
    }
    .data-card table { width: 100%; border-collapse: collapse; }
    .data-card thead th {
        background: rgba(255,255,255,0.03);
        font-size: 0.6875rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--text-muted); padding: 10px 16px;
        border-bottom: 1px solid var(--border-color);
        white-space: nowrap; text-align: left;
    }
    .data-card tbody td {
        padding: 10px 16px; font-size: 0.8125rem;
        color: var(--text-secondary);
        border-bottom: 1px solid rgba(255,255,255,0.04);
        white-space: nowrap;
    }
    .data-card tbody tr:last-child td { border-bottom: none; }
    .data-card tbody tr:hover { background: rgba(255,255,255,0.02); }

    /* Export buttons */
    .export-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 14px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 600;
        cursor: pointer; text-decoration: none; transition: all 0.15s;
        border: 1px solid;
    }
    .export-pill.excel {
        color: #4ade80; border-color: rgba(74,222,128,0.2);
        background: rgba(74,222,128,0.06);
    }
    .export-pill.excel:hover { background: rgba(74,222,128,0.12); border-color: rgba(74,222,128,0.35); }
    .export-pill.report {
        color: var(--accent-light); border-color: rgba(167,139,250,0.2);
        background: rgba(167,139,250,0.06);
    }
    .export-pill.report:hover { background: rgba(167,139,250,0.12); border-color: rgba(167,139,250,0.35); }

    /* Chart container */
    .chart-card {
        background: var(--chat-content-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        margin-top: 16px;
    }

    /* ===== Smart Suggestions ===== */
    .smart-sugg {
        margin-top: 16px;
        padding: 14px;
        background: rgba(124,58,237,0.05);
        border: 1px solid rgba(124,58,237,0.12);
        border-radius: 12px;
    }
    .smart-sugg-btn {
        display: block; width: 100%; text-align: left;
        padding: 10px 14px; border-radius: 8px;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.06);
        color: var(--text-secondary); cursor: pointer;
        transition: all 0.12s; margin-top: 8px;
    }
    .smart-sugg-btn:hover {
        background: rgba(124,58,237,0.1);
        border-color: var(--accent-light);
        color: var(--text-primary);
        transform: translateX(4px);
    }

    /* ===== Welcome Screen ===== */
    .welcome-screen {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; min-height: 65vh;
        animation: msgFade 0.45s ease-out;
    }
    .welcome-logo {
        width: 56px; height: 56px; border-radius: 50%;
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 0 40px rgba(124,58,237,0.25);
        margin-bottom: 20px;
    }
    .welcome-title {
        font-size: 1.5rem; font-weight: 700; color: var(--text-primary);
        margin-bottom: 6px;
    }
    .welcome-subtitle {
        font-size: 0.875rem; color: var(--text-muted);
        margin-bottom: 32px; text-align: center; max-width: 400px;
    }
    .welcome-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 12px; width: 100%; max-width: 560px;
    }
    .welcome-card {
        padding: 16px; border-radius: 12px; cursor: pointer;
        background: var(--chat-content-bg);
        border: 1px solid var(--border-color);
        transition: all 0.15s;
    }
    .welcome-card:hover {
        border-color: var(--accent-light);
        background: rgba(124,58,237,0.06);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .welcome-card .wc-title {
        font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);
        margin-bottom: 4px;
    }
    .welcome-card .wc-desc {
        font-size: 0.75rem; color: var(--text-muted);
        line-height: 1.4; display: -webkit-box;
        -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }

    /* ===== Input Area ===== */
    .edp-input-area {
        background: var(--chat-bg);
        padding: 12px 24px 20px;
    }
    .edp-input-box {
        max-width: 768px; margin: 0 auto;
        background: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 24px;
        padding: 12px 18px; display: flex; align-items: flex-end; gap: 12px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .edp-input-box:focus-within {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(124,58,237,0.15);
    }
    .edp-input-box textarea {
        flex: 1; background: transparent; border: none; outline: none; resize: none;
        color: var(--text-primary); font-size: 0.9375rem; font-family: inherit;
        line-height: 1.5; max-height: 200px; overflow-y: auto;
        scrollbar-width: thin; scrollbar-color: var(--border-color) transparent;
    }
    .edp-input-box textarea::placeholder { color: var(--text-muted); }
    .edp-input-box textarea::-webkit-scrollbar { width: 4px; }
    .edp-input-box textarea::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 2px; }

    .send-btn {
        width: 36px; height: 36px; border-radius: 50%; border: none;
        background: var(--accent); color: #fff;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; flex-shrink: 0; transition: all 0.15s;
    }
    .send-btn:hover:not(:disabled) {
        background: #6d28d9;
        transform: scale(1.05);
    }
    .send-btn:disabled { opacity: 0.3; cursor: not-allowed; }

    .edp-disclaimer {
        text-align: center; font-size: 0.6875rem;
        color: var(--text-muted); margin-top: 8px;
        max-width: 768px; margin-left: auto; margin-right: auto;
    }

    /* ===== Loading ===== */
    .typing-indicator { display: flex; gap: 5px; align-items: center; padding: 6px 0; }
    .typing-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--accent-light);
        animation: typingBounce 1.3s ease-in-out infinite;
    }
    .typing-dot:nth-child(2) { animation-delay: 0.15s; }
    .typing-dot:nth-child(3) { animation-delay: 0.3s; }
    @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-6px); opacity: 1; }
    }

    /* ===== Animations ===== */
    @keyframes msgFade {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ===== Scrollbar for chat ===== */
    .edp-chat-scroll::-webkit-scrollbar { width: 6px; }
    .edp-chat-scroll::-webkit-scrollbar-track { background: transparent; }
    .edp-chat-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }
    .edp-chat-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }

    /* Sidebar scrollbar */
    .edp-sidebar::-webkit-scrollbar { width: 4px; }
    .edp-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 2px; }

    /* Responsive */
    @media (max-width: 768px) {
        .edp-sidebar { position: fixed; left: 0; top: 0; bottom: 0; z-index: 50; }
        .welcome-grid { grid-template-columns: 1fr; }
        .msg-container { padding: 0 16px; }
        .msg-row.user .msg-bubble { max-width: 90%; }
    }
</style>

<div class="h-[calc(100vh-64px)] flex chatedp-root" x-data="chatedp()">
    <!-- ===== Sidebar ===== -->
    <div class="edp-sidebar flex flex-col shrink-0 overflow-hidden" :class="sidebarOpen ? '' : 'collapsed'">
        <!-- New Chat -->
        <div class="p-3 shrink-0">
            <button @click="newConversation" class="new-chat-btn">
                <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 0;">edit_square</span>
                <span>Percakapan Baru</span>
            </button>
        </div>

        <!-- Search -->
        <div class="px-3 pb-2 shrink-0">
            <input type="text" x-model="searchQuery" placeholder="Cari..." class="search-box">
        </div>

        <!-- Conversation List -->
        <div class="flex-1 overflow-y-auto px-2 pb-3 space-y-3">
            <template x-for="(group, groupName) in filteredGroupedHistory" :key="groupName">
                <div x-show="group.length > 0">
                    <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--sidebar-muted);" x-text="groupName"></div>
                    <div class="space-y-0.5">
                        <template x-for="conv in group" :key="conv.id">
                            <div @click="loadConversation(conv.id)"
                                 class="conv-item"
                                 :class="conversationId === conv.id ? 'active' : ''">
                                <span class="material-symbols-outlined text-base shrink-0" style="color: var(--sidebar-muted); font-size: 18px;"
                                      x-text="conversationId === conv.id ? 'chat_bubble' : 'chat_bubble_outline'"></span>
                                <span class="conv-title" x-text="conv.title || 'Percakapan'"></span>
                                <button @click.stop="deleteConversation(conv.id)" class="conv-del" title="Hapus">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer -->
        <div class="p-3 shrink-0" style="border-top: 1px solid var(--sidebar-border);">
            <div class="flex items-center gap-2" style="color: var(--sidebar-muted); font-size: 11px;">
                <span class="material-symbols-outlined" style="font-size: 14px; font-variation-settings: 'FILL' 1;">auto_awesome</span>
                <span>ChatEDP · Powered by AI</span>
            </div>
        </div>
    </div>

    <!-- ===== Main ===== -->
    <div class="edp-main flex-1 flex flex-col min-w-0">
        <!-- Top Bar -->
        <div class="edp-topbar flex items-center gap-3 shrink-0">
            <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg transition" style="color: var(--text-muted);"
                    onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='transparent'">
                <span class="material-symbols-outlined text-xl">menu</span>
            </button>
            <div class="flex items-center gap-2">
                <span class="font-semibold" style="color: var(--text-primary); font-size: 0.9375rem;">ChatEDP</span>
                <span style="font-size: 0.6875rem; color: var(--text-muted); background: rgba(255,255,255,0.05); padding: 2px 10px; border-radius: 20px;">Analytics</span>
            </div>
        </div>

        <!-- Chat Scroll Area -->
        <div class="flex-1 overflow-y-auto edp-chat-scroll" id="chat-container">

            <!-- Welcome State -->
            <template x-if="messages.length === 0">
                <div class="msg-container">
                    <div class="welcome-screen">
                        <div class="welcome-logo">
                            <span class="material-symbols-outlined text-white" style="font-size: 28px; font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        </div>
                        <div class="welcome-title">Ada yang bisa saya bantu?</div>
                        <div class="welcome-subtitle">Tanyakan apa saja tentang data bisnis Anda. Saya akan menganalisis dan menyajikan hasilnya secara visual.</div>

                        @if(!empty($suggestedQuestions) && count($suggestedQuestions) > 0)
                            <div class="welcome-grid">
                                @foreach($suggestedQuestions as $sq)
                                    <button @click="setInput({{ json_encode($sq['prompt']) }}); setTimeout(() => sendMessage(), 50)" class="welcome-card text-left">
                                        <div class="wc-title">{{ $sq['title'] }}</div>
                                        <div class="wc-desc">{{ $sq['prompt'] }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="welcome-grid">
                                <div class="welcome-card" @click="setInput('Berapa total penjualan bulan ini?'); setTimeout(() => sendMessage(), 50)" style="cursor:pointer;">
                                    <div class="wc-title">💰 Total Penjualan</div>
                                    <div class="wc-desc">Berapa total penjualan bulan ini?</div>
                                </div>
                                <div class="welcome-card" @click="setInput('Top 5 customer berdasarkan revenue'); setTimeout(() => sendMessage(), 50)" style="cursor:pointer;">
                                    <div class="wc-title">🏆 Top Customer</div>
                                    <div class="wc-desc">Top 5 customer berdasarkan revenue</div>
                                </div>
                                <div class="welcome-card" @click="setInput('Perbandingan penjualan per salesperson'); setTimeout(() => sendMessage(), 50)" style="cursor:pointer;">
                                    <div class="wc-title">📊 Perbandingan Sales</div>
                                    <div class="wc-desc">Perbandingan penjualan per salesperson</div>
                                </div>
                                <div class="welcome-card" @click="setInput('Tren penjualan 6 bulan terakhir'); setTimeout(() => sendMessage(), 50)" style="cursor:pointer;">
                                    <div class="wc-title">📈 Tren Penjualan</div>
                                    <div class="wc-desc">Tren penjualan 6 bulan terakhir</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </template>

            <!-- Message Loop -->
            <template x-for="(msg, index) in messages" :key="index">
                <div>
                    <!-- User Message -->
                    <template x-if="msg.role === 'user'">
                        <div class="msg-row user">
                            <div class="msg-container">
                                <div class="msg-body">
                                    <div class="msg-bubble" x-text="msg.content"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Assistant Message -->
                    <template x-if="msg.role === 'assistant'">
                        <div class="msg-row assistant">
                            <div class="msg-container">
                                <div class="msg-body">
                                    <!-- Avatar -->
                                    <div class="msg-avatar">
                                        <span class="material-symbols-outlined text-white" style="font-size: 18px; font-variation-settings: 'FILL' 1;">auto_awesome</span>
                                    </div>
                                    <!-- Content -->
                                    <div class="msg-text">
                                        <!-- Normal text -->
                                        <template x-if="msg.content && !msg.content.startsWith('⚠️')">
                                            <div x-html="formatMessage(msg.content)"></div>
                                        </template>

                                        <!-- Error -->
                                        <template x-if="msg.content && msg.content.startsWith('⚠️')">
                                            <div class="msg-error-inline">
                                                <span class="material-symbols-outlined" style="font-size: 18px; color: #f87171; flex-shrink: 0; margin-top: 1px;">error</span>
                                                <span x-text="msg.content.replace('⚠️ ', '')"></span>
                                            </div>
                                        </template>

                                        <!-- Smart Suggestions -->
                                        <template x-if="msg.metadata && msg.metadata.suggestions && msg.metadata.suggestions.length > 0">
                                            <div class="smart-sugg">
                                                <p style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--accent-light); display: flex; align-items: center; gap: 6px;">
                                                    <span class="material-symbols-outlined" style="font-size: 14px; font-variation-settings: 'FILL' 1;">lightbulb</span>
                                                    Mungkin Anda bermaksud
                                                </p>
                                                <template x-for="(sugg, sIdx) in msg.metadata.suggestions" :key="sIdx">
                                                    <button @click="setInput(sugg.prompt); setTimeout(() => sendMessage(), 50)" class="smart-sugg-btn">
                                                        <span style="font-weight: 600; font-size: 0.8125rem; color: var(--accent-light); display: block;" x-text="sugg.title"></span>
                                                        <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="'\"' + sugg.prompt + '\"'"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- Results Table -->
                                        <template x-if="msg.results && msg.results.length > 0">
                                            <div>
                                                <div class="data-card">
                                                    <div class="overflow-x-auto">
                                                        <table>
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
                                                    <a :href="`/api/chatedp/export?token=` + msg.export_token" target="_blank" class="export-pill excel">
                                                        <span class="material-symbols-outlined" style="font-size: 14px;">download</span>
                                                        Export Excel
                                                    </a>
                                                    <a :href="`/api/chatedp/report?token=` + msg.export_token" target="_blank" class="export-pill report">
                                                        <span class="material-symbols-outlined" style="font-size: 14px;">summarize</span>
                                                        Report
                                                    </a>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Chart -->
                                        <template x-if="msg.chart_config">
                                            <div class="chart-card">
                                                <canvas :id="'chart-' + index" class="w-full" style="height: 280px;"></canvas>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Loading Indicator -->
            <div x-show="isLoading" class="msg-row assistant" style="animation: msgFade 0.3s ease-out;">
                <div class="msg-container">
                    <div class="msg-body" style="display:flex;gap:16px;align-items:flex-start;">
                        <div class="msg-avatar">
                            <span class="material-symbols-outlined text-white" style="font-size: 18px; font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        </div>
                        <div style="padding: 6px 0;">
                            <div class="typing-indicator">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Input Area -->
        <div class="edp-input-area shrink-0">
            <form @submit.prevent="sendMessage">
                <div class="edp-input-box">
                    <textarea x-model="input"
                              placeholder="Tanyakan sesuatu tentang data Anda..."
                              :disabled="isLoading"
                              rows="1"
                              @input="autoResize($event.target)"
                              @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); sendMessage(); }"></textarea>
                    <button type="submit" :disabled="!input.trim() || isLoading" class="send-btn">
                        <span class="material-symbols-outlined" style="font-size: 20px;">arrow_upward</span>
                    </button>
                </div>
            </form>
            <div class="edp-disclaimer">ChatEDP dapat membuat kesalahan. Periksa informasi penting.</div>
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

            autoResize(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 200) + 'px';
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

                // Reset textarea height
                const ta = document.querySelector('.edp-input-box textarea');
                if (ta) ta.style.height = 'auto';

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
                    this.loadHistory();
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
                // Ordered list items
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
                gradient.addColorStop(0, 'rgba(124, 58, 237, 0.6)');
                gradient.addColorStop(1, 'rgba(124, 58, 237, 0.05)');

                new Chart(canvas, {
                    type: config.type || 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: config.y_label || config.y_field,
                            data: values,
                            backgroundColor: gradient,
                            borderColor: 'rgb(124, 58, 237)',
                            borderWidth: 2,
                            borderRadius: 6,
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
                                    color: '#8e8e8e',
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1e1e1e',
                                titleFont: { family: 'Inter', size: 13 },
                                bodyFont: { family: 'Inter', size: 12 },
                                cornerRadius: 8,
                                padding: 12,
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: 'Inter', size: 11 }, color: '#8e8e8e' }
                            },
                            y: {
                                grid: { color: 'rgba(255,255,255,0.04)' },
                                ticks: { font: { family: 'Inter', size: 11 }, color: '#8e8e8e' },
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
