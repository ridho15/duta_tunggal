<?php

namespace Database\Seeders\Finance;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class FinanceCustomerSupplierSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $customers = [
            [
                'code' => 'CUST-FIN-001',
                'name' => 'PT. Nusantara Retail',
                'perusahaan' => 'PT. Nusantara Retail',
                'email' => 'finance@nusaretail.id',
                'phone' => '021-555-0123',
                'telephone' => '021-555-0123',
                'address' => 'Jl. Merdeka No. 88, Jakarta',
                'tempo_kredit' => 45,
                'tipe' => 'PKP',
                'fax' => '021-555-0999',
                'nik_npwp' => '01.234.567.8-901.000',
                'tipe_pembayaran' => 'Kredit',
                'kredit_limit' => 500000000,
                'isSpecial' => true,
            ],
            [
                'code' => 'CUST-FIN-002',
                'name' => 'CV. Sinar Elektrik',
                'perusahaan' => 'CV. Sinar Elektrik',
                'email' => 'akunting@sinar-elektrik.co.id',
                'phone' => '031-7788-221',
                'telephone' => '031-7788-221',
                'address' => 'Jl. Raya Industri No. 5, Surabaya',
                'tempo_kredit' => 30,
                'tipe' => 'PKP',
                'fax' => '031-7788-229',
                'nik_npwp' => '02.345.678.9-012.000',
                'tipe_pembayaran' => 'Kredit',
                'kredit_limit' => 350000000,
                'isSpecial' => false,
            ],
            [
                'code' => 'CUST-FIN-003',
                'name' => 'PT. Prima Konstruksi',
                'perusahaan' => 'PT. Prima Konstruksi',
                'email' => 'finance@primakonstruksi.co.id',
                'phone' => '022-3355-4466',
                'telephone' => '022-3355-4466',
                'address' => 'Jl. Gatot Subroto No. 12, Bandung',
                'tempo_kredit' => 60,
                'tipe' => 'PKP',
                'fax' => '022-3355-4499',
                'nik_npwp' => '03.456.789.0-123.000',
                'tipe_pembayaran' => 'Kredit',
                'kredit_limit' => 600000000,
                'isSpecial' => false,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['code' => $customer['code']],
                $customer
            );
        }

        $suppliers = [
            [
                'code' => 'SUPP-FIN-001',
                'name' => 'PT. Bahan Baku Global',
                'perusahaan' => 'PT. Bahan Baku Global',
                'email' => 'order@bbglobal.co.id',
                'phone' => '021-9900-1122',
                'address' => 'Kawasan Industri MM2100, Cikarang',
                'tempo_hutang' => 45,
                'handphone' => '0812-9900-1122',
                'fax' => '021-9900-5599',
                'npwp' => '09.876.543.2-101.000',
                'kontak_person' => 'Bpk. Herman',
            ],
            [
                'code' => 'SUPP-FIN-002',
                'name' => 'CV. Precision Parts',
                'perusahaan' => 'CV. Precision Parts',
                'email' => 'sales@precisionparts.id',
                'phone' => '024-7788-9988',
                'address' => 'Jl. Pantura No. 101, Semarang',
                'tempo_hutang' => 30,
                'handphone' => '0813-7788-9988',
                'fax' => '024-7788-9977',
                'npwp' => '08.765.432.1-202.000',
                'kontak_person' => 'Ibu. Rina',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['code' => $supplier['code']],
                $supplier
            );
        }
    }
}
