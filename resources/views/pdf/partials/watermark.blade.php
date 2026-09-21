{{-- Watermark diagonal (DRAFT / DIBATALKAN) — fixed agar muncul di setiap halaman. Butuh $doc['watermark']. --}}
@if (!empty($doc['watermark']))
    <div style="position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 96px; font-weight: bold; letter-spacing: 8px; color: {{ $doc['watermark'] === 'DIBATALKAN' ? '#e9b8b8' : '#dddddd' }}; transform: rotate(-28deg); z-index: -1;">
        {{ $doc['watermark'] }}
    </div>
@endif
