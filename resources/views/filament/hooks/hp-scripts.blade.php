@auth
@php
    $dtScanFallbackUrl = null;
    try {
        $dtManifest = public_path('build/manifest.json');
        if (is_file($dtManifest) && array_key_exists('resources/js/scan-fallback.js', (array) json_decode((string) file_get_contents($dtManifest), true))) {
            $dtScanFallbackUrl = \Illuminate\Support\Facades\Vite::asset('resources/js/scan-fallback.js');
        }
    } catch (\Throwable) {
        $dtScanFallbackUrl = null;
    }
@endphp
<script>
/* Tampilan HP: menu samping selalu tertutup saat halaman dibuka (Filament menyimpan status "terbuka"
   dari desktop). Menu dibuka lewat tombol "Menu" di navigasi bawah. */
(() => {
    const closeOnMobile = () => {
        if (window.innerWidth < 1024 && window.Alpine?.store('sidebar')) window.Alpine.store('sidebar').close();
    };
    document.addEventListener('alpine:initialized', closeOnMobile);
    document.addEventListener('livewire:navigated', closeOnMobile);
})();

/**
 * Tahap 3 — pemindai barcode SKU untuk layar HP (komponen hp.scan-button).
 * 1) BarcodeDetector bawaan browser (Chrome Android), 2) cadangan html5-qrcode (dimuat bila perlu),
 * 3) input manual. Hasil dikirim sebagai event Livewire "barcode-scanned" { code }.
 */
document.addEventListener('alpine:init', () => {
    const fallbackUrl = @js($dtScanFallbackUrl);
    const formats = ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'qr_code'];

    const loadFallback = () => new Promise((resolve) => {
        if (window.DtHtml5Qrcode) return resolve(true);
        if (! fallbackUrl) return resolve(false);
        window.addEventListener('dt-scan-fallback-ready', () => resolve(true), { once: true });
        const script = document.createElement('script');
        script.type = 'module';
        script.src = fallbackUrl;
        script.onerror = () => resolve(false);
        document.head.appendChild(script);
    });

    Alpine.data('dtBarcodeScanner', (hint) => ({
        isOpen: false,
        manual: '',
        message: hint,
        stream: null,
        timer: null,
        fallback: null,
        lastCode: null,

        async open() {
            this.isOpen = true;
            this.manual = '';
            this.message = hint;
            await this.$nextTick();

            if (! navigator.mediaDevices?.getUserMedia) {
                this.message = 'Kamera tidak tersedia di perangkat ini. Ketik kode produk di bawah.';
                return;
            }

            if ('BarcodeDetector' in window) {
                try {
                    const supported = await window.BarcodeDetector.getSupportedFormats();
                    const detector = new window.BarcodeDetector({ formats: formats.filter((f) => supported.includes(f)) });
                    const video = document.createElement('video');
                    video.setAttribute('playsinline', '');
                    video.muted = true;
                    this.$refs.reader.innerHTML = '';
                    this.$refs.reader.appendChild(video);
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    video.srcObject = this.stream;
                    await video.play();
                    this.timer = setInterval(async () => {
                        try {
                            const codes = await detector.detect(video);
                            if (codes.length) this.found(codes[0].rawValue);
                        } catch (e) {}
                    }, 300);
                    return;
                } catch (e) {
                    this.stopNative();
                }
            }

            if (await loadFallback()) {
                try {
                    const id = 'dt-scan-' + Math.random().toString(36).slice(2);
                    this.$refs.reader.id = id;
                    const F = window.DtHtml5QrcodeFormats;
                    this.fallback = new window.DtHtml5Qrcode(id, {
                        formatsToSupport: [F.CODE_128, F.EAN_13, F.EAN_8, F.CODE_39, F.UPC_A, F.QR_CODE],
                        verbose: false,
                    });
                    await this.fallback.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 140 } }, (text) => this.found(text));
                    return;
                } catch (e) {}
            }

            this.message = 'Kamera tidak bisa dibuka (izin ditolak atau tidak didukung). Ketik kode produk di bawah.';
        },

        found(code) {
            code = String(code || '').trim();
            if (! code || code === this.lastCode) return;
            this.lastCode = code;
            if (navigator.vibrate) navigator.vibrate(80);
            this.close();
            Livewire.dispatch('barcode-scanned', { code });
            setTimeout(() => { this.lastCode = null; }, 1500);
        },

        submitManual() {
            if (this.manual.trim()) this.found(this.manual);
        },

        stopNative() {
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },

        async close() {
            this.isOpen = false;
            this.stopNative();
            if (this.fallback) {
                try { await this.fallback.stop(); } catch (e) {}
                this.fallback = null;
            }
            if (this.$refs.reader) this.$refs.reader.innerHTML = '';
        },
    }));
});
</script>
@endauth
