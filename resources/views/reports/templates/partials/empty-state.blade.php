<div class="py-24 flex flex-col items-center justify-center text-center px-4 bg-surface-50/50">
    <div class="w-24 h-24 bg-brand-50 rounded-full flex items-center justify-center mb-6 shadow-inner relative">
        <div class="absolute inset-0 bg-brand-100 rounded-full animate-ping opacity-20"></div>
        <span class="material-symbols-outlined text-[48px] text-brand-500 relative z-10" style="font-variation-settings: 'FILL' 1;">inventory_2</span>
    </div>
    <h3 class="text-h3 text-surface-900 mb-2">No Templates Found</h3>
    <p class="text-body-md text-surface-500 max-w-md mx-auto mb-8">{{ $message }}</p>
    <a href="{{ route('report.builder') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all shadow-md hover:shadow-lg font-medium">
        <span class="material-symbols-outlined text-[20px]">play_arrow</span>
        Go to Report Builder
    </a>
</div>
