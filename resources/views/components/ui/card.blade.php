{{-- Card Component: <x-ui.card title="..." icon="..." subtitle="..." gradient="from-x to-y" iconColor="text-x"> --}}
@props([
    'title' => '',
    'icon' => 'widgets',
    'subtitle' => '',
    'gradient' => 'from-indigo-50 to-blue-100',
    'iconColor' => 'text-indigo-600',
    'id' => null,
])

<div @if($id) id="{{ $id }}" @endif class="ui-card animate-fade-up">
    <div class="ui-card-header">
        <div>
            <h2 class="text-h3 text-surface-800 flex items-center gap-2.5">
                <div class="ui-card-icon bg-gradient-to-br {{ $gradient }}">
                    <span class="material-symbols-outlined {{ $iconColor }} text-[18px]">{{ $icon }}</span>
                </div>
                {{ $title }}
            </h2>
            @if($subtitle)
                <p class="text-label-sm text-surface-400 mt-1 ml-[42px]">{{ $subtitle }}</p>
            @endif
        </div>
        @if(isset($actions))
            <div class="flex items-center gap-3">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{ $slot }}

    @if(isset($footer))
        {{ $footer }}
    @endif
</div>
