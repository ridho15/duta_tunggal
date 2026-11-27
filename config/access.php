<?php

/*
|--------------------------------------------------------------------------
| Katalog Hak Akses (Peran & Hak Akses, keputusan Ko Handy 27 Nov 2026)
|--------------------------------------------------------------------------
| Bahasa awam: Menu → Halaman → Aksi. Satu centang = satu kunci akses "{halaman}.{aksi}" (mis. penjualan.so.approve).
|
| Per halaman:
|   label    : nama di layar
|   prefix   : awalan nama route halaman ini (route "{prefix}.{akhiran}")
|   subject  : nama izin teknis lama (Spatie), dipakai untuk akses bawaan & disinkronkan saat checklist disimpan
|   bypass   : true bila controller lama meloloskan peran/kelompok tanpa izin teknis (akses bawaan = ikut menu)
|   actions  : [kunci => [label, akhiran route[], izin teknis lama[] opsional]]
|              view   = Lihat (menu tampil & halaman bisa dibuka); akhiran route yang tidak terdaftar ikut "view".
|              create/update/delete = Tambah/Ubah/Hapus; lainnya = tombol khusus.
|   extra_prefixes : awalan route lain yang ikut halaman ini ([awalan => aksi default]).
|
| Export/Import Excel/CSV: HANYA Owner/Super Admin (tidak ada di checklist), lihat Access::canExportImport().
*/

$crud = fn (string $s) => [
    'view' => ['Lihat', ['index', 'show'], ["view any {$s}", "view {$s}"]],
    'create' => ['Tambah', ['create', 'store'], ["create {$s}"]],
    'update' => ['Ubah', ['edit', 'update'], ["update {$s}"]],
    'delete' => ['Hapus', ['destroy'], ["delete {$s}"]],
];

return [
    // Peran yang SELALU punya akses penuh & satu-satunya yang boleh mengatur peran / export-import.
    'super_roles' => ['Owner', 'Super Admin'],

    // Pencarian bersama yang dipakai banyak halaman (combobox customer / produk / supplier): tidak diatur checklist,
    // tetap dijaga aturan izin lama di controller-nya.
    'shared_prefixes' => ['penjualan.customers', 'pembelian.products', 'pembelian.suppliers', 'pembelian.product-details', 'pembelian.supplier-details'],

    // Akhiran route export / import Excel-CSV di SEMUA halaman: khusus Owner / Super Admin (keputusan Ko Handy 27 Nov 2026).
    'export_route_suffixes' => ['excel', 'export', 'download-excel', 'csv', 'import', 'import-store', 'import-template', 'template'],

    'sections' => [
        'penjualan' => [
            'label' => 'Penjualan',
            'pages' => [
                'penjualan.quotations' => [
                    'label' => 'Penawaran Harga', 'prefix' => 'penjualan.quotations', 'subject' => 'quotation',
                    'actions' => array_merge($crud('quotation'), [
                        'update' => ['Ubah', ['edit', 'update', 'revision'], ['update quotation']],
                        'request_approve' => ['Ajukan Approve', ['requestApprove'], ['request quotation']],
                        'approve' => ['Approve / Tolak', ['approve', 'reject'], ['response quotation']],
                        'convert' => ['Jadikan SO', ['convertToSaleOrder'], ['create sales order']],
                        'print' => ['Cetak', ['pdf'], ['view quotation']],
                    ]),
                ],
                'penjualan.so' => [
                    'label' => 'Pesanan Penjualan (SO)', 'prefix' => 'penjualan.so', 'subject' => 'sales order',
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'products.search'], ['view any sales order', 'view sales order']],
                        'create' => ['Tambah', ['create', 'store'], ['create sales order']],
                        'update' => ['Ubah Draft', ['edit', 'update'], ['update sales order']],
                        'approve' => ['Approve / Tolak', ['approve', 'reject'], ['response sales order']],
                        'cancel' => ['Batalkan SO', ['cancel'], ['update sales order']],
                        'complete' => ['Selesai', ['complete'], ['update sales order']],
                    ],
                ],
                'penjualan.do' => [
                    'label' => 'Perintah Kirim (DO)', 'prefix' => 'penjualan.do', 'subject' => 'delivery order', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show'], ['view any delivery order', 'view delivery order']],
                        'create' => ['Buat DO', ['create', 'store'], ['create delivery order']],
                        'confirm' => ['Konfirmasi Stok', ['confirmStock'], ['update delivery order']],
                        'send' => ['Kirim', ['send'], ['update delivery order']],
                        'cancel' => ['Batalkan / Minta Batal', ['cancel', 'request-cancel'], ['update delivery order']],
                        'print' => ['Cetak', ['pdf'], ['view delivery order']],
                    ],
                ],
                'penjualan.sj' => [
                    'label' => 'Surat Jalan', 'prefix' => 'penjualan.sj', 'subject' => 'surat jalan', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show'], ['view any surat jalan', 'view surat jalan']],
                        'create' => ['Buat SJ', ['create', 'store'], ['create surat jalan']],
                        'cancel' => ['Batalkan', ['cancel'], ['update surat jalan']],
                        'print' => ['Cetak', ['pdf'], ['view surat jalan']],
                    ],
                ],
                'penjualan.schedules' => [
                    'label' => 'Jadwal Kirim', 'prefix' => 'penjualan.schedules', 'subject' => 'delivery schedule', 'bypass' => true,
                    'actions' => array_merge($crud('delivery schedule'), [
                        'create' => ['Tambah', ['create', 'store', 'quickDriver', 'quickVehicle'], ['create delivery schedule']],
                        'process' => ['Mulai / Selesai / Gagal', ['start', 'complete', 'fail'], ['update delivery schedule']],
                        'cancel' => ['Batalkan', ['cancel'], ['update delivery schedule']],
                        'print' => ['Cetak', ['pdf'], ['view delivery schedule']],
                    ]),
                ],
                'penjualan.invoices' => [
                    'label' => 'Invoice Penjualan', 'prefix' => 'penjualan.invoices', 'subject' => 'invoice',
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show'], ['view any invoice', 'view invoice']],
                        'create' => ['Buat Invoice', ['create', 'store'], ['create invoice']],
                        'update' => ['Ubah / No. Faktur Pajak', ['edit', 'updateTaxNumber'], ['update invoice']],
                        'print' => ['Cetak', ['pdf'], ['view invoice']],
                    ],
                ],
                'penjualan.receipts' => [
                    'label' => 'Terima Pembayaran', 'prefix' => 'penjualan.receipts', 'subject' => 'customer receipt',
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'customerInvoices'], ['view any customer receipt', 'view customer receipt']],
                        'create' => ['Catat Penerimaan', ['create', 'store'], ['create customer receipt']],
                        'cancel' => ['Batalkan', ['cancel'], ['update customer receipt']],
                        'print' => ['Cetak Kwitansi', ['pdf'], ['view customer receipt']],
                    ],
                ],
                'penjualan.returns' => [
                    'label' => 'Retur Penjualan', 'prefix' => 'penjualan.returns', 'subject' => 'customer return',
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'customerInvoices'], ['view any customer return', 'view customer return']],
                        'create' => ['Tambah', ['create', 'store'], ['create customer return']],
                        'approve' => ['Approve / Tolak', ['approve', 'reject'], ['update customer return']],
                        'process' => ['Terima Barang / QC / Selesaikan', ['markReceived', 'startQc', 'saveQc', 'complete'], ['update customer return']],
                        'print' => ['Cetak', ['pdf'], ['view customer return']],
                    ],
                ],
                'penjualan.credit-notes' => [
                    'label' => 'Nota Kredit', 'prefix' => 'penjualan.credit-notes', 'subject' => 'credit note', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'customerInvoices', 'invoiceDetails'], ['view any credit note', 'view credit note']],
                        'create' => ['Tambah', ['create', 'store', 'fromReturn'], ['create credit note']],
                        'delete' => ['Hapus Draft', ['destroy'], ['delete credit note']],
                        'issue' => ['Terbitkan / Faktur Pajak', ['issue', 'updateTaxDocument'], ['update credit note']],
                        'void' => ['Void', ['void'], ['update credit note']],
                        'print' => ['Cetak', ['pdf'], ['view credit note']],
                    ],
                ],
                'penjualan.laporan' => [
                    'label' => 'Laporan Penjualan', 'prefix' => 'penjualan.laporan', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'print' => ['Cetak PDF', ['pdf']],
                    ],
                    'export_suffixes' => ['excel'],
                ],
            ],
        ],

        'gudang' => [
            'label' => 'Gudang & Stok',
            'pages' => [
                'gudang.kiriman' => [
                    'label' => 'Siapkan & Kirim', 'prefix' => 'gudang.kiriman', 'bypass' => true,
                    'extra_prefixes' => ['hp' => 'view'],
                    'actions' => [
                        'view' => ['Lihat', ['index', 'pickingList']],
                        'process' => ['Cek / Ubah Qty', ['check', 'submitCheck', 'editQty']],
                        'retur' => ['Retur DO', ['retur', 'submitRetur']],
                    ],
                ],
                'gudang.retur-do' => [
                    'label' => 'Persetujuan Retur DO', 'prefix' => 'gudang.retur-do', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'approve' => ['Setujui / Tolak', ['approve', 'reject']],
                    ],
                ],
                'gudang.penerimaan' => [
                    'label' => 'Terima Barang', 'prefix' => 'gudang.penerimaan', 'subject' => 'purchase receipt',
                    'extra_prefixes' => ['pembelian.receipts' => 'view'],
                    'actions' => array_merge($crud('purchase receipt'), [
                        'view' => ['Lihat', ['index', 'show', 'po-items'], ['view any purchase receipt', 'view purchase receipt']],
                        'confirm' => ['Konfirmasi', ['confirm'], ['update purchase receipt']],
                        'void' => ['Void', ['void'], ['update purchase receipt']],
                        'print' => ['Cetak', ['pdf'], ['view purchase receipt']],
                    ]),
                ],
                'gudang.stok' => [
                    'label' => 'Cek Stok', 'prefix' => 'gudang.stok', 'subject' => 'inventory stock',
                    'extra_prefixes' => ['inventory-card' => 'view'],
                    'actions' => [
                        'view' => ['Lihat', ['index', 'card', 'inventory-card'], ['view any inventory stock', 'view inventory stock']],
                        'print' => ['Cetak Kartu Stok', ['card.pdf', 'inventory-card.pdf'], ['view inventory stock']],
                    ],
                    'export_suffixes' => ['card.excel'],
                ],
                'gudang.transfer' => [
                    'label' => 'Pindah Stok (Mutasi)', 'prefix' => 'gudang.transfer', 'subject' => 'stock transfer', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'available-products'], ['view any stock transfer', 'view stock transfer']],
                        'create' => ['Tambah', ['create', 'store'], ['create stock transfer']],
                        'update' => ['Ubah / Ajukan', ['edit', 'update', 'request', 'revert-draft'], ['update stock transfer', 'request stock transfer']],
                        'delete' => ['Hapus', ['destroy'], ['delete stock transfer']],
                        'approve' => ['Setujui / Tolak', ['approve', 'reject'], ['response stock transfer']],
                        'ship' => ['Kirim Barang', ['ship'], ['update stock transfer']],
                        'receive' => ['Terima Barang', ['receive'], ['update stock transfer']],
                        'close' => ['Tutup Sisa', ['close-remaining'], ['response stock transfer']],
                        'void' => ['Void', ['void'], ['response stock transfer']],
                        'print' => ['Cetak', ['pdf'], ['view stock transfer']],
                    ],
                ],
                'gudang.opname' => [
                    'label' => 'Stock Opname', 'prefix' => 'gudang.opname', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show']],
                        'create' => ['Tambah', ['create', 'store']],
                        'process' => ['Hitung / Simpan / Selesai', ['start', 'save-progress', 'recount', 'complete']],
                        'approve' => ['Setujui', ['approve']],
                        'cancel' => ['Batalkan', ['cancel']],
                        'delete' => ['Hapus', ['destroy']],
                        'print' => ['Cetak', ['pdf']],
                    ],
                    'export_suffixes' => ['excel'],
                ],
                'gudang.adjustment' => [
                    'label' => 'Penyesuaian Stok', 'prefix' => 'gudang.adjustment', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'products', 'raks', 'stock']],
                        'create' => ['Tambah', ['create', 'store']],
                        'update' => ['Ubah', ['edit', 'update']],
                        'delete' => ['Hapus', ['destroy']],
                        'approve' => ['Setujui / Tolak', ['approve', 'reject']],
                    ],
                ],
                'gudang.laporan' => [
                    'label' => 'Laporan Stok & Mutasi', 'prefix' => 'gudang.laporan', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'print' => ['Cetak PDF', ['pdf']],
                    ],
                    'export_suffixes' => ['excel'],
                ],
            ],
        ],

        'manufaktur' => [
            'label' => 'Manufaktur',
            'pages' => [
                'manufaktur.bom' => [
                    'label' => 'Resep Produksi (BOM)', 'prefix' => 'manufaktur.bom', 'subject' => 'bill of material', 'bypass' => true,
                    'actions' => array_merge($crud('bill of material'), [
                        'view' => ['Lihat', ['index', 'show', 'product-details', 'generate-code'], ['view any bill of material', 'view bill of material']],
                        'update' => ['Ubah / Aktif-Nonaktif', ['edit', 'update', 'toggle-active'], ['update bill of material']],
                        'print' => ['Cetak', ['pdf']],
                    ]),
                ],
                'manufaktur.spk' => [
                    'label' => 'Perintah Kerja (SPK)', 'prefix' => 'manufaktur.spk', 'subject' => 'manufacturing order', 'bypass' => true,
                    'actions' => array_merge($crud('manufacturing order'), [
                        'view' => ['Lihat', ['index', 'show', 'bom-details'], ['view any manufacturing order', 'view manufacturing order']],
                        'start' => ['Mulai', ['start'], ['update manufacturing order']],
                        'cancel' => ['Batalkan', ['cancel'], ['update manufacturing order']],
                        'print' => ['Cetak', ['pdf']],
                    ]),
                ],
                'manufaktur.material-issue' => [
                    'label' => 'Pengeluaran Bahan', 'prefix' => 'manufaktur.material-issue', 'subject' => 'material issue', 'bypass' => true,
                    'actions' => array_merge($crud('material issue'), [
                        'view' => ['Lihat', ['index', 'show', 'product-details', 'spk-materials'], ['view any material issue', 'view material issue']],
                        'approve' => ['Ajukan / Setujui / Tolak', ['submit-approval', 'confirm-warehouse', 'reject'], ['update material issue']],
                        'complete' => ['Selesaikan', ['complete'], ['update material issue']],
                        'cancel' => ['Batalkan / Void', ['cancel', 'void'], ['update material issue']],
                        'print' => ['Cetak', ['pdf']],
                    ]),
                ],
                'manufaktur.production' => [
                    'label' => 'Hasil Produksi', 'prefix' => 'manufaktur.production', 'subject' => 'production', 'bypass' => true,
                    'actions' => array_merge($crud('production'), [
                        'view' => ['Lihat', ['index', 'show', 'spk-details'], ['view any production', 'view production']],
                        'finish' => ['Selesai / Kembali Draft', ['finish', 'revert-draft'], ['update production']],
                        'print' => ['Cetak', ['pdf']],
                    ]),
                ],
                'manufaktur.qc' => [
                    'label' => 'QC Pabrik', 'prefix' => 'manufaktur.qc', 'subject' => 'quality control', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'raks'], ['view any quality control', 'view quality control']],
                        'create' => ['Tambah', ['create', 'store'], ['create quality control']],
                        'process' => ['Proses QC', ['process', 'process-form'], ['update quality control']],
                        'print' => ['Cetak', ['pdf']],
                    ],
                ],
            ],
        ],

        'pembelian' => [
            'label' => 'Pembelian',
            'pages' => [
                'pembelian.or' => [
                    'label' => 'Permintaan (OR)', 'prefix' => 'pembelian.or', 'subject' => 'order request', 'bypass' => true,
                    'actions' => array_merge($crud('order request'), [
                        'update' => ['Ubah / Ajukan', ['edit', 'update', 'request-approve'], ['update order request']],
                        'approve' => ['Setujui / Tolak', ['approve', 'reject'], ['response order request']],
                        'close' => ['Tutup', ['close'], ['update order request']],
                        'print' => ['Cetak', ['pdf']],
                    ]),
                ],
                'pembelian.po' => [
                    'label' => 'Pesanan Pembelian (PO)', 'prefix' => 'pembelian.po', 'subject' => 'purchase order',
                    'actions' => array_merge($crud('purchase order'), [
                        'update' => ['Ubah / Minta Tutup', ['edit', 'update', 'request-close'], ['update purchase order', 'request purchase order']],
                        'approve' => ['Approve', ['approve'], ['response purchase order']],
                        'cancel' => ['Batalkan / Tutup', ['cancel', 'close'], ['response purchase order']],
                        'print' => ['Cetak', ['pdf'], ['view purchase order']],
                    ]),
                ],
                'pembelian.invoices' => [
                    'label' => 'Invoice Pembelian', 'prefix' => 'pembelian.invoices', 'subject' => 'invoice',
                    'actions' => array_merge($crud('invoice'), [
                        'view' => ['Lihat', ['index', 'show', 'available-receipts', 'receipt-details'], ['view any invoice', 'view invoice']],
                        'post' => ['Terbitkan (Posting)', ['post'], ['update invoice']],
                        'cancel' => ['Batalkan', ['cancel'], ['update invoice']],
                        'print' => ['Cetak', ['pdf'], ['view invoice']],
                    ]),
                ],
                'pembelian.payments' => [
                    'label' => 'Pembayaran Hutang', 'prefix' => 'pembelian.payments', 'subject' => 'vendor payment',
                    'actions' => array_merge($crud('vendor payment'), [
                        'view' => ['Lihat', ['index', 'show', 'supplier-invoices'], ['view any vendor payment', 'view vendor payment']],
                        'post' => ['Posting', ['post'], ['update vendor payment']],
                        'cancel' => ['Batalkan', ['cancel'], ['update vendor payment']],
                        'print' => ['Cetak Voucher', ['pdf'], ['view vendor payment']],
                    ]),
                ],
                'pembelian.returns' => [
                    'label' => 'Retur Pembelian', 'prefix' => 'pembelian.returns', 'subject' => 'purchase return',
                    'actions' => array_merge($crud('purchase return'), [
                        'view' => ['Lihat', ['index', 'show', 'receipt-items', 'supplier-receipts'], ['view any purchase return', 'view purchase return']],
                        'update' => ['Ubah / Ajukan', ['edit', 'update', 'submit'], ['update purchase return']],
                        'approve' => ['Setujui / Tolak / Penyelesaian', ['approve', 'reject', 'resolution'], ['update purchase return']],
                        'print' => ['Cetak', ['pdf'], ['view purchase return']],
                    ]),
                ],
                'pembelian.laporan' => [
                    'label' => 'Laporan Pembelian', 'prefix' => 'pembelian.laporan', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'print' => ['Cetak PDF', ['pdf']],
                    ],
                    'export_suffixes' => ['excel'],
                ],
            ],
        ],

        'keuangan' => [
            'label' => 'Keuangan',
            'pages' => [
                'keuangan.cashbank' => [
                    'label' => 'Kas & Bank', 'prefix' => 'keuangan.cashbank', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'transfer.show']],
                        'create' => ['Tambah / Transfer', ['create', 'store', 'transfer.create', 'transfer.store']],
                        'delete' => ['Hapus / Batalkan', ['destroy', 'transfer.destroy', 'transfer.cancel']],
                        'print' => ['Cetak Voucher', ['pdf', 'transfer.pdf']],
                    ],
                ],
                'keuangan.journal' => [
                    'label' => 'Buku Besar & Jurnal', 'prefix' => 'keuangan.journal', 'subject' => 'journal entry', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index', 'show', 'ledger'], ['view any journal entry', 'view journal entry']],
                        'create' => ['Jurnal Manual', ['create', 'store'], ['create journal entry']],
                        'reverse' => ['Jurnal Balik', ['reverse'], ['update journal entry']],
                        'delete' => ['Hapus', ['destroy'], ['delete journal entry']],
                        'print' => ['Cetak', ['pdf'], ['view journal entry']],
                    ],
                ],
                'keuangan.period' => [
                    'label' => 'Tutup Buku', 'prefix' => 'keuangan.period', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'create' => ['Buat Periode', ['create', 'store']],
                        'close' => ['Tutup Periode', ['close', 'close.form']],
                        'reopen' => ['Buka Kembali', ['reopen']],
                        'delete' => ['Hapus', ['destroy']],
                    ],
                ],
                'master.coa' => [
                    'label' => 'Bagan Akun (COA)', 'prefix' => 'master.coa', 'subject' => 'chart of account', 'bypass' => true,
                    'extra_prefixes' => ['keuangan.coa' => 'view'],
                    'actions' => $crud('chart of account'),
                ],
                'keuangan.asset' => [
                    'label' => 'Aset Tetap', 'prefix' => 'keuangan.asset', 'subject' => 'asset', 'bypass' => true,
                    'actions' => array_merge($crud('asset'), [
                        'view' => ['Lihat', ['index', 'show', 'depreciation.index', 'disposal.index', 'disposal.show', 'transfer.index', 'transfer.show'], ['view any asset', 'view asset']],
                        'depreciation' => ['Penyusutan', ['calculate-depreciation', 'depreciation.process-monthly', 'depreciation.process-single', 'depreciation.reverse', 'post-acquisition-journal'], ['create asset depreciation']],
                        'disposal' => ['Pelepasan Aset', ['disposal.create', 'disposal.store', 'disposal.void'], ['create asset disposal']],
                        'transfer' => ['Mutasi Aset', ['transfer.create', 'transfer.store', 'transfer.approve', 'transfer.complete', 'transfer.cancel', 'transfer.void'], ['create asset transfer']],
                        'print' => ['Cetak', ['pdf', 'depreciation.pdf', 'disposal.pdf', 'transfer.pdf']],
                    ]),
                ],
                'laporan.financial' => [
                    'label' => 'Laporan Keuangan', 'prefix' => 'laporan.financial', 'bypass' => true,
                    'extra_prefixes' => ['reports' => 'view'],
                    'actions' => [
                        'view' => ['Lihat', ['index', 'balance-sheet', 'income-statement', 'trial-balance']],
                        'print' => ['Cetak PDF', ['balance-sheet.pdf', 'income-statement.pdf', 'trial-balance.pdf']],
                    ],
                ],
            ],
        ],

        'master' => [
            'label' => 'Data Master',
            'pages' => [
                'master.products' => [
                    'label' => 'Produk', 'prefix' => 'master.products', 'subject' => 'product', 'bypass' => true,
                    'actions' => array_merge($crud('product'), [
                        'create' => ['Tambah', ['create', 'store', 'generate-sku'], ['create product']],
                        'update' => ['Ubah / Aktif-Nonaktif', ['edit', 'update', 'toggle-active'], ['update product']],
                    ]),
                ],
                'master.customers' => [
                    'label' => 'Customer', 'prefix' => 'master.customers', 'subject' => 'customer', 'bypass' => true,
                    'actions' => array_merge($crud('customer'), [
                        'create' => ['Tambah', ['create', 'store', 'check-duplicates', 'generate-code'], ['create customer']],
                    ]),
                ],
                'master.suppliers' => [
                    'label' => 'Supplier', 'prefix' => 'master.suppliers', 'subject' => 'supplier', 'bypass' => true,
                    'actions' => array_merge($crud('supplier'), [
                        'create' => ['Tambah', ['create', 'store', 'check-duplicates', 'generate-code'], ['create supplier']],
                        'update' => ['Ubah / Aktif-Nonaktif', ['edit', 'update', 'toggle-active'], ['update supplier']],
                        'merge' => ['Gabungkan Duplikat', ['merge'], ['update supplier']],
                    ]),
                ],
                'master.cabang' => ['label' => 'Cabang', 'prefix' => 'master.cabang', 'subject' => 'cabang', 'bypass' => true, 'actions' => $crud('cabang')],
                'master.gudang' => [
                    'label' => 'Gudang & Rak', 'prefix' => 'master.gudang', 'subject' => 'warehouse', 'bypass' => true,
                    'extra_prefixes' => ['master.rak' => 'update', 'gudang.warehouse-raks' => 'view'],
                    'actions' => $crud('warehouse'),
                ],
                'master.kategori' => ['label' => 'Kategori', 'prefix' => 'master.kategori', 'subject' => 'product category', 'bypass' => true, 'actions' => $crud('product category')],
                'master.satuan' => ['label' => 'Satuan', 'prefix' => 'master.satuan', 'subject' => 'unit of measure', 'bypass' => true, 'actions' => $crud('unit of measure')],
                'master.kendaraan' => [
                    'label' => 'Armada & Driver', 'prefix' => 'master.kendaraan', 'subject' => 'vehicle', 'bypass' => true,
                    'extra_prefixes' => ['master.driver' => null],   // null = aksi mengikuti akhiran route (index/create/…)
                    'actions' => $crud('vehicle'),
                ],
                'master.mata-uang' => ['label' => 'Mata Uang & Kurs', 'prefix' => 'master.mata-uang', 'subject' => 'currency', 'bypass' => true, 'actions' => $crud('currency')],
                'master.pajak' => ['label' => 'Tarif Pajak', 'prefix' => 'master.pajak', 'subject' => 'tax setting', 'bypass' => true, 'actions' => $crud('tax setting')],
            ],
        ],

        'pengaturan' => [
            'label' => 'Pengaturan',
            'pages' => [
                'settings.users' => [
                    'label' => 'Pengguna', 'prefix' => 'settings.users', 'subject' => 'user', 'bypass' => true,
                    'actions' => array_merge($crud('user'), [
                        'update' => ['Ubah / Aktif-Nonaktif / Reset Password', ['edit', 'update', 'toggle-active', 'reset-password'], ['update user']],
                    ]),
                ],
                'settings.approval-rules' => [
                    'label' => 'Aturan Persetujuan', 'prefix' => 'settings.approval-rules', 'bypass' => true,
                    'actions' => [
                        'view' => ['Lihat', ['index']],
                        'create' => ['Tambah', ['create', 'store']],
                        'update' => ['Ubah', ['edit', 'update']],
                    ],
                ],
            ],
        ],
    ],
];
