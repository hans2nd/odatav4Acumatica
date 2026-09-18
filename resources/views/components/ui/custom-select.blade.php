@props(['id', 'name' => '', 'required' => false])

<div x-data="customSelect('{{ $id }}')" class="relative w-full">
    <select id="{{ $id }}" name="{{ $name }}" class="hidden" x-ref="select" {{ $required ? 'required' : '' }} {{ $attributes }}>
        {{ $slot }}
    </select>
    
    <button type="button" @click="toggle" @click.away="close" class="ui-input flex justify-between items-center w-full bg-white text-left focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500">
        <span x-text="selectedLabel" class="truncate" :class="!selectedValue ? 'text-surface-400' : 'text-surface-900'"></span>
        <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">expand_more</span>
    </button>
    
    <div x-show="open" x-transition.opacity.duration.200ms class="absolute z-30 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto" style="display: none;">
        <ul class="py-1">
            <template x-for="option in options" :key="option.value">
                <li @click="selectOption(option.value)" 
                    class="px-3.5 py-2.5 hover:bg-surface-50 cursor-pointer text-sm transition-colors flex justify-between items-center"
                    :class="option.value === selectedValue ? 'bg-brand-50 text-brand-700 font-medium' : 'text-surface-700'"
                >
                    <span x-text="option.label"></span>
                    <span x-show="option.value === selectedValue" class="material-symbols-outlined text-[16px] text-brand-600">check</span>
                </li>
            </template>
        </ul>
    </div>
</div>
