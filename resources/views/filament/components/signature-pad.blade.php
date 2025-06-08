@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    let canvas = document.getElementById('signature-pad');
    let signaturePad = new SignaturePad(canvas);

    function clearSignature() {
        signaturePad.clear();
        document.getElementById('signature-input').value = '';
    }

    // Simpan data base64 ke input saat menggambar
    canvas.addEventListener('mouseup', () => {
        document.getElementById('signature-input').value = signaturePad.toDataURL();
        console.log('log');
    });

    // Jika ada data sebelumnya, tampilkan
    @if($getState())
        signaturePad.fromDataURL(@json($getState()));
    @endif
</script>
<!-- CDN Signature Pad -->
@endpush
<div>
    <canvas id="signature-pad" class="border rounded" width=400 height=200></canvas>
    <button type="button" onclick="clearSignature()">Hapus</button>
    <input type="hidden" name="data.signature" id="signature-input" wire:model="{{ $getStatePath() }}"
        value="{{ old('data.signature', $getState()) }}">
</div>