{{-- resources/views/pdf/kwitansi.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Kwitansi Penjualan</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .kop {
            text-align: center;
            margin-bottom: 20px;
        }

        .garis {
            border-top: 2px solid #000;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
        }

        .ttd {
            margin-top: 50px;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="kop">
        <strong>PT Duta Tunggal</strong><br>
        Jl. Contoh Alamat No. 123, Padang<br>
        Telp. 08xx-xxxx-xxxx
        <div class="garis"></div>
        <h3>KWITANSI</h3>
    </div>

    <table>
        <tr>
            <td>No. Kwitansi</td>
            <td>: {{ 'KW-'.$transaksi->id }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{ \Carbon\Carbon::parse($transaksi->tanggal)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Telah Terima Dari</td>
            <td>: {{ $transaksi->nama_pelanggan }}</td>
        </tr>
        <tr>
            <td>Untuk Pembayaran</td>
            <td>: {{ $transaksi->keterangan }}</td>
        </tr>
        <tr>
            <td>Jumlah</td>
            <td>: <strong>Rp {{ number_format($transaksi->jumlah, 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <div class="ttd">
        Padang, {{ \Carbon\Carbon::parse($transaksi->tanggal)->translatedFormat('d F Y') }}<br>
        Hormat Kami,<br><br><br><br>
        <strong>{{ $transaksi->diterima_oleh ?? 'Petugas Kasir' }}</strong>
    </div>
</body>

</html>