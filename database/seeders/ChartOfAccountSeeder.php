<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Note: Using updateOrCreate to handle existing data safely
        // No need to delete existing records as updateOrCreate will handle duplicates

        $assetAccounts = [
            '1000' => 'AKTIVA',
            '1100' => 'AKTIVA LANCAR',
            '1110' => 'KAS DAN SETARA KAS',
            '1111' => 'KAS',
            '1111.01' => 'KAS BESAR',
            '1111.02' => 'KAS KECIL',
            '1111.03' => 'KAS PENJUALAN',
            '1112' => 'BANK',
            '1112.01' => 'BANK BCA',
            '1112.01.01' => 'BANK BCA - OPERASIONAL',
            '1112.01.02' => 'BANK BCA - DEPOSITO',
            '1112.01.03' => 'BANK BCA - INVESTASI',
            '1112.02' => 'BANK MANDIRI',
            '1112.02.01' => 'BANK MANDIRI - OPERASIONAL',
            '1112.02.02' => 'BANK MANDIRI - DEPOSITO',
            '1112.02.03' => 'BANK MANDIRI - INVESTASI',
            '1112.03' => 'BANK PANIN (IDR) - 2197',
            '1112.03.01' => 'BANK PANIN (IDR) - 2197 - OPERASIONAL',
            '1112.03.02' => 'BANK PANIN (IDR) - 2197 - DEPOSITO',
            '1112.03.03' => 'BANK PANIN (IDR) - 2197 - INVESTASI',
            '1112.04' => 'BANK PANIN (IDR) - 2297',
            '1112.04.01' => 'BANK PANIN (IDR) - 2297 - OPERASIONAL',
            '1112.04.02' => 'BANK PANIN (IDR) - 2297 - DEPOSITO',
            '1112.04.03' => 'BANK PANIN (IDR) - 2297 - INVESTASI',
            '1112.05' => 'BANK PANIN (USD)',
            '1112.05.01' => 'BANK PANIN (USD) - OPERASIONAL',
            '1112.05.02' => 'BANK PANIN (USD) - DEPOSITO',
            '1112.05.03' => 'BANK PANIN (USD) - INVESTASI',
            '1113' => 'DEPOSITO',
            '1113.01' => 'DEPOSITO BANK PANIN',
            '1120' => 'PIUTANG DAGANG',
            '1121' => 'PIUTANG MARKET PLACE',
            '1121.01' => 'PIUTANG TOKOPEDIA',
            '1121.02' => 'PIUTANG SHOPEE',
            '1121.03' => 'PIUTANG BUKALAPAK',
            '1122' => 'PIUTANG GIRO MUNDUR',
            '1130' => 'PIUTANG LAIN-LAIN',
            '1130.01' => 'PIUTANG KARYAWAN',
            '1130.02' => 'PIUTANG PIHAK KE TIGA',
            '1130.03' => 'PIUTANG PEMEGANG SAHAM',
            '1130.04' => 'PIUTANG LAIN-LAIN',
            '1140' => 'PERSEDIAAN',
            '1140.01' => 'PERSEDIAAN BARANG DAGANGAN',
            '1140.02' => 'PERSEDIAAN BARANG PRODUKSI',
            '1140.10' => 'PERSEDIAAN BARANG DAGANGAN - DEFAULT PRODUK',
            '1140.20' => 'BARANG TERKIRIM',
            // HPP Inventory Accounts
            '1-101' => 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY',
            '1-102' => 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY 2',
            '1-201' => 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY',
            '1-202' => 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY 2',
            '1150' => 'UANG MUKA',
            '1150.01' => 'UANG MUKA PEMBELIAN BARANG DAGANG',
            '1150.02' => 'UANG MUKA PEMBELIAN NON BARANG DAGANG',
            '1150.03' => 'UANG MUKA LAINNYA',
            '1160' => 'BIAYA DIBAYAR DIMUKA',
            '1160.01' => 'SEWA DIBAYAR DIMUKA',
            '1160.02' => 'ASURANSI DIBAYAR DIMUKA',
            '1160.03' => 'THR DIBAYAR DIMUKA',
            '1160.04' => 'PERBAIKAN DIBAYAR DIMUKA',
            '1160.05' => 'BIAYA DIBAYAR DIMUKA LAINNYA',
            '1170' => 'PAJAK DIBAYAR DIMUKA',
            '1170.01' => 'PPH 21',
            '1170.02' => 'PPH 22',
            '1170.03' => 'PPH 23',
            '1170.04' => 'PPH 4 (2)',
            '1170.05' => 'PPH 25',
            '1170.06' => 'PPN MASUKAN',
            '1180' => 'AKTIVA LANCAR LAINNYA',
            '1180.01' => 'BARANG DALAM PERJALANAN',
            '1180.10' => 'BARANG TERKIRIM - DEFAULT PRODUK',
            '1200' => 'AKTIVA TETAP',
            '1210' => 'HARGA PEROLEHAN ASET TETAP',
            '1210.01' => 'PERALATAN KANTOR (OE)',
            '1210.02' => 'PERLENGKAPAN KANTOR (FF)',
            '1210.03' => 'KENDARAAN',
            '1210.04' => 'BANGUNAN',
            '1210.05' => 'TANAH',
            '1230' => 'ASET TA (TAX AMNESTY)',
            '1240' => 'PEMBANGUNAN DALAM PROSES',
            '1250' => 'AKTIVA TETAP LAINNYA',
            '1300' => 'AYAT SILANG',
            '1300.01' => 'AYAT SILANG KAS DAN KAS',
            '1300.02' => 'AYAT SILANG KAS DAN BANK',
            '1300.03' => 'AYAT SILANG BANK DAN BANK',
            '1400' => 'AKUN SEMENTARA',
            '1400.01' => 'POS SEMENTARA PENGADAAN',
            '1400.02' => 'POS SEMENTARA PENJUALAN',
            '1400.03' => 'POS SEMENTARA PEMBELIAN',
            '1400.04' => 'POS SEMENTARA PRODUKSI',
            '1400.05' => 'POS SEMENTARA INVENTORI',
            '1400.06' => 'POS SEMENTARA LAINNYA',
        ];

        $contraAssetAccounts = [
            '1220' => 'AKUMULASI BIAYA PENYUSUTAN ASET TETAP',
            '1220.01' => 'AKUMULASI BIAYA PENYUSUTAN PERALATAN KANTOR (OE)',
            '1220.02' => 'AKUMULASI BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (FF)',
            '1220.03' => 'AKUMULASI BIAYA PENYUSUTAN KENDARAAN',
            '1220.04' => 'AKUMULASI BIAYA PENYUSUTAN BANGUNAN',
        ];

        $liabilityAccounts = [
            '2000' => 'HUTANG',
            '2100' => 'HUTANG LANCAR',
            '2110' => 'HUTANG DAGANG',
            '2120' => 'HUTANG PAJAK',
            '2120.01' => 'HUTANG PPH 21',
            '2120.02' => 'HUTANG PPH 23',
            '2120.03' => 'HUTANG PPH 4 (2)',
            '2120.04' => 'HUTANG PPH 25',
            '2120.05' => 'HUTANG PPH 29',
            '2120.06' => 'PPN KELUARAN',
            '2130' => 'UANG MUKA PENJUALAN',
            '2140' => 'HUTANG BANK',
            '2150' => 'HUTANG LEASING',
            '2160' => 'HUTANG LAIN LAIN',
            '2160.01' => 'HUTANG KARYAWAN',
            '2160.02' => 'HUTANG PIHAK KE TIGA',
            '2160.03' => 'HUTANG PEMEGANG SAHAM',
            '2160.04' => 'HUTANG TITIPAN KONSUMEN',
            '2170' => 'BIAYA YANG MASIH HARUS DIBAYAR',
            '2170.01' => 'BIAYA YMHDB - PENGIRIMAN / PENGANGKUTAN',
            '2170.02' => 'BIAYA YMHDB - BONGKAR MUAT',
            '2170.03' => 'BIAYA YMHDB - TRANSPORTASI / PERJALANAN DINAS',
            '2170.04' => 'BIAYA YMHDB - PROMOSI PENJUALAN',
            '2170.05' => 'BIAYA YMHDB - KOMISI PENJUALAN',
            '2170.06' => 'BIAYA YMHDB - ALAT PERKAKAS DAN PROSES',
            '2170.07' => 'BIAYA YMHDB - GAJI',
            '2170.08' => 'BIAYA YMHDB - THR',
            '2170.09' => 'BIAYA YMHDB - BPJS',
            '2170.10' => 'BIAYA YMHDB - SEWA',
            '2170.11' => 'BIAYA YMHDB - ASURANSI',
            '2170.12' => 'BIAYA YMHDB - SISTEM TEKNOLOGI DAN IT',
            '2170.13' => 'BIAYA YMHDB - KEPERLUAN KANTOR',
            '2170.14' => 'BIAYA YMHDB - PERBAIKAN / PEMELIHARAAN',
            '2170.15' => 'BIAYA YMHDB - NOTARIS',
            '2170.16' => 'BIAYA YMHDB - KONSULTAN',
            '2170.17' => 'BIAYA YMHDB - AUDITOR',
            '2170.18' => 'BIAYA YMHDB - BUNGA PINJAMAN',
            '2170.19' => 'BIAYA YMHDB - LAIN-LAIN',
            '2200' => 'HUTANG JANGKA PANJANG',
            '2210' => 'HUTANG IMBALAN PASCA KERJA',
            '2220' => 'HUTANG JANGKA PANJANG LAINNYA',
            '2190' => 'KEWAJIBAN PEMBELIAN LAINNYA',
            '2190.10' => 'PEMBELIAN BELUM TERTAGIH - DEFAULT PRODUK',
            // Penerimaan barang belum tertagih: liability under 2100 (Hutang Lancar)
            '2100.10' => 'PENERIMAAN BARANG BELUM TERTAGIH',
        ];

        $equityAccounts = [
            '3000' => 'EKUITAS',
            '3100' => 'MODAL DISETOR',
            '3200' => 'MODAL DISETOR LAINNYA',
            '3300' => 'DEVIDEN',
            '3400' => 'LABA DITAHAN',
            '3500' => 'LABA TAHUN BERJALAN',
        ];

        $expenseAccounts = [
            '5000' => 'HPP',
            '5100' => 'HPP / HARGA POKOK PEMBELIAN BARANG DAGANGAN',
            '5100.10' => 'HPP BARANG DAGANGAN - DEFAULT PRODUK',
            '5110' => 'POTONGAN PEMBELIAN BARANG DAGANGAN',
            '5120' => 'RETUR PEMBELIAN BARANG DAGANGAN',
            '5120.10' => 'RETUR PEMBELIAN BARANG DAGANGAN - DEFAULT PRODUK',
            '5130' => 'BEA MASUK',
            '5200' => 'HPP / HARGA POKOK PEMBELIAN BARANG PRODUKSI',
            '5210' => 'POTONGAN PEMBELIAN BARANG DAGANGAN',
            '5220' => 'RETUR PEMBELIAN BARANG DAGANGAN',
            '5230' => 'BIAYA TENAGA KERJA PROSES PRODUKSI',
            '5240' => 'BIAYA TENAGA KERJA LUAR PROSES PRODUKSI',
            // HPP Expense Accounts
            '5-101' => 'PEMBELIAN BAHAN BAKU - RAW MATERIAL PURCHASE',
            '5-102' => 'PEMBELIAN BAHAN BAKU - RAW MATERIAL PURCHASE 2',
            '6-201' => 'BIAYA TENAGA KERJA LANGSUNG - DIRECT LABOR',
            '6-202' => 'BIAYA TENAGA KERJA LANGSUNG - DIRECT LABOR 2',
            '6000' => 'BIAYA',
            '6100' => 'BIAYA PENJUALAN',
            '6100.01' => 'BIAYA PACKING',
            '6100.02' => 'BIAYA PENGIRIMAN / PENGANGKUTAN',
            '6100.03' => 'BIAYA BONGKAR MUAT',
            '6100.04' => 'BIAYA TRANSPORTASI / PERJALANAN DINAS',
            '6100.05' => 'BIAYA BAHAN BAKAR',
            '6100.06' => 'BIAYA PARKIR & TOL',
            '6100.07' => 'BIAYA PROMOSI PENJUALAN',
            '6100.08' => 'BIAYA ENTERTAINMENT',
            '6100.09' => 'BIAYA PENJUALAN LAINNYA',
            '6100.10' => 'BIAYA KOMISI PENJUALAN',
            '6100.11' => 'BIAYA PIUTANG TIDAK TERTAGIH',
            '6100.12' => 'BIAYA ALAT PERKAKAS DAN PROSES',
            '6200' => 'BIAYA ADM & UMUM',
            '6210' => 'BIAYA KARYAWAN',
            '6210.01' => 'BIAYA GAJI',
            '6210.02' => 'BIAYA THR',
            '6210.03' => 'BIAYA PESANGON',
            '6210.04' => 'BIAYA UANG MAKAN',
            '6210.05' => 'BIAYA PENGOBATAN',
            '6210.06' => 'BIAYA KARYAWAN LAINNYA',
            '6220' => 'BIAYA ASURANSI',
            '6220.01' => 'BIAYA ASURANSI KESEHATAN',
            '6220.02' => 'BIAYA ASURANSI KENDARAAN',
            '6220.03' => 'BIAYA ASURANSI KANTOR / GEDUNG / GUDANG',
            '6220.04' => 'BIAYA BPJS KESEHATAN',
            '6220.05' => 'BIAYA BPJS TENAGA KERJA',
            '6220.06' => 'BIAYA ASURANSI LAINNYA',
            '6230' => 'BIAYA SEWA',
            '6230.01' => 'BIAYA SEWA KANTOR / GEDUNG / GUDANG',
            '6230.02' => 'BIAYA SEWA KENDARAAN',
            '6230.03' => 'BIAYA SEWA MESIN FOTOCOPY',
            '6230.04' => 'BIAYA SEWA MESIN',
            '6230.05' => 'BIAYA SEWA LAINNYA',
            '6240' => 'BIAYA LGAT',
            '6240.01' => 'BIAYA LISTRIK',
            '6240.02' => 'BIAYA TELEPON',
            '6240.03' => 'BIAYA AIR PDAM',
            '6240.04' => 'BIAYA INTERNET',
            '6250' => 'BIAYA PERLENGKAPAN KANTOR',
            '6250.01' => 'BIAYA ATK',
            '6250.02' => 'BIAYA FOTOCOPY',
            '6250.03' => 'BIAYA PERCETAKAN',
            '6250.04' => 'BIAYA METERAI /  BENDA POS',
            '6250.05' => 'BIAYA PERLENGKAPAN KANTOR LAINNYA',
            '6250.06' => 'BIAYA SISTEM TEKNOLOGI DAN IT',
            '6260' => 'BIAYA KEPERLUAN KANTOR',
            '6260.01' => 'BIAYA KIRIM DOKUMEN',
            '6260.02' => 'BIAYA RUMAH TANGGA KANTOR',
            '6260.03' => 'BIAYA PERBAIKAN / PEMELIHARAAN KANTOR / GEDUNG / GUDANG',
            '6260.04' => 'BIAYA PERBAIKAN / PEMELIHARAAN KENDARAAN',
            '6260.05' => 'BIAYA PERBAIKAN / PEMELIHARAAN INVENTARIS KANTOR',
            '6260.06' => 'BIAYA KEAMANAN DAN KEBERSIHAN',
            '6260.07' => 'BIAYA KIR / PAJAK / STNK',
            '6260.08' => 'BIAYA KEPERLUAN KANTOR LAINNYA',
            '6270' => 'BIAYA JASA KHUSUS',
            '6270.01' => 'BIAYA NOTARIS',
            '6270.02' => 'BIAYA APPRAISAL / PENILAI',
            '6270.03' => 'BIAYA KONSULTAN',
            '6270.04' => 'BIAYA JASA KHUSUS LAINNYA',
            '6270.05' => 'BIAYA AUDITOR',
            '6280' => 'BIAYA ADMINISTRASI UMUM LAINNYA',
            '6280.01' => 'BIAYA PAJAK',
            '6280.02' => 'BIAYA SUMBANGAN',
            '6280.03' => 'BIAYA PBB',
            '6280.04' => 'BIAYA IMBALAN PASCA KERJA',
            '6280.05' => 'BIAYA ADM UMUM LAINNYA',
            '6300' => 'BIAYA PENYUSUTAN DAN AMORTISASI',
            '6310' => 'BIAYA PENYUSUTAN ASET TETAP',
            '6311' => 'BIAYA PENYUSUTAN PERALATAN KANTOR (OE)',
            '6312' => 'BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (OE)',
            '6313' => 'BIAYA PENYUSUTAN KENDARAAN',
            '6314' => 'BIAYA PENYUSUTAN BANGUNAN',
            '6320' => 'BIAYA AMORTISASI',
            '6320.01' => 'BIAYA AMORTISASI LAINNYA',
            '8000' => 'BIAYA DILUAR USAHA',
            '8000.01' => 'BIAYA ADMINISTRASI BANK',
            '8000.02' => 'BIAYA PAJAK BUNGA JASA GIRO',
            '8000.03' => 'BIAYA PAJAK BUNGA DEPOSITO',
            '8000.04' => 'BIAYA BUNGA PINJAMAN',
            '8000.05' => 'BIAYA LAINNYA',
            '8000.06' => 'BIAYA PAJAK HADIAH / UNDIAN',
            '9000' => 'LAINNYA',
            '9000.01' => 'SELISIH KURS',
            '9000.02' => 'PEMBULATAN',
            '9100' => 'PAJAK PENGHASILAN',
        ];

        // Revenue accounts (explicitly grouped)
        $revenueAccounts = [
            '4000' => 'PENJUALAN',
            '4100' => 'PENJUALAN BARANG DAGANGAN',
            '4100.10' => 'PENJUALAN BARANG DAGANGAN - DEFAULT PRODUK',
            '4110' => 'POTONGAN PENJUALAN BARANG DAGANGAN',
            '4110.10' => 'POTONGAN PENJUALAN BARANG DAGANGAN - DEFAULT PRODUK',
            '4120' => 'RETUR PENJUALAN BARANG DAGANGAN',
            '4120.10' => 'RETUR PENJUALAN BARANG DAGANGAN - DEFAULT PRODUK',
            '4200' => 'PENJUALAN BARANG PRODUKSI',
            '4210' => 'POTONGAN PENJUALAN BARANG PRODUKSI',
            '4220' => 'RETUR PENJUALAN BARANG PRODUKSI',
            '7000' => 'PENDAPATAN DILUAR USAHA',
            '7000.01' => 'PENDAPATAN BUNGA JASA GIRO',
            '7000.02' => 'PENDAPATAN BUNGA DEPOSITO',
            '7000.03' => 'PENDAPATAN ATAS PENJUALAN AKTIVA',
            '7000.04' => 'PENDAPATAN LAINNYA',
            '7000.05' => 'PENDAPATAN HADIAH / UNDIAN',
        ];

        // Group accounts by the financial group to make seeding explicit and clear
        $groups = [
            'Asset' => $assetAccounts,
            'Contra Asset' => $contraAssetAccounts,
            'Liability' => $liabilityAccounts,
            'Equity' => $equityAccounts,
            'Revenue' => $revenueAccounts,
            'Expense' => $expenseAccounts,
        ];

        // Build flat account list and explicit type mapping (so seeder honors groups)
        $accounts = [];
        $explicitTypes = [];
        foreach ($groups as $groupName => $list) {
            foreach ($list as $code => $name) {
                $accounts[$code] = $name;
                $explicitTypes[$code] = $groupName;
            }
        }

        // Sort accounts by code length then alphabetically to ensure parents are created first
        $sortedCodes = array_keys($accounts);
        usort($sortedCodes, function($a, $b) {
            return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
        });

        $createdAccounts = [];

        foreach ($sortedCodes as $code) {
            $name = $accounts[$code];
            $parentId = $this->findParentId($code, $createdAccounts);
            // Prefer explicit group/type if provided; fall back to heuristic
            $type = $explicitTypes[$code] ?? $this->getAccountType($code);

            $coa = ChartOfAccount::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]
            );

            $createdAccounts[$code] = $coa->id;
        }
    }

    private function findParentId($code, $createdAccounts)
    {
        // If code has dot (like 1111.01), parent is the 4-digit code before dot (1111)
        if (strpos($code, '.') !== false) {
            $parentCode = explode('.', $code)[0];
            return isset($createdAccounts[$parentCode]) ? $createdAccounts[$parentCode] : null;
        }

        // For 4-digit codes, find parent by changing trailing digits to 0 progressively
        // This ensures proper hierarchy: 1111 -> 1110 -> 1100 -> 1000
        $length = strlen($code);
        for ($i = $length - 1; $i >= 1; $i--) {
            $potentialParent = substr($code, 0, $i) . str_repeat('0', $length - $i);
            if (isset($createdAccounts[$potentialParent])) {
                return $createdAccounts[$potentialParent];
            }
        }

        return null;
    }

    private function getAccountType($code)
    {
        // Special case for Contra Asset accounts (Accumulated Depreciation)
        if (strpos($code, '1220') === 0) {
            return 'Contra Asset';
        }
        
        $firstDigit = substr($code, 0, 1);
        
        return match ($firstDigit) {
            '1' => 'Asset',
            '2' => 'Liability',
            '3' => 'Equity',
            '4', '7' => 'Revenue',
            '5', '6', '8', '9' => 'Expense',
            default => 'Asset',
        };
    }
}
