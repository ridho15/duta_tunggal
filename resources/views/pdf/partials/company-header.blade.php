{{-- Kop dokumen: nama legal, cabang, alamat, telepon, email, NPWP (kolom kosong dilewati) + judul & nomor. Butuh $doc. --}}
@php $c = $doc['company']; @endphp
<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #333; margin-bottom: 14px;">
    <tr>
        <td style="vertical-align: top; width: 58%; padding: 0 0 10px 0; border: none;">
            @if (!empty($c['logo']))
                <img src="{{ $c['logo'] }}" alt="Logo" style="height: 46px; float: left; margin-right: 10px;">
            @endif
            <div style="font-size: 15px; font-weight: bold;">{{ $c['name'] }}</div>
            @if (!empty($c['branch']))
                <div>Cabang {{ $c['branch'] }}</div>
            @endif
            @foreach ($c['lines'] as $line)
                <div style="font-size: 10px; color: #444;">{{ $line }}</div>
            @endforeach
        </td>
        <td style="vertical-align: top; text-align: right; padding: 0 0 10px 0; border: none;">
            <div style="font-size: 24px; font-weight: bold; letter-spacing: 1px;">{{ $doc['title'] }}</div>
            <div style="font-size: 12px; margin-top: 4px;">{{ $doc['number'] }}</div>
        </td>
    </tr>
</table>
