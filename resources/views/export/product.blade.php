<table>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Nama Product</th>
            <th>Cabang</th>
            <th>Kategori</th>
            <th>Sell Price(Rp)</th>
            <th>Cost Price(Rp)</th>
            <th>Biaya (Rp)</th>
            <th>Harga Batas (%)</th>
            <th>Item Value (Rp)</th>
            <th>Tipe Pajak</th>
            <th>Pajak (%)</th>
            <th>Jumlah Kelipatan Gudang Besar</th>
            <th>Jumlah Jual Kategori Banyak</th>
            <th>Kode Merek</th>
            <th>Satuan</th>
            <th>Konversi Satuan</th>
            <th>Deskripsi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($listProduct as $product)
        <tr>
            <td>{{ $product->sku }}</td>
            <td>{{ $product->name }}</td>
            <td>({{ $product->cabang->kode }}) {{ $product->cabang->nama }}</td>
            <td>{{ $product->productCategory->name }}</td>
            <td>{{ $product->sell_price }}</td>
            <td>{{ $product->cost_price }}</td>
            <td>{{ $product->biaya }}</td>
            <td>{{ $product->harga_batas }}</td>
            <td>{{ $product->item_value }}</td>
            <td>{{ $product->tipe_pajak }}</td>
            <td>{{ $product->pajak }}</td>
            <td>{{ $product->jumlah_kelipatan_gudang_besar }}</td>
            <td>{{ $product->jumlah_jual_kategori_banyak }}</td>
            <td>{{ $product->kode_merek }}</td>
            <td>{{ $product->uom->name }}</td>
            <td>
                @foreach ($product->unitConversions as $unitConversion)
                {{ $unitConversion->nilai_konversi }} {{ $unitConversion->uom->name }},
                @endforeach
            </td>
            <td>{{ $product->description }}</td>
        </tr>
        @endforeach
    </tbody>
</table>