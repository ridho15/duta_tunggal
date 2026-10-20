<x-filament-panels::page>
    <div class="dt-hp">
        <div class="dt-hp-row">
            <a href="{{ $backUrl }}" class="dt-hp-link" style="font-size:15px;color:#374151">‹ {{ $deliveryOrder->do_number }}</a>
        </div>

        <span class="dt-hp-pill is-warning">Retur DO · akan jadi {{ $returnNumber }}</span>
        <div>
            <h1 class="dt-hp-title">Catat barang kurang / rusak</h1>
            <div class="dt-hp-sub">Isi hanya barang yang tidak sesuai. Sisanya dianggap sesuai.</div>
        </div>

        @if ($blockReason)
            <div class="dt-hp-alert is-warning">
                {{ $blockReason }}
                @if ($existingUrl)
                    <a href="{{ $existingUrl }}" class="dt-hp-link">Lihat retur</a>
                @endif
            </div>
        @else
            @foreach ($items as $item)
                @php
                    $line = $lines[$item->id] ?? ['short' => 0, 'reject' => 0];
                    $qty = (float) $item->quantity;
                    $received = max(0, $qty - (float) $line['short'] - (float) $line['reject']);
                    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
                    $uom = $item->product?->uom?->name ?? 'pcs';
                    $isChanged = ((float) $line['short'] + (float) $line['reject']) > 0;
                @endphp
                <div class="dt-hp-card" wire:key="line-{{ $item->id }}">
                    <div class="dt-hp-row">
                        <span class="dt-hp-name">{{ $item->product?->name }}</span>
                        <span class="dt-hp-code">DO: {{ $fmt($qty) }} {{ $uom }}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
                        @foreach (['short' => 'Kurang', 'reject' => 'Rusak / reject'] as $field => $label)
                            <div>
                                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">{{ $label }}</div>
                                <div class="dt-hp-stepper is-small">
                                    <button type="button" wire:click="step({{ $item->id }}, '{{ $field }}', -1)" aria-label="Kurangi {{ $label }}">−</button>
                                    <input type="number" inputmode="decimal" min="0" step="1" wire:model.blur="lines.{{ $item->id }}.{{ $field }}" aria-label="{{ $label }} {{ $item->product?->name }}">
                                    <button type="button" wire:click="step({{ $item->id }}, '{{ $field }}', 1)" aria-label="Tambah {{ $label }}">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div style="font-size:12px;font-weight:600;color:{{ $isChanged ? '#92400E' : '#15803D' }}">
                        {{ $isChanged ? 'Diterima: '.$fmt($received).' '.$uom : 'Sesuai · '.$fmt($qty).' '.$uom }}
                    </div>
                </div>
            @endforeach

            <div>
                <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:4px">Catatan (opsional)</div>
                <textarea wire:model.blur="note" rows="2" style="width:100%;border:1px solid #D1D5DB;border-radius:10px;padding:10px;font-size:14px" placeholder="Contoh: dus penyok, 1 pcs pecah"></textarea>
            </div>

            @if ($shortTotal + $rejectTotal > 0)
                <div class="dt-hp-alert is-info">
                    Setelah disetujui:
                    @if ($shortTotal > 0) stok {{ $deliveryOrder->warehouse?->kode ?: $deliveryOrder->warehouse?->name }} kembali {{ rtrim(rtrim(number_format($shortTotal, 2, ',', '.'), '0'), ',') }} pcs (kurang). @endif
                    @if ($rejectTotal > 0) {{ rtrim(rtrim(number_format($rejectTotal, 2, ',', '.'), '0'), ',') }} pcs rusak masuk gudang karantina. @endif
                    DO pengganti <b>{{ $deliveryOrder->do_number }}C</b> dibuat untuk Surat Jalan.
                </div>
            @endif

            <div class="dt-hp-sticky">
                <button type="button" class="dt-hp-btn is-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        @disabled($shortTotal + $rejectTotal <= 0)>
                    Simpan &amp; ajukan retur
                </button>
                @if ($shortTotal + $rejectTotal <= 0)
                    <div class="dt-hp-reason">Isi jumlah kurang atau rusak dulu.</div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
