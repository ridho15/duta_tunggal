<x-filament-panels::page>
    <div class="dt-hp">
        <div class="dt-hp-row">
            <a href="{{ $backUrl }}" class="dt-hp-link" style="font-size:15px;color:#374151">‹ Kiriman @if ($queueCount !== null)({{ $queueCount }})@endif</a>
            <span class="dt-hp-pill" style="font-size:9px;padding:1px 6px">{{ strtoupper(auth()->user()->getRoleNames()->first() ?? 'Gudang') }}</span>
        </div>

        <span class="dt-hp-pill">{{ $deliveryOrder->warehouse?->kode ?: $deliveryOrder->warehouse?->name }}</span>
        <div>
            <h1 class="dt-hp-title">{{ $deliveryOrder->do_number }}</h1>
            <div class="dt-hp-sub">untuk {{ $customer ?? '-' }} · ambil barang, cek, lalu centang</div>
        </div>

        @unless ($isShippable)
            <div class="dt-hp-alert is-info">Status DO: {{ \App\Models\DeliveryOrder::statusLabel($deliveryOrder->status) }}.</div>
        @endunless

        <div class="dt-hp-row" style="justify-content:flex-end">
            <span class="dt-hp-sub" style="margin-right:auto">Scan barcode barang untuk mencentang otomatis</span>
            <x-hp.scan-button label="Scan barang" />
        </div>

        @foreach ($items as $item)
            @php
                $isChecked = in_array((int) $item->id, $checked, true);
                $rack = $item->warehouseSources->pluck('rak.code')->filter()->unique()->implode(', ');
            @endphp
            <label @class(['dt-hp-card', 'is-done' => $isChecked]) style="flex-direction:row;align-items:center;gap:14px;cursor:pointer" wire:key="item-{{ $item->id }}">
                <input type="checkbox" class="dt-hp-check" @checked($isChecked) wire:click="toggle({{ $item->id }})" @disabled(! $isShippable)>
                <span style="flex-grow:1;min-width:0">
                    <span class="dt-hp-name" style="display:block">{{ $item->product?->name }}</span>
                    <span class="dt-hp-code" style="display:block">{{ $item->product?->sku }}@if ($rack) · Rak {{ $rack }}@endif</span>
                </span>
                <span class="dt-hp-qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}<small> {{ $item->product?->uom?->name ?? 'pcs' }}</small></span>
            </label>
        @endforeach

        <div class="dt-hp-sub" style="text-align:center">{{ $checkedCount }} dari {{ $items->count() }} barang sudah dicek</div>

        @if ($isShippable)
            <div class="dt-hp-sticky">
                <button type="button" class="dt-hp-btn is-primary"
                        wire:click="ship" wire:loading.attr="disabled" wire:target="ship"
                        @disabled($blockReason !== null)
                        wire:confirm="Kirim {{ $deliveryOrder->do_number }} sekarang? Stok gudang akan dikurangi.">
                    <span wire:loading.remove wire:target="ship">Semua sesuai – KIRIM</span>
                    <span wire:loading wire:target="ship">Memproses…</span>
                </button>
                @if ($blockReason)
                    <div class="dt-hp-reason">{{ $blockReason }}</div>
                @endif
                @if ($returUrl)
                    <a href="{{ $returUrl }}" class="dt-hp-btn is-warning">Ada kurang / rusak</a>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
