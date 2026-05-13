## Order Request Currency Form Behavior

Status: RESOLVED
Tanggal update: 2026-05-12

### Ringkasan
Bug konversi harga pada perubahan mata uang item di Order Request sudah diperbaiki.
Saat mata uang item diubah (mis. IDR ke USD), nilai berikut sekarang ikut dikonversi:
- original_price (harga master)
- unit_price (harga override)
- total, total_cost, subtotal, tax_nominal dihitung ulang berdasarkan nilai baru

### Implementasi Fix
Perubahan ada pada afterStateUpdated field currency item di form Repeater:
- File: app/Filament/Resources/OrderRequestResource.php
- Field: Select::make('currency_id') pada item

Logika yang diterapkan:
1. Ambil currency lama dan currency baru
2. Ambil rate lama dan baru ke IDR
3. Parse nilai original_price dan unit_price saat ini
4. Konversi dengan rumus:
   converted = (current * oldRateToIdr) / newRateToIdr
5. Set ulang original_price dan unit_price
6. Hitung ulang total terkait item (total, total_cost, subtotal, tax_nominal)

### Contoh Skenario
Input awal:
- Mata uang lama: IDR
- Mata uang baru: USD
- original_price: 109.859
- unit_price: 109.859
- Rate USD ke IDR: 15.000

Hasil konversi:
- 109.859 / 15.000 = 7,323933...
- Tampilan 2 desimal: 7,32

### Validasi
Test terkait konversi dan approval tetap lulus:
- tests/Feature/OrderRequestCurrencyConversionTest.php
- tests/Feature/OrderRequestApprovalCurrencyTest.php
- tests/Feature/OrderRequestCurrencyChangeIssueTest.php

