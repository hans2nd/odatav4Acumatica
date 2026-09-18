{{-- Button: <x-ui.btn variant="primary" icon="save" size="md"> --}}
@props([
    'variant' => 'primary',
    'icon' => null,
    'size' => 'md',
    'type' => 'button',
])

@php
    $variantClass = match($variant) {
        'primary' => 'ui-btn-primary',
        'secondary' => 'ui-btn-secondary',
        'danger' => 'ui-btn-danger',
        'danger-solid' => 'ui-btn-danger-solid',
        'ghost' => 'ui-btn-ghost',
        'outline' => 'ui-btn-outline',
        'accent' => 'ui-btn-accent',
        'test' => 'ui-btn-test',
        'diag' => 'ui-btn-diag',
        default => 'ui-btn-primary',
    };
    $sizeClass = match($size) {
        'sm' => 'ui-btn-sm',
        'md' => 'ui-btn-md',
        default => 'ui-btn-md',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "ui-btn {$sizeClass} {$variantClass}"]) }}>
    @if($icon)
        <span class="material-symbols-outlined text-[14px]">{{ $icon }}</span>
    @endif
    {{ $slot }}
</button>
