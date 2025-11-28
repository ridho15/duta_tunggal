<?php

namespace Database\Seeders\Finance;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class FinanceChartOfAccountSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas & Setara Kas Induk'],
            ['code' => '1100', 'name' => 'Bank Utama'],
            ['code' => '1111', 'name' => 'Kas Operasional'],
            ['code' => '1111.01', 'name' => 'Kas Besar Kantor'],
            ['code' => '1112', 'name' => 'Rekening Bank'],
            ['code' => '1112.01', 'name' => 'Bank BCA - Operasional'],
            ['code' => '1113.01', 'name' => 'Deposito Bank Panin'],
            ['code' => '1200', 'name' => 'Piutang Usaha'],
            ['code' => '1300', 'name' => 'Persediaan Induk'],
            ['code' => '1140', 'name' => 'Persediaan Bahan Baku'],
            ['code' => '1140.01', 'name' => 'Persediaan Bahan Baku - Gudang Utama'],
            ['code' => '1150', 'name' => 'Barang Dalam Proses'],
            ['code' => '1210', 'name' => 'Harga Perolehan Aset Tetap'],
            ['code' => '1210.01', 'name' => 'Peralatan Produksi'],
            ['code' => '1220', 'name' => 'Akumulasi Penyusutan'],
            ['code' => '1220.01', 'name' => 'Akumulasi Penyusutan Peralatan Produksi', 'type' => 'Contra Asset'],
            ['code' => '2000', 'name' => 'Hutang Usaha'],
            ['code' => '2110', 'name' => 'Hutang Dagang'],
            ['code' => '2140', 'name' => 'Pinjaman Bank Jangka Pendek'],
            ['code' => '3000', 'name' => 'Modal Disetor'],
            ['code' => '3100', 'name' => 'Laba Ditahan'],
            ['code' => '4000', 'name' => 'Pendapatan Penjualan'],
            ['code' => '4100', 'name' => 'Pendapatan Penjualan Lokal'],
            ['code' => '5000', 'name' => 'Harga Pokok Penjualan'],
            ['code' => '5110', 'name' => 'Pembelian Bahan Baku'],
            ['code' => '5120', 'name' => 'Biaya Tenaga Kerja Langsung'],
            ['code' => '5130', 'name' => 'Overhead - Listrik Pabrik'],
            ['code' => '5140', 'name' => 'Overhead - Penyusutan Mesin'],
            ['code' => '5150', 'name' => 'Overhead - Perawatan Mesin'],
            ['code' => '6100.02', 'name' => 'Biaya Pengiriman Penjualan'],
            ['code' => '6220.01', 'name' => 'Biaya Asuransi Kesehatan'],
            ['code' => '6230.01', 'name' => 'Biaya Sewa Gudang'],
            ['code' => '6240.01', 'name' => 'Biaya Utilitas Kantor'],
            ['code' => '6250.01', 'name' => 'Biaya ATK'],
            ['code' => '6260.03', 'name' => 'Biaya Perawatan Kantor'],
            ['code' => '6270.03', 'name' => 'Biaya Konsultan'],
            ['code' => '6280.05', 'name' => 'Biaya Administrasi Umum Lainnya'],
            ['code' => '6311', 'name' => 'Biaya Penyusutan Peralatan'],
            ['code' => '7000.04', 'name' => 'Pendapatan Luar Usaha Lainnya'],
            ['code' => '8000.01', 'name' => 'Biaya Administrasi Bank'],
        ];

        usort($accounts, fn($a, $b) => strlen($a['code']) <=> strlen($b['code']));

        foreach ($accounts as $account) {
            $code = $account['code'];
            $coa = ChartOfAccount::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $account['name'],
                    'type' => $account['type'] ?? $this->context->inferAccountType($code),
                    'parent_id' => $this->context->resolveParentId($code),
                    'is_active' => true,
                ]
            );

            $this->context->storeCoa($coa);
        }

        $this->context->ensureOpeningBalance('1140.01', 42000000);
        $this->context->ensureOpeningBalance('1150', 9500000);
    }
}
