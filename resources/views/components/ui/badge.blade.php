{{-- Badge: <x-ui.badge variant="success">Connected</x-ui.badge> --}}
@props([
    'variant' => 'neutral',
])

@php
    $variantClass = match($variant) {
        'success' => 'ui-badge-success',
        'info' => 'ui-badge-info',
        'warning' => 'ui-badge-warning',
        'violet' => 'ui-badge-violet',
        'teal' => 'ui-badge-teal',
        'neutral' => 'ui-badge-neutral',
        default => 'ui-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => "ui-badge {$variantClass}"]) }}>
    {{ $slot }}
</span>
