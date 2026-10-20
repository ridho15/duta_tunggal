@props([
    'label' => '',
    'value' => '',
    'subtext' => null,
    'variant' => 'dashed',
])

@php
    $variantClasses = match ($variant) {
        'highlight' => 'border border-[#bbf7d0] bg-[#f0fdf4]',
        'solid' => 'border border-gray-200 bg-white',
        'warning' => 'border border-[#fcd34d] bg-[#fffbeb]',
        'danger' => 'border border-[#fecaca] bg-[#fef2f2]',
        default => 'border border-dashed border-gray-300 bg-transparent',
    };

    $labelColor = match ($variant) {
        'highlight' => 'text-[#15803d]',
        'warning' => 'text-[#92400e]',
        'danger' => 'text-[#b91c1c]',
        default => 'text-gray-500',
    };

    $valueColor = match ($variant) {
        'highlight' => 'text-[#14532d]',
        'warning' => 'text-[#92400e]',
        'danger' => 'text-[#b91c1c]',
        default => 'text-gray-900',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl p-3.5 flex flex-col justify-between {$variantClasses}"]) }}>
    <div>
        <div class="text-xs font-medium {{ $labelColor }}">{{ $label }}</div>
        <div class="text-lg md:text-xl font-extrabold tracking-tight mt-0.5 {{ $valueColor }}">
            {{ $value }}
        </div>
    </div>
    @if ($subtext)
        <div class="text-[11px] text-gray-500 mt-1">
            {{ $subtext }}
        </div>
    @endif
    {{ $slot }}
</div>
