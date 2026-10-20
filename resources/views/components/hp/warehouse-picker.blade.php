@props(['warehouseId' => null, 'warehouseLabel' => null, 'options' => collect()])
@if ($warehouseId)
    <div class="dt-hp-row">
        <span class="dt-hp-pill">Gudang {{ $warehouseLabel }}</span>
        @if ($options->count() > 1)
            <details>
                <summary class="dt-hp-link" style="cursor:pointer;list-style:none">Ganti gudang</summary>
                <div class="dt-hp-card" style="position:absolute;right:16px;z-index:20;min-width:220px">
                    @foreach ($options as $option)
                        <button type="button" wire:click="selectWarehouse({{ $option->id }})" class="dt-hp-list-row" style="width:100%;text-align:left;background:none;border:0">
                            {{ $option->kode ?: $option->name }} <span class="dt-hp-code">{{ $option->name }}</span>
                        </button>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@else
    <div class="dt-hp-card">
        <div class="dt-hp-name">Pilih gudang tempat Anda bekerja</div>
        <div class="dt-hp-sub">Pilihan ini diingat sampai Anda keluar. Admin dapat mengikat akun ke satu gudang di Pengguna &amp; Hak Akses.</div>
        @forelse ($options as $option)
            <button type="button" wire:click="selectWarehouse({{ $option->id }})" class="dt-hp-btn is-secondary">
                {{ $option->kode ?: $option->name }} · {{ $option->name }}
            </button>
        @empty
            <div class="dt-hp-alert is-warning">Belum ada gudang yang bisa Anda akses.</div>
        @endforelse
    </div>
@endif
