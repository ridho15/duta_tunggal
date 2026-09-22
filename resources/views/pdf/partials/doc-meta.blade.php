{{-- Baris meta dokumen (label: nilai). Butuh $doc['meta']; opsional $width (default 100%). --}}
<table style="width: {{ $width ?? '100%' }}; border-collapse: collapse; margin-bottom: 10px;">
    @foreach ($doc['meta'] as $row)
        <tr>
            <td style="width: 32%; padding: 2px 6px 2px 0; vertical-align: top; border: none;"><strong>{{ $row['label'] }}</strong></td>
            <td style="padding: 2px 0; vertical-align: top; border: none;">: {{ $row['value'] }}</td>
        </tr>
    @endforeach
</table>
