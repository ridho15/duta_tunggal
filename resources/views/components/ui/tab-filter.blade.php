@props([
    'active' => false,
    'count' => null,
    'href' => null,
])

@php
    $baseClasses = 'inline-flex items-center gap-1.5 text-[13px] rounded-full px-3.5 py-2 transition font-semibold select-none border';

    if ($active) {
        $classes = "{$baseClasses} bg-[#15803d] text-white border-[#15803d] font-bold shadow-sm";
    } else {
        $classes = "{$baseClasses} bg-white text-gray-700 border-gray-200 hover:bg-gray-50 hover:border-gray-300";
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span>{{ $slot }}</span>
        @if ($count !== null)
            <span class="inline-flex items-center justify-center text-xs font-bold px-1.5 py-0.2 rounded-full {{ $active ? 'bg-white text-[#15803d]' : 'bg-gray-100 text-gray-700' }}">
                {{ $count }}
            </span>
        @endif
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>
        <span>{{ $slot }}</span>
        @if ($count !== null)
            <span class="inline-flex items-center justify-center text-xs font-bold px-1.5 py-0.2 rounded-full {{ $active ? 'bg-white text-[#15803d]' : 'bg-gray-100 text-gray-700' }}">
                {{ $count }}
            </span>
        @endif
    </button>
@endif
