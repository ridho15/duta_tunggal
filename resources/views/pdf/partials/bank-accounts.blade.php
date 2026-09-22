{{-- Rekening bank untuk pembayaran (dari kop Cabang/global). Tidak menampilkan apa pun bila belum diisi. --}}
@if (!empty($doc['company']['banks']))
    <div style="margin-top: 14px; page-break-inside: avoid;">
        <div style="font-weight: bold;">Pembayaran ke rekening:</div>
        <table style="border-collapse: collapse; margin-top: 3px;">
            @foreach ($doc['company']['banks'] as $bank)
                <tr>
                    <td style="border: none; padding: 1px 12px 1px 0;">{{ $bank['bank'] }}@if (!empty($bank['branch'])) ({{ $bank['branch'] }})@endif</td>
                    <td style="border: none; padding: 1px 12px 1px 0;"><strong>{{ $bank['number'] }}</strong></td>
                    <td style="border: none; padding: 1px 0;">@if (!empty($bank['holder'])) a.n. {{ $bank['holder'] }} @endif</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
