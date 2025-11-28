<?php

namespace Database\Seeders\Finance;

use App\Models\CashBankTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceCashBankTransactionSeeder extends Seeder
{
    public function __construct(
        private FinanceSeedContext $context,
        private array $salesContext = [],
        private array $purchaseContext = []
    ) {
    }

    public function run(): void
    {
        $bankAccount = $this->context->getCoa('1112.01') ?? $this->context->getCoa('1112');
        $cashAccount = $this->context->getCoa('1111.01') ?? $this->context->getCoa('1111');
        $receivableAccount = $this->context->getCoa('1120');

        if (!$bankAccount || !$cashAccount || !$receivableAccount) {
            return;
        }

        $transactions = [
            [
                'number' => 'CBT-SEED-AR-001',
                'date' => Carbon::now()->subDays(14),
                'type' => 'bank_in',
                'account' => $bankAccount,
                'offset' => $receivableAccount,
                'amount' => 76500000,
                'counterparty' => 'PT. Nusantara Retail',
                'description' => 'Pelunasan lanjutan invoice INV-AR-001',
            ],
            [
                'number' => 'CBT-SEED-AR-002',
                'date' => Carbon::now()->subDays(18),
                'type' => 'bank_in',
                'account' => $bankAccount,
                'offset' => $receivableAccount,
                'amount' => 90000000,
                'counterparty' => 'PT. Nusantara Retail',
                'description' => 'Pembayaran pertama invoice INV-AR-001',
            ],
            [
                'number' => 'CBT-SEED-AR-003',
                'date' => Carbon::now()->subDays(40),
                'type' => 'bank_in',
                'account' => $bankAccount,
                'offset' => $receivableAccount,
                'amount' => 60000000,
                'counterparty' => 'CV. Sinar Elektrik',
                'description' => 'Pembayaran sebagian invoice INV-AR-002',
            ],
            [
                'number' => 'CBT-SEED-EXP-6100',
                'date' => Carbon::now()->subDays(12),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6100.02',
                'amount' => 1750000,
                'counterparty' => 'Jasa Ekspedisi Nusantara',
                'description' => 'Biaya pengiriman pesanan pelanggan',
            ],
            [
                'number' => 'CBT-SEED-EXP-6220',
                'date' => Carbon::now()->subDays(10),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6220.01',
                'amount' => 820000,
                'counterparty' => 'PT. Asuransi Sentosa',
                'description' => 'Premi asuransi kesehatan karyawan',
            ],
            [
                'number' => 'CBT-SEED-EXP-6230',
                'date' => Carbon::now()->subDays(9),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6230.01',
                'amount' => 5200000,
                'counterparty' => 'PT. Gudang Sejahtera',
                'description' => 'Pembayaran sewa gudang bulan berjalan',
            ],
            [
                'number' => 'CBT-SEED-EXP-6240',
                'date' => Carbon::now()->subDays(8),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6240.01',
                'amount' => 2650000,
                'counterparty' => 'PLN & ISP',
                'description' => 'Pembayaran listrik dan internet kantor',
            ],
            [
                'number' => 'CBT-SEED-EXP-6250',
                'date' => Carbon::now()->subDays(7),
                'type' => 'cash_out',
                'account' => $cashAccount,
                'offset_code' => '6250.01',
                'amount' => 450000,
                'counterparty' => 'Toko ATK Sentosa',
                'description' => 'Pembelian perlengkapan kantor tunai',
            ],
            [
                'number' => 'CBT-SEED-EXP-6260',
                'date' => Carbon::now()->subDays(6),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6260.03',
                'amount' => 3100000,
                'counterparty' => 'CV. Service Prima',
                'description' => 'Perawatan AC & genset kantor',
            ],
            [
                'number' => 'CBT-SEED-EXP-6270',
                'date' => Carbon::now()->subDays(5),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6270.03',
                'amount' => 4500000,
                'counterparty' => 'Konsultan Pajak Mitra Abadi',
                'description' => 'Jasa konsultasi pajak bulanan',
            ],
            [
                'number' => 'CBT-SEED-EXP-6280',
                'date' => Carbon::now()->subDays(4),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '6280.05',
                'amount' => 2250000,
                'counterparty' => 'PT. Multi Administrasi',
                'description' => 'Biaya administrasi dan legalitas',
            ],
            [
                'number' => 'CBT-SEED-EXP-8000',
                'date' => Carbon::now()->subDays(3),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '8000.01',
                'amount' => 185000,
                'counterparty' => 'Bank BCA',
                'description' => 'Biaya administrasi bank',
            ],
            [
                'number' => 'CBT-SEED-INC-7000',
                'date' => Carbon::now()->subDays(2),
                'type' => 'bank_in',
                'account' => $bankAccount,
                'offset_code' => '7000.04',
                'amount' => 7200000,
                'counterparty' => 'PT. Lelang Mandiri',
                'description' => 'Pendapatan penjualan scrap mesin',
            ],
            [
                'number' => 'CBT-SEED-ASSET-1210',
                'date' => Carbon::now()->subDays(20),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '1210.01',
                'amount' => 50000000,
                'counterparty' => 'PT. Mesin Prima',
                'description' => 'Pembayaran uang muka mesin produksi baru',
            ],
            [
                'number' => 'CBT-SEED-FIN-2140-IN',
                'date' => Carbon::now()->subDays(30),
                'type' => 'bank_in',
                'account' => $bankAccount,
                'offset_code' => '2140',
                'amount' => 100000000,
                'counterparty' => 'Bank Mandiri',
                'description' => 'Pencairan fasilitas kredit modal kerja',
            ],
            [
                'number' => 'CBT-SEED-FIN-2140-OUT',
                'date' => Carbon::now()->subDays(9),
                'type' => 'bank_out',
                'account' => $bankAccount,
                'offset_code' => '2140',
                'amount' => 25000000,
                'counterparty' => 'Bank Mandiri',
                'description' => 'Angsuran pinjaman modal kerja',
            ],
        ];

        foreach ($transactions as $row) {
            $offset = $row['offset'] ?? $this->context->getCoa($row['offset_code'] ?? '');
            if (!$offset) {
                continue;
            }

            CashBankTransaction::updateOrCreate(
                ['number' => $row['number']],
                [
                    'date' => $row['date']->toDateString(),
                    'type' => $row['type'],
                    'account_coa_id' => $row['account']->id,
                    'offset_coa_id' => $offset->id,
                    'amount' => $row['amount'],
                    'counterparty' => $row['counterparty'],
                    'description' => $row['description'],
                    'cabang_id' => $this->context->ensureCabang()->id,
                ]
            );
        }
    }
}
