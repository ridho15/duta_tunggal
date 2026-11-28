@extends('layouts.app')

@section('title', 'Tanda Tangan Saya · Duta Tunggal ERP')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tanda tangan saya</h1>
        <p class="mt-1 text-sm text-gray-700">
            Dipakai otomatis saat Anda mengajukan, menyetujui, atau membayar dokumen (voucher, permintaan bayar, PO).
            Dokumen yang sudah ditandatangani menyimpan salinannya sendiri, jadi mengganti tanda tangan tidak mengubah dokumen lama.
        </p>
    </div>

    <section class="bg-white rounded-2xl border border-gray-200 p-4" aria-labelledby="ttd-sekarang">
        <h2 id="ttd-sekarang" class="text-sm font-bold text-gray-900">Tanda tangan sekarang</h2>
        @if ($current)
            <img src="{{ $current }}" alt="Tanda tangan Anda saat ini" width="320" height="120" class="mt-3 h-28 w-auto max-w-full object-contain rounded-lg border border-gray-200 bg-white p-2">
        @else
            <p class="mt-2 text-sm text-amber-800">Belum ada. Dokumen yang Anda tanda tangani akan menampilkan nama dan waktu saja, tanpa gambar.</p>
        @endif
    </section>

    <form method="POST" action="{{ route('signature.update') }}" enctype="multipart/form-data"
          x-data="signaturePad()" x-init="init()" @submit="beforeSubmit($event)"
          class="bg-white rounded-2xl border border-gray-200 p-4 space-y-4">
        @csrf
        <div>
            <div class="flex items-center justify-between gap-2">
                <label for="sig-canvas" class="text-sm font-bold text-gray-900">Gambar tanda tangan baru</label>
                <button type="button" @click="clear()" class="cursor-pointer min-h-9 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Hapus gambar</button>
            </div>
            <p class="text-xs text-gray-600 mt-0.5">Pakai mouse, jari, atau pena di kotak di bawah.</p>
            <canvas id="sig-canvas" x-ref="canvas" role="img" aria-label="Area menggambar tanda tangan"
                    class="mt-2 w-full h-48 rounded-xl border-2 border-dashed border-gray-300 bg-white touch-none cursor-crosshair"></canvas>
            <input type="hidden" name="signature_data" x-ref="data">
        </div>

        <div class="border-t border-gray-100 pt-4">
            <label for="sig-file" class="text-sm font-bold text-gray-900">Atau unggah gambar (PNG / JPG, maks. 512 KB)</label>
            <input id="sig-file" type="file" name="signature_file" accept="image/png,image/jpeg"
                   class="cursor-pointer mt-2 block w-full text-sm text-gray-800 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200">
            <p class="text-xs text-gray-600 mt-1">Saran: foto tanda tangan di kertas putih, potong rapat. Bila gambar dan unggahan diisi, unggahan yang dipakai.</p>
            @error('signature_file')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
        </div>

        <p x-show="empty && !hasFile" x-cloak class="text-xs font-semibold text-amber-800" role="status">Gambar di kotak atau pilih file dulu sebelum menyimpan.</p>

        <div class="flex justify-end">
            <button type="submit" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                Simpan tanda tangan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function signaturePad() {
        return {
            ctx: null, drawing: false, empty: true, hasFile: false,
            init() {
                const c = this.$refs.canvas;
                const fit = () => {
                    const r = c.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
                    c.width = r.width * dpr; c.height = r.height * dpr;
                    this.ctx = c.getContext('2d'); this.ctx.scale(dpr, dpr);
                    this.ctx.lineWidth = 2.2; this.ctx.lineCap = 'round'; this.ctx.lineJoin = 'round'; this.ctx.strokeStyle = '#0f172a';
                    this.empty = true;
                };
                fit();
                const pos = e => { const r = c.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
                c.addEventListener('pointerdown', e => { this.drawing = true; c.setPointerCapture(e.pointerId); const [x, y] = pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); });
                c.addEventListener('pointermove', e => { if (!this.drawing) return; const [x, y] = pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); this.empty = false; });
                const end = () => { this.drawing = false; };
                c.addEventListener('pointerup', end); c.addEventListener('pointerleave', end);
                this.$root.querySelector('input[type=file]').addEventListener('change', e => { this.hasFile = e.target.files.length > 0; });
            },
            clear() { const c = this.$refs.canvas; this.ctx.clearRect(0, 0, c.width, c.height); this.empty = true; },
            beforeSubmit(e) {
                if (this.hasFile) return;
                if (this.empty) { e.preventDefault(); return; }
                this.$refs.data.value = this.$refs.canvas.toDataURL('image/png');
            },
        };
    }
</script>
@endpush
