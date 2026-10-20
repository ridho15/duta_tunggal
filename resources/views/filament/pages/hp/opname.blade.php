<x-filament-panels::page>
    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','); @endphp
    <div class="dt-hp">
        @if (! $opname)
            <x-hp.warehouse-picker :warehouse-id="$warehouseId" :warehouse-label="$warehouseLabel" :options="$warehouseOptions" />

            @if ($warehouseId)
                <div class="dt-hp-sub">Hitung barang di gudang satu per satu. Jumlah di sistem tidak ditampilkan supaya hitungan apa adanya.</div>

                @forelse ($opnames as $item)
                    <div class="dt-hp-card" wire:key="opname-{{ $item->id }}">
                        <div class="dt-hp-row">
                            <span class="dt-hp-name">{{ $item->opname_number }}</span>
                            <span @class(['dt-hp-pill', 'is-info' => $item->status === 'in_progress'])>
                                {{ $item->status === 'in_progress' ? 'Sedang dihitung' : 'Belum mulai' }}
                            </span>
                        </div>
                        <div class="dt-hp-sub">
                            {{ optional($item->opname_date)->translatedFormat('d M Y') }}
                            @if ($item->items_count) · {{ $item->counted_count }} / {{ $item->items_count }} dihitung @endif
                        </div>
                        @if ($item->items_count)
                            <div class="dt-hp-progress"><span style="width: {{ $item->items_count ? round($item->counted_count / $item->items_count * 100) : 0 }}%"></span></div>
                        @endif
                        <button type="button" class="dt-hp-btn is-primary is-small" style="align-self:flex-end" wire:click="start({{ $item->id }})" wire:loading.attr="disabled">
                            {{ $item->status === 'in_progress' ? 'Lanjutkan hitung' : 'Mulai hitung' }}
                        </button>
                    </div>
                @empty
                    <div class="dt-hp-empty">
                        Tidak ada stock opname yang perlu dihitung di gudang ini.
                        @if ($createUrl)
                            <div style="margin-top:8px"><a href="{{ $createUrl }}" class="dt-hp-link">+ Buat stock opname</a></div>
                        @else
                            Opname akan muncul di sini setelah dibuat oleh admin gudang.
                        @endif
                    </div>
                @endforelse
            @endif
        @else
            <div class="dt-hp-row">
                <button type="button" wire:click="pause" class="dt-hp-link" style="font-size:15px;color:#374151;background:none;border:0">‹ Jeda</button>
                <span style="font-size:14px;font-weight:700">{{ $countedCount }} / {{ $total }} dihitung</span>
            </div>
            <div class="dt-hp-progress"><span style="width: {{ $total ? round($countedCount / $total * 100) : 0 }}%"></span></div>

            @if ($current)
                <span class="dt-hp-pill">Opname · {{ $opname->warehouse?->kode ?: $opname->warehouse?->name }}@if ($current->rak) · Rak {{ $current->rak->code ?: $current->rak->name }}@endif</span>
                <div class="dt-hp-row">
                    <h1 class="dt-hp-title">Hitung barang ini</h1>
                    <x-hp.scan-button label="Scan barang" />
                </div>

                <div class="dt-hp-card" style="align-items:center;text-align:center;gap:10px" wire:key="current-{{ $current->id }}">
                    <div class="dt-hp-name" style="font-size:20px">{{ $current->product?->name }}</div>
                    <div class="dt-hp-code">
                        {{ $current->product?->sku }}
                        @if ($current->rak) · Rak {{ $current->rak->code ?: $current->rak->name }} @endif
                        · satuan {{ $current->product?->uom?->name ?? 'pcs' }}
                    </div>
                    <div class="dt-hp-stepper" style="width:100%">
                        <button type="button" wire:click="step(-1)" aria-label="Kurangi">−</button>
                        <input type="number" inputmode="decimal" min="0" step="any" wire:model="qty" placeholder="0"
                               wire:keydown.enter="saveAndNext" aria-label="Jumlah hasil hitung" autofocus>
                        <button type="button" wire:click="step(1)" aria-label="Tambah">+</button>
                    </div>
                    <div class="dt-hp-note">Jumlah di sistem disembunyikan supaya hitungan apa adanya. Selisih muncul setelah selesai.</div>
                </div>

                <div class="dt-hp-sticky" style="flex-direction:row">
                    <button type="button" class="dt-hp-btn is-secondary" style="flex:0 0 36%" wire:click="skip">Lewati</button>
                    <button type="button" class="dt-hp-btn is-primary" wire:click="saveAndNext" wire:loading.attr="disabled">Simpan &amp; berikutnya</button>
                </div>
            @elseif ($allCounted)
                <div class="dt-hp-card is-done" style="text-align:center">
                    <div class="dt-hp-name">Semua barang sudah dihitung</div>
                    <div class="dt-hp-sub">Periksa daftar di bawah. Setelah selesai, selisih dapat direview di desktop.</div>
                </div>
                <button type="button" class="dt-hp-btn is-primary" wire:click="finish"
                        wire:confirm="Selesai menghitung {{ $opname->opname_number }}? Hasil hitung tidak bisa diubah dari HP setelah ini.">
                    Selesai menghitung
                </button>
            @else
                <div class="dt-hp-empty">Daftar barang opname ini kosong.</div>
            @endif

            @if ($counted->isNotEmpty())
                <div style="font-size:12px;font-weight:700;color:#6B7280;letter-spacing:.6px;text-transform:uppercase;margin-top:6px">Sudah dihitung</div>
                <div class="dt-hp-card" style="gap:0;padding-top:4px;padding-bottom:4px">
                    @foreach ($counted as $row)
                        <div class="dt-hp-list-row" wire:key="counted-{{ $row->id }}">
                            <span>{{ $row->product?->name }}</span>
                            <span><b>{{ $fmt($row->physical_qty) }}</b> <button type="button" wire:click="edit({{ $row->id }})" class="dt-hp-link" style="background:none;border:0;text-decoration:underline">ubah</button></span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
