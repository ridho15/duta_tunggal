@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-1.5 font-bold transition rounded-xl disabled:opacity-60 disabled:cursor-not-allowed select-none';

    $variantClasses = match ($variant) {
        'primary' => 'bg-[#15803d] hover:bg-[#14532d] text-white border border-transparent shadow-sm focus:ring-2 focus:ring-[#15803d]/30',
        'secondary', 'outline' => 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 shadow-sm focus:ring-2 focus:ring-gray-200',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white border border-transparent shadow-sm focus:ring-2 focus:ring-red-500/30',
        'ghost' => 'bg-transparent hover:bg-gray-100 text-gray-700 border border-transparent',
        'subtle' => 'bg-[#ecfdf3] hover:bg-[#dcfce7] text-[#15803d] border border-[#bbf7d0]',
        default => 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300',
    };

    $sizeClasses = match ($size) {
        'xs' => 'text-xs px-2.5 py-1 min-h-[30px]',
        'sm' => 'text-xs px-3 py-1.5 min-h-[36px]',
        'lg' => 'text-base px-5 py-3 min-h-[48px]',
        default => 'text-sm px-4 py-2 min-h-[40px]',
    };

    $classes = "{$baseClasses} {$variantClasses} {$sizeClasses}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
