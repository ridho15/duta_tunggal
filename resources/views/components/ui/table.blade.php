@props([
    'toolbar' => null,
    'footer' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col']) }}>
    @if ($toolbar || isset($toolbarSlot))
        <div class="p-3.5 md:p-4 border-b border-[#eef0f2] flex flex-wrap gap-2.5 items-center justify-between bg-white">
            {{ $toolbarSlot ?? $toolbar }}
        </div>
    @endif

    <div class="overflow-x-auto w-full">
        <table class="w-full border-collapse text-left text-sm min-w-[720px]">
            @if (isset($head))
                <thead>
                    <tr class="text-xs font-semibold text-gray-500 bg-gray-50/80 border-b border-gray-200">
                        {{ $head }}
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-gray-100 bg-white">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($footer || isset($footerSlot))
        <div class="p-3.5 md:p-4 border-t border-[#eef0f2] text-xs text-gray-500 bg-white">
            {{ $footerSlot ?? $footer }}
        </div>
    @endif
</div>
