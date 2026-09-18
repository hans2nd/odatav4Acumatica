{{-- Data Table: <x-ui.data-table :columns="[...]" id="..."> --}}
@props([
    'columns' => [],
    'id' => null,
    'checkboxId' => null,
    'checkboxOnchange' => null,
])

<div class="overflow-x-auto">
    <table class="ui-table" @if($id) id="{{ $id }}" @endif>
        <thead>
            <tr>
                @if($checkboxId)
                    <th class="w-12 text-left">
                        <input type="checkbox" id="{{ $checkboxId }}"
                            @if($checkboxOnchange) onchange="{{ $checkboxOnchange }}" @endif
                            class="ui-checkbox">
                    </th>
                @endif
                @foreach($columns as $col)
                    @if(is_array($col))
                        <th class="{{ $col['class'] ?? '' }}">{{ $col['label'] }}</th>
                    @else
                        <th>{{ $col }}</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody {{ $attributes }}>
            {{ $slot }}
        </tbody>
    </table>
</div>
