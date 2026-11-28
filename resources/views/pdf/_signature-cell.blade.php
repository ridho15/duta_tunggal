{{-- Satu kotak tanda tangan di PDF. $label, $sig (DocumentSignature|null), $fallback (nama bila belum ditandatangani) --}}
<td class="sig-cell">
    <div class="sig-label">{{ $label }}</div>
    <div class="sig-space">
        @if ($sig && ($img = $sig->imagePath()))
            <img src="{{ $img }}" class="sig-img" alt="">
        @endif
    </div>
    <div class="sig-name">{{ $sig?->signer_name ?? ($fallback ?? '( .................. )') }}</div>
    <div class="sig-meta">
        @if ($sig)
            {{ $sig->signer_title }}<br>{{ $sig->signed_at->translatedFormat('d M Y H:i') }}
        @else
            &nbsp;
        @endif
    </div>
</td>
