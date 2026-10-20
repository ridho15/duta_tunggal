@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
])

<section {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-2xl p-5 md:p-6 shadow-sm flex flex-col gap-4']) }}>
    @if ($title || isset($header))
        <div class="flex items-center justify-between gap-3 pb-3.5 border-b border-[#eef0f2]">
            <div class="flex items-center gap-3">
                @if (isset($icon))
                    <span class="w-9 h-9 rounded-xl bg-[#ecfdf3] text-[#15803d] inline-flex items-center justify-center flex-shrink-0">
                        {{ $icon }}
                    </span>
                @endif
                <div>
                    @if ($title)
                        <h2 class="text-base font-bold text-gray-900 m-0">{{ $title }}</h2>
                    @endif
                    @if ($subtitle)
                        <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            @if (isset($headerAction))
                <div>
                    {{ $headerAction }}
                </div>
            @elseif ($badge)
                <span class="text-xs font-semibold text-gray-700 bg-gray-100 border border-gray-200 rounded-full px-2.5 py-0.5">
                    {{ $badge }}
                </span>
            @endif
        </div>
    @endif

    <div class="flex-1">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="pt-3.5 border-t border-[#eef0f2]">
            {{ $footer }}
        </div>
    @endif
</section>
