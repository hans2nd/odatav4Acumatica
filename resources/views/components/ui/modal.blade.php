{{-- Modal Component: <x-ui.modal id="..." title="..." :maxWidth="'2xl'"> --}}
@props([
    'id' => 'modal',
    'title' => '',
    'titleIcon' => 'add_circle',
    'titleIconColor' => 'text-brand-500',
    'maxWidth' => '2xl',
    'titleId' => null,
])

@php
    $maxWidthClass = match($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
        default => 'max-w-2xl',
    };
@endphp

<div id="{{ $id }}" class="modal-backdrop hidden">
    <div class="modal-overlay" onclick="closeModal('{{ $id }}')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full {{ $maxWidthClass }} overflow-hidden max-h-[90vh] flex flex-col">
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center shrink-0" style="background: linear-gradient(180deg, rgba(248,250,252,0.8) 0%, rgba(255,255,255,0.4) 100%);">
                <h3 class="text-h3 text-surface-800 flex items-center gap-2" @if($titleId) id="{{ $titleId }}" @endif>
                    <span class="material-symbols-outlined {{ $titleIconColor }} text-[20px]">{{ $titleIcon }}</span>
                    {!! $title !!}
                </h3>
                <button onclick="closeModal('{{ $id }}')"
                    class="ui-icon-btn text-surface-400 hover:text-surface-700 rounded-xl">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Body --}}
            {{ $slot }}

            {{-- Footer --}}
            @if(isset($footer))
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2 shrink-0" style="background: linear-gradient(0deg, rgba(248,250,252,0.5) 0%, rgba(255,255,255,0.3) 100%);">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
