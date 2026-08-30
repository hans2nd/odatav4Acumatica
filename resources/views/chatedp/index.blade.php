@extends('layouts.app')

@section('title', 'ChatEDP - Data Analysis Assistant')

@section('content')
<div class="h-[calc(100vh-64px)] flex bg-slate-50" x-data="chatedp()">
    <!-- Sidebar -->
    <div class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0">
        <div class="p-4 border-b border-slate-200">
            <button @click="newConversation" class="w-full btn bg-indigo-500 hover:bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium shadow-sm transition flex justify-center items-center">
                <span class="material-symbols-outlined text-sm mr-2">add</span> New Chat
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-2 space-y-4">
            <template x-for="(group, groupName) in groupedHistory" :key="groupName">
                <div x-show="group.length > 0">
                    <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider" x-text="groupName"></div>
                    <div class="space-y-1">
                        <template x-for="conv in group" :key="conv.id">
                            <button @click="loadConversation(conv.id)" 
                                    class="w-full text-left p-3 text-sm rounded hover:bg-slate-50 transition"
                                    :class="conversationId === conv.id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600'">
                                <div class="truncate font-medium" x-text="conv.title || 'Conversation'"></div>
                                <div class="text-xs text-slate-400 mt-1" x-text="formatDate(conv.updated_at)"></div>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Bar -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-bold text-slate-800">ChatEDP</h1>
                <p class="text-sm text-slate-500">Natural language data analysis</p>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6" id="chat-container">
        
        <template x-if="messages.length === 0">
            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                <span class="material-symbols-outlined text-5xl mb-4 text-slate-300">smart_toy</span>
                <p class="text-lg font-medium text-slate-500">Ask a question about your data</p>
                @if(!empty($suggestedQuestions) && count($suggestedQuestions) > 0)
                    <div class="mt-8 grid grid-cols-2 gap-4 max-w-2xl">
                        @foreach($suggestedQuestions as $sq)
                            <button @click="setInput({{ json_encode($sq['prompt']) }})" class="p-4 bg-white border border-slate-200 rounded-lg shadow-sm hover:border-indigo-300 text-left transition">
                                <span class="font-medium text-slate-700 block">{{ $sq['title'] }}</span>
                                <span class="text-sm text-slate-500">"{{ $sq['prompt'] }}"</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400 mt-2">Tanyakan sesuatu tentang data yang tersedia.</p>
                @endif
            </div>
        </template>

        <template x-for="(msg, index) in messages" :key="index">
            <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                <div class="max-w-[80%]" :class="msg.role === 'user' ? 'bg-indigo-600 text-white rounded-l-2xl rounded-tr-2xl' : 'bg-white border border-slate-200 text-slate-800 rounded-r-2xl rounded-tl-2xl shadow-sm'">
                    <div class="p-4">
                        <div class="prose prose-sm max-w-none" :class="msg.role === 'user' ? 'prose-invert' : ''" x-html="formatMessage(msg.content)"></div>
                    </div>
                    
                    <!-- If assistant message has results table -->
                    <template x-if="msg.role === 'assistant' && msg.results && msg.results.length > 0">
                        <div class="border-t border-slate-100 p-4 bg-slate-50 rounded-br-2xl">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-sm">
                                    <thead>
                                        <tr>
                                            <template x-if="msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns">
                                                <template x-for="col in msg.metadata.presentation.columns" :key="col.key">
                                                    <th class="border-b border-slate-200 pb-2 px-2 font-semibold text-slate-600 whitespace-nowrap" x-text="col.label"></th>
                                                </template>
                                            </template>
                                            <template x-if="!(msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns)">
                                                <template x-for="col in Object.keys(msg.results[0])" :key="col">
                                                    <th class="border-b border-slate-200 pb-2 px-2 font-semibold text-slate-600 whitespace-nowrap" x-text="col"></th>
                                                </template>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, idx) in msg.results" :key="idx">
                                            <tr>
                                                <template x-if="msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns">
                                                    <template x-for="col in msg.metadata.presentation.columns" :key="col.key">
                                                        <td class="border-b border-slate-100 py-2 px-2 text-slate-600 whitespace-nowrap" x-text="typeof row[col.key] === 'object' && row[col.key] !== null && 'formatted_value' in row[col.key] ? row[col.key].formatted_value : row[col.key]"></td>
                                                    </template>
                                                </template>
                                                <template x-if="!(msg.metadata && msg.metadata.presentation && msg.metadata.presentation.columns)">
                                                    <template x-for="col in Object.keys(row)" :key="col">
                                                        <td class="border-b border-slate-100 py-2 px-2 text-slate-600 whitespace-nowrap" x-text="typeof row[col] === 'object' && row[col] !== null && 'formatted_value' in row[col] ? row[col].formatted_value : row[col]"></td>
                                                    </template>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Export/Report Actions -->
                            <div class="mt-4 flex space-x-3" x-show="msg.export_token">
                                <a :href="`/api/chatedp/export?token=` + msg.export_token" target="_blank" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 flex items-center bg-emerald-50 px-2 py-1 rounded border border-emerald-200">
                                    <span class="material-symbols-outlined text-sm mr-1">download</span> Export Excel
                                </a>
                                <a :href="`/api/chatedp/report?token=` + msg.export_token" target="_blank" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 flex items-center bg-indigo-50 px-2 py-1 rounded border border-indigo-200">
                                    <span class="material-symbols-outlined text-sm mr-1">summarize</span> Report
                                </a>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Chart Render Area -->
                    <template x-if="msg.role === 'assistant' && msg.chart_config">
                        <div class="border-t border-slate-100 p-4">
                            <canvas :id="'chart-' + index" class="w-full h-64"></canvas>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Loading Indicator -->
        <div x-show="isLoading" class="flex justify-start">
            <div class="bg-white border border-slate-200 p-4 rounded-r-2xl rounded-tl-2xl shadow-sm flex items-center space-x-2">
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                <span class="ml-2 text-sm text-slate-500 font-medium">Analyzing data...</span>
            </div>
        </div>
    </div>

    <!-- Input Area -->
    <div class="bg-white border-t border-slate-200 p-4 shrink-0">
        <form @submit.prevent="sendMessage" class="max-w-4xl mx-auto relative">
            <input type="text" x-model="input" placeholder="Ask about your data..." 
                   class="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-full py-3 pl-6 pr-12 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                   :disabled="isLoading">
            <button type="submit" :disabled="!input.trim() || isLoading" class="absolute right-2 top-2 p-1.5 bg-indigo-500 text-white rounded-full hover:bg-indigo-600 disabled:opacity-50 transition">
                <span class="material-symbols-outlined text-xl">send</span>
            </button>
        </form>
        <div class="text-center mt-2">
            <span class="text-xs text-slate-400">AI can make mistakes. Check important information.</span>
        </div>
    </div>
    </div>
</div>

<!-- Add Chart.js for rendering -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chatedp', () => ({
            conversationId: null,
            messages: [],
            input: '',
            isLoading: false,
            history: [],
            groupedHistory: {
                'Today': [],
                'Yesterday': [],
                'Last 7 Days': [],
                'Older': []
            },

            init() {
                this.loadHistory();
                this.createNewConversation();
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
                    'Today': [],
                    'Yesterday': [],
                    'Last 7 Days': [],
                    'Older': []
                };

                this.history.forEach(conv => {
                    const convDate = new Date(conv.updated_at);
                    if (convDate >= today) {
                        this.groupedHistory['Today'].push(conv);
                    } else if (convDate >= yesterday) {
                        this.groupedHistory['Yesterday'].push(conv);
                    } else if (convDate >= last7Days) {
                        this.groupedHistory['Last 7 Days'].push(conv);
                    } else {
                        this.groupedHistory['Older'].push(conv);
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
                            results: null, // Not stored in DB
                            export_token: null, // Expired
                            chart_config: null, // Skip re-rendering old charts for simplicity
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

            formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            },

            setInput(text) {
                this.input = text;
                // this.sendMessage();
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
                    body: JSON.stringify({ title: 'New Analysis' })
                })
                .then(res => res.json())
                .then(data => {
                    this.conversationId = data.conversation.id;
                    this.loadHistory(); // Refresh sidebar to show the new conversation
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
                        this.messages.push({ role: 'assistant', content: '⚠️ Error: ' + data.error });
                    } else {
                        const msgIdx = this.messages.length;
                        this.messages.push({ 
                            role: 'assistant', 
                            content: data.message.content,
                            results: data.results,
                            export_token: data.export_token,
                            chart_config: data.chart_config
                        });
                        
                        // Render chart if present after DOM updates
                        if (data.chart_config && data.results && data.results.length > 0) {
                            setTimeout(() => {
                                this.renderChart(msgIdx, data.chart_config, data.results);
                            }, 100);
                        }
                    }
                    this.scrollToBottom();
                })
                .catch(err => {
                    this.isLoading = false;
                    this.messages.push({ role: 'assistant', content: '⚠️ Network Error: Could not reach the server.' });
                    this.scrollToBottom();
                });
            },

            scrollToBottom() {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    container.scrollTop = container.scrollHeight;
                }, 50);
            },

            formatMessage(text) {
                // Simple formatting for bold and newlines
                let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                return formatted.replace(/\n/g, '<br>');
            },

            renderChart(index, config, data) {
                const canvas = document.getElementById('chart-' + index);
                if (!canvas) return;

                const labels = data.map(row => row[config.x_field] || 'Unknown');
                const values = data.map(row => parseFloat(row[config.y_field]) || 0);

                new Chart(canvas, {
                    type: config.type || 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: config.y_field,
                            data: values,
                            backgroundColor: 'rgba(99, 102, 241, 0.5)',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }));
    });
</script>
@endsection
