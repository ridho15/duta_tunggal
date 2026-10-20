<x-filament-panels::page>
    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $status = function (array $s) {
            if ($s['free'] <= 0) return ['✕ Habis', '#B91C1C', '#FEF2F2', '#FCA5A5'];
            if ($s['min'] > 0 && $s['free'] < $s['min']) return ['! Menipis', '#92400E', '#FFFBEB', '#FCD34D'];
            return ['✓ Aman', '#15803D', '#F0FDF4', '#86EFAC'];
        };
    @endphp
    <div class="dt-hp">
        <div class="dt-hp-search">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari nama atau kode barang" autofocus>
            <x-hp.scan-button label="Scan barang" />
        </div>

        @if ($results->isEmpty())
            <div class="dt-hp-empty">
                @if (trim($search) === '')
                    Ketik nama/kode barang atau scan barcode di label.
                @else
                    Barang "{{ $search }}" tidak ditemukan.
                @endif
            </div>
        @endif

        @if ($selected)
            @php
                $s = $summary[$selected->id] ?? ['physical' => 0, 'locked' => 0, 'free' => 0, 'min' => 0, 'warehouses' => []];
                [$label, $color, $bg, $border] = $status($s);
                $maxFree = max(1, collect($s['warehouses'])->max('free') ?? 1);
                $uom = $selected->uom?->name ?? 'pcs';
            @endphp
            <div class="dt-hp-card" wire:key="selected-{{ $selected->id }}">
                <div class="dt-hp-row">
                    <div>
                        <div class="dt-hp-name">{{ $selected->name }}</div>
                        <div class="dt-hp-code">{{ $selected->sku }}</div>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:{{ $color }};background:{{ $bg }};border:1px solid {{ $border }};border-radius:6px;padding:3px 7px;white-space:nowrap">{{ $label }}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:6px;margin-top:6px">
                    <span class="dt-hp-big">{{ $fmt($s['free']) }}</span>
                    <span class="dt-hp-sub">{{ $uom }} stok bebas</span>
                </div>
                <div class="dt-hp-sub">Fisik {{ $fmt($s['physical']) }} · dikunci DO {{ $fmt($s['locked']) }}</div>

                @forelse ($s['warehouses'] as $warehouse)
                    <div style="margin-top:6px">
                        <div class="dt-hp-row" style="font-size:13px">
                            <span style="font-weight:600">{{ $warehouse['name'] }}</span>
                            <span><b>{{ $fmt($warehouse['free']) }}</b> <span class="dt-hp-code">bebas · fisik {{ $fmt($warehouse['physical']) }}</span></span>
                        </div>
                        <div class="dt-hp-bar"><span style="width: {{ max(0, min(100, round($warehouse['free'] / $maxFree * 100))) }}%"></span></div>
                    </div>
                @empty
                    <div class="dt-hp-note">Belum ada stok tercatat untuk barang ini.</div>
                @endforelse

                @if ($transferUrl || $requestUrl)
                    <div style="display:flex;gap:8px;margin-top:8px">
                        @if ($transferUrl)
                            <a href="{{ $transferUrl }}" class="dt-hp-btn is-secondary" style="flex:1">Pindah stok</a>
                        @endif
                        @if ($requestUrl)
                            <a href="{{ $requestUrl }}" class="dt-hp-btn is-secondary" style="flex:1">+ Minta pembelian</a>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @foreach ($results as $product)
            @continue($selected && $product->id === $selected->id)
            @php
                $s = $summary[$product->id] ?? ['physical' => 0, 'locked' => 0, 'free' => 0, 'min' => 0, 'warehouses' => []];
                [$label, $color] = $status($s);
            @endphp
            <button type="button" class="dt-hp-card" style="text-align:left" wire:click="open({{ $product->id }})" wire:key="result-{{ $product->id }}">
                <div class="dt-hp-row">
                    <div style="min-width:0">
                        <div class="dt-hp-name" style="font-size:15px">{{ $product->name }}</div>
                        <div class="dt-hp-code">{{ $product->sku }} · <span style="color:{{ $color }};font-weight:600">{{ $label }}</span></div>
                    </div>
                    <span class="dt-hp-qty" style="font-size:20px">{{ $fmt($s['free']) }}<small> {{ $product->uom?->name ?? 'pcs' }}</small></span>
                </div>
            </button>
        @endforeach
    </div>
</x-filament-panels::page>
