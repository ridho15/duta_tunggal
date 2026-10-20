# ALUR BISNIS PENJUALAN – ERP DUTA TUNGGAL

Sumber: dokumen Ko Handy "DISKUSI dengan Rey 12 OKT 2026" + klarifikasi WhatsApp (Okt 2026). Disusun 13/10/2026.
Dokumen ini adalah **aturan bisnis**. Jika bertentangan dengan mockup atau kode, dokumen ini yang diikuti. Poin bertanda **[MENUNGGU]** belum diputuskan.

## Prinsip

1. Sangat mudah dipakai: **tidak ada input produk ulang** di langkah mana pun. Setiap dokumen mengambil barang dari dokumen sebelumnya.
2. 1 SO bisa menjadi **beberapa DO** (bertahap, dan/atau dari beberapa gudang).
3. Toko (cabang penjual) dan gudang pengirim bisa berbeda; 1 pesanan bisa diambil dari beberapa gudang.

## Alur

```text
SO ──approve kasir──► DO (per gudang) ──gudang kirim──► [Retur DO bila ada selisih] ──► SJ (kolektif) ──► Invoice (kolektif) ──► Pembayaran
```

### 1. Sales Order (SO)
- Diisi: customer, produk, qty, harga, diskon, **ongkos kirim**.
- Gudang **tidak** dipilih di SO.
- SO "Ambil Sendiri" tetap melalui DO.

### 2. Approve SO
- Dilakukan oleh **kasir atau peran setara**, bukan Super Admin.
- Kasir boleh approve jika:
  - harga tiap barang **≥ batas harga di master produk** (termasuk harga setelah diskon per cabang), dan
  - total piutang customer setelah SO **tidak melewati limit kredit**.
- Jika salah satu syarat tidak terpenuhi → naik ke atasan.
- **[MENUNGGU]** Harga di bawah batas: (A) ditolak saat simpan, atau (B) boleh disimpan lalu approve oleh atasan.
- COD: kasir memverifikasi bahwa titipan/pembayaran customer **sudah benar-benar masuk** sebelum DO dibuat.

### 3. Delivery Order (DO)
- Hanya dari SO yang sudah disetujui. Barang dan harga mengikuti SO.
- **Gudang dipilih di DO.** Per produk ditampilkan semua gudang dengan stok bebas > 0, user mengisi qty per gudang.
- Qty DO ≤ sisa SO; qty per gudang ≤ stok bebas gudang tersebut.
- DO dipecah per gudang (contoh DO 1-01 dari gudang A, DO 1-02 dari gudang B).
- **Stok bebas terkunci saat DO dibuat.**

### 4. Gudang kirim
- Barang di layar gudang mengikuti DO.
- Checker mencocokkan barang. Jika sesuai → klik **KIRIM**.
- Jika tidak sesuai (kurang/rusak) → **Retur by DO**.

### 5. Retur by DO (sebelum SJ)
- Dibuat oleh toko (sales/checker), mencatat qty diterima, kurang, dan reject.
- Penomoran: DO asal `DO.000x` → retur `DO.000xR` → DO pengganti `DO.000xC`.
- DO asal ditandai "sudah diretur". **Hanya DO.000xC yang bisa dijadikan SJ.**
- Retur yang disetujui: tercatat di **Stock Adjustment** (referensi DO.000xR) dan **stok gudang asal bertambah** sesuai qty retur. Barang reject masuk lokasi karantina/rusak, bukan stok jual.
- Jenis mutasi stok sendiri: "Retur DO" (berbeda dari retur penjualan setelah invoice).

### 6. Surat Jalan (SJ)
- Urutan: pilih **Customer > SO > centang DO**. Tidak ada input produk.
- 1 SJ boleh menggabungkan beberapa DO (**kolektif**) asalkan dari **SO yang sama**, termasuk DO dari gudang berbeda.
- DO yang sudah dipakai di SJ lain **disembunyikan** dari pilihan (keputusan Ko Handy).
- Membuat SJ **otomatis menandai DO "diterima"** (satu langkah).
- Cetakan SJ menampilkan gudang asal per barang.

### 7. Invoice
- Urutan: pilih **Customer > SO > centang SJ** (kolektif, SO yang sama). Tidak ada input produk.
- Bisa menambah ongkos kirim / biaya lain.
- Invoice **tidak terbit otomatis** dari DO.
- Setelah posting, invoice **terkunci**; koreksi lewat pembatalan/revisi yang tercatat.

## Pembelian (ringkas, dari hasil UAT)

- Alur: Permintaan Pembelian → PO (disetujui) → Terima Barang + QC → Invoice Pembelian → Pembayaran Supplier.
- QC di saat terima barang: qty bagus masuk stok; qty reject **tidak masuk stok, tidak dijurnal**, dan otomatis menjadi **tepat 1 dokumen retur pembelian**.
- Hutang supplier hanya dihitung dari qty bagus yang ditagihkan; reject QC tidak boleh mengurangi hutang dua kali.
- Rekening supplier wajib tersimpan di master dan tampil saat pembayaran.

## Inventory

- **Stok bebas = stok fisik − stok terkunci DO.** Angka inilah yang dipakai untuk menjual dan membuat DO.
- **[USULAN, perlu konfirmasi]** Stock opname: hitung fisik (blind count), selisih direview, lalu diposting sebagai Penyesuaian Stok.
- **[USULAN, perlu konfirmasi]** Transfer antar gudang memakai menu Pindah Stok; stok asal berkurang dan stok tujuan bertambah setelah diterima.
