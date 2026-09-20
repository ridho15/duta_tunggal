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

];
