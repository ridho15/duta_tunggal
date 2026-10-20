@props(['label' => 'Scan barcode', 'hint' => 'Arahkan kamera ke barcode di label barang.'])
{{-- Tahap 3 (6.8/6.12): scan barcode SKU. Hasil dikirim sebagai event Livewire "barcode-scanned". --}}
<div x-data="dtBarcodeScanner(@js($hint))" class="contents">
    <button type="button" x-on:click="open()" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class(['dt-hp-scan-trigger']) }}>
        <x-filament::icon icon="heroicon-o-qr-code" class="h-6 w-6" />
    </button>

    <template x-teleport="body">
        <div x-show="isOpen" x-cloak class="dt-scan-overlay" x-on:keydown.escape.window="close()">
            <div class="dt-scan-head">
                <button type="button" x-on:click="close()" style="background:none;border:0;color:#fff;font-weight:600">✕ Tutup</button>
                <span>{{ $label }}</span>
                <span style="width:52px"></span>
            </div>
            <div class="dt-scan-view">
                <div x-ref="reader" style="width:100%;height:100%"></div>
                <div class="dt-scan-frame"></div>
            </div>
            <div class="dt-scan-foot">
                <div class="dt-hp-sub" x-text="message"></div>
                <form x-on:submit.prevent="submitManual()" style="display:flex;gap:8px">
                    <input type="text" x-model="manual" placeholder="Tidak bisa scan? Ketik kode produk" autocomplete="off" style="flex-grow:1">
                    <button type="submit" class="dt-hp-btn is-primary is-small">OK</button>
                </form>
            </div>
        </div>
    </template>
</div>
