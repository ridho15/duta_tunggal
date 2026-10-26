@props([
    'name' => null,
    'id' => null,
    'value' => null,
    'placeholder' => '0',
    'required' => false,
    'disabled' => false,
    'size' => 'sm',
    'min' => 0,
    'readonly' => false,
])

@php
    $inputId = $id ?? ($name ? str_replace(['[', ']'], ['_', ''], $name) : 'rupiah_' . uniqid());
    $sizeClass = $size === 'sm' ? 'py-1.5 pl-8 pr-2.5 text-xs' : 'py-2 pl-9 pr-3 text-sm';
    $prefixClass = $size === 'sm' ? 'left-2.5 text-xs' : 'left-3 text-sm';
    $numericValue = (float) old($name, $value ?? 0);
@endphp

<div
    class="relative w-full"
    x-data="{
        rawValue: {{ $numericValue }},
        displayValue: '',
        init() {
            this.syncDisplay();
            this.$watch('rawValue', () => this.syncDisplay());
        },
        syncDisplay() {
            this.displayValue = (this.rawValue !== null && this.rawValue !== undefined && this.rawValue !== '' && Number(this.rawValue) !== 0)
                ? window.formatRupiah(this.rawValue, false)
                : (this.rawValue === 0 ? '0' : '');
        },
        onInput(e) {
            this.rawValue = window.parseRupiah(e.target.value);
        },
        onBlur() {
            this.syncDisplay();
        },
        onFocus(e) {
            if (e.target.value === '0') {
                this.displayValue = '';
            }
        }
    }"
>
    <span class="absolute inset-y-0 {{ $prefixClass }} flex items-center pointer-events-none font-bold text-gray-400 select-none">
        Rp
    </span>

    <input
        type="text"
        id="{{ $inputId }}_display"
        x-model="displayValue"
        @input="onInput"
        @blur="onBlur"
        @focus="onFocus"
        placeholder="{{ $placeholder }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $readonly ? 'readonly' : '' }}
        {{ $attributes->merge(['class' => "w-full {$sizeClass} bg-white border border-gray-300 rounded-lg font-bold text-gray-900 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition disabled:bg-gray-100 disabled:text-gray-400 font-mono"]) }}
    />

    @if($name)
        <input
            type="hidden"
            name="{{ $name }}"
            id="{{ $inputId }}"
            :value="rawValue"
            {{ $required ? 'required' : '' }}
        />
    @endif
</div>
