{{-- Blok tanda tangan. Butuh $doc['signatures'] = [[role, name?, caption]]; nama kosong → garis tanda tangan kosong. --}}
@php $signatures = $doc['signatures'] ?? []; $count = max(1, count($signatures)); @endphp
<table style="width: 100%; border-collapse: collapse; margin-top: 28px; page-break-inside: avoid;">
    <tr>
        @foreach ($signatures as $sig)
            <td style="width: {{ floor(100 / $count) }}%; text-align: center; vertical-align: top; border: none; padding: 0 8px;">
                <div>{{ $sig['role'] }}</div>
                <div style="height: 62px;"></div>
                <div style="border-top: 1px solid #333; padding-top: 3px;">
                    @if (!empty($sig['name']))
                        <strong>{{ $sig['name'] }}</strong><br>
                    @endif
                    <span style="font-size: 9px; color: #555;">{{ $sig['caption'] ?? 'Nama jelas / tanda tangan' }}</span>
                </div>
            </td>
        @endforeach
    </tr>
</table>
