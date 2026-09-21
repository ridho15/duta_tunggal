<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sales Order dari Quotation langsung Approved?
    |--------------------------------------------------------------------------
    | false (default): SO dibuat berstatus Draft dan tetap melalui "Ajukan Persetujuan" ->
    |                  persetujuan Manajer Sales (anti-self-approval & batas nominal
    |                  ApprovalControlService berlaku).
    | true           : SO langsung Approved dengan approve_by = pembuat (perilaku lama aksi
    |                  tabel Quotation). Melewati persetujuan SO — hanya untuk bisnis yang
    |                  menganggap persetujuan Quotation sudah cukup.
    */
    'so_from_quotation_auto_approve' => (bool) env('SALES_SO_FROM_QUOTATION_AUTO_APPROVE', false),

    /*
    |--------------------------------------------------------------------------
    | Pembulatan baris penjualan (Fase 5B, keputusan D6)
    |--------------------------------------------------------------------------
    | Jumlah desimal untuk DPP dan PPN per baris (Quotation, SO, Invoice, PDF, laporan):
    | 2 (default) = selaras decimal(15,2) dan layar; 0 = rupiah bulat (perilaku lama TaxService).
    | Mengubah nilai ini hanya memengaruhi dokumen/jurnal BARU — invoice yang sudah terbit tidak dihitung ulang.
    | Perubahan kebijakan pembulatan PPN wajib disetujui akuntansi.
    */
    'line_rounding_decimals' => (int) env('SALES_LINE_ROUNDING_DECIMALS', 2),

    /*
    |--------------------------------------------------------------------------
    | Kontrol stok & pengiriman (T2 — docs/PLAN-T2-STOK-PENGIRIMAN.md)
    |--------------------------------------------------------------------------
    | SEMUA flag default false → perilaku lama utuh sampai dihidupkan bertahap (urutan di §9 rencana):
    |   ledger                : buku besar reservasi tunggal; reservasi DO DIKONSUMSI saat Dikirim (memperbaiki reservasi yatim),
    |                           gerakan stok pengiriman idempoten.
    |   strict_dispatch       : DeliveryOrderTransitions (satu pintu status), jadwal wajib Mulai dulu, larangan stok negatif
    |                           saat Dikirim, gagal-kirim mengembalikan stok, aksi Kirim/Diterima/Batalkan.
    |   reserve_on_so_approve : reservasi stok sejak SO Approved (dipindahkan ke DO saat DO disetujui).
    |   block_short_approval  : approve SO stok kurang DIBLOKIR kecuali backorder beralasan.
    */
    'stock' => [
        'ledger' => (bool) env('SALES_STOCK_LEDGER', false),
        'strict_dispatch' => (bool) env('SALES_STOCK_STRICT_DISPATCH', false),
        'reserve_on_so_approve' => (bool) env('SALES_STOCK_RESERVE_ON_SO_APPROVE', false),
        'block_short_approval' => (bool) env('SALES_STOCK_BLOCK_SHORT_APPROVAL', false),
    ],

];
