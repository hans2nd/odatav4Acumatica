{{-- Form Group: <x-ui.form-group label="..." :required="true" hint="..."> --}}
@props([
    'label' => '',
    'required' => false,
    'hint' => null,
    'for' => null,
])

<div {{ $attributes }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="ui-label">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="ui-hint">{{ $hint }}</p>
    @endif
</div>
