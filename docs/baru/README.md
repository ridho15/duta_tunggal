# Paket Referensi Developer – ERP Duta Tunggal

Versi 13/10/2026 · Disusun oleh tim UAT (Reyner / Hassee)

## Isi paket

| File | Isi | Untuk |
|---|---|---|
| `UIUX_Spec_ERP_Duta_Tunggal.md` | Spesifikasi UI/UX & navigasi v1.1 (prompt AI, design token, komponen, menu baru, peran, spesifikasi halaman, glosarium, panduan Filament, tahapan) | AI + developer |
| `UIUX_Spec_ERP_Duta_Tunggal.pdf` | Versi PDF dari file di atas | Dibaca manusia |
| `menu_mapping.json` | Pemetaan 82 menu lama → 41 menu baru, ikon, peran (format mesin) | AI + developer |
| `Alur_Bisnis_Ko_Handy.md` | Aturan bisnis SO → DO → Retur DO → SJ → Invoice, pembelian, inventory | AI + developer (prioritas tertinggi) |
| `List_Pengerjaan_Developer.md` | Bug penghambat, fitur belum ada, bug lain, yang sudah diperbaiki | Developer |
| `mockups/*.png` | 15 gambar mockup (A1–E5) | Lampirkan ke AI sebagai gambar |
| `mockups/*.html` | Versi HTML statis dari mockup yang sama | Dibuka di browser / dibaca AI |

## Urutan memberi ke AI (Claude / ChatGPT / Cursor)

1. Tempel **prompt pembuka** dari Bagian 0 di `UIUX_Spec_ERP_Duta_Tunggal.md`.
2. Lampirkan `UIUX_Spec_ERP_Duta_Tunggal.md`, `menu_mapping.json`, `Alur_Bisnis_Ko_Handy.md`, `List_Pengerjaan_Developer.md`.
3. Kerjakan **per tahap** (Bagian 9 spec). Untuk setiap layar, lampirkan PNG mockup yang relevan saja (lihat indeks 6.13 di spec), supaya konteks AI tidak penuh.
4. Minta AI menyebut file yang diubah dan cara uji manual setiap selesai satu tahap.

## Prioritas

1. Bug A1–A3 di `List_Pengerjaan_Developer.md` (menghambat alur utama).
2. Tahap 1 spec: navigasi, peran, bahasa, error, tema.
3. Fitur alur Ko Handy (Bagian B list) bersamaan dengan Tahap 2 spec.

## Masih menunggu keputusan

- Batas harga: Opsi A (tolak saat simpan) atau Opsi B (simpan, approve oleh atasan). Mockup memakai Opsi B.
- Apakah "Permintaan Pembayaran" dan "Pengajuan Voucher" fungsinya sama (akan digabung).
- Nama status dan peran di spec masih perkiraan; cocokkan dengan enum dan role yang ada di kode.
