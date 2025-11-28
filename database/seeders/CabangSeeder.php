<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding cabang data...');

        $faker = \Faker\Factory::create('id_ID');

        // Define static cabang data for consistent seeding
        $staticCabangs = [
            [
                'kode' => 'CBG-001',
                'nama' => 'Cabang Pusat Jakarta',
                'alamat' => 'Jl. Sudirman No. 1, Jakarta Pusat',
                'telepon' => '021-1234567',
                'kenaikan_harga' => 0,
                'status' => true,
                'warna_background' => '#007bff',
                'tipe_penjualan' => 'Semua',
                'kode_invoice_pajak' => 'INV-PJK-001',
                'kode_invoice_non_pajak' => 'INV-NPJK-001',
                'kode_invoice_pajak_walkin' => 'INV-WPJK-001',
                'nama_kwitansi' => 'Kwitansi Cabang Pusat',
                'label_invoice_pajak' => 'Faktur Pajak',
                'label_invoice_non_pajak' => 'Faktur Non Pajak',
                'lihat_stok_cabang_lain' => true,
            ],
            [
                'kode' => 'CBG-002',
                'nama' => 'Cabang Surabaya',
                'alamat' => 'Jl. Tunjungan No. 45, Surabaya',
                'telepon' => '031-7654321',
                'kenaikan_harga' => 5.0,
                'status' => true,
                'warna_background' => '#28a745',
                'tipe_penjualan' => 'Semua',
                'kode_invoice_pajak' => 'INV-PJK-002',
                'kode_invoice_non_pajak' => 'INV-NPJK-002',
                'kode_invoice_pajak_walkin' => 'INV-WPJK-002',
                'nama_kwitansi' => 'Kwitansi Cabang Surabaya',
                'label_invoice_pajak' => 'Faktur Pajak',
                'label_invoice_non_pajak' => 'Faktur Non Pajak',
                'lihat_stok_cabang_lain' => false,
            ],
        ];

        // Seed static cabangs first
        $created = 0;
        foreach ($staticCabangs as $cabangData) {
            $cabang = \App\Models\Cabang::updateOrCreate(
                ['kode' => $cabangData['kode']],
                $cabangData
            );

            if ($cabang->wasRecentlyCreated) {
                $created++;
            }
        }

        // Generate additional random cabangs if needed
        $targetTotal = 20;
        $currentTotal = \App\Models\Cabang::count();

        if ($currentTotal < $targetTotal) {
            $remaining = $targetTotal - $currentTotal;
            $additionalCreated = 0;
            $attempts = 0;
            $maxAttempts = $remaining * 2; // Allow some retries for unique kode

            while ($additionalCreated < $remaining && $attempts < $maxAttempts) {
                $attempts++;

                // Generate unique codes outside the array to avoid faker conflicts
                $kode = 'CBG-' . $faker->unique()->numerify('###');
                $kodeInvoicePajak = 'INV-PJK-' . $faker->unique()->numerify('###');
                $kodeInvoiceNonPajak = 'INV-NPJK-' . $faker->unique()->numerify('###');
                $kodeInvoicePajakWalkin = 'INV-WPJK-' . $faker->unique()->numerify('###');

                $cabang = \App\Models\Cabang::updateOrCreate(
                    ['kode' => $kode],
                    [
                        'nama' => 'Cabang ' . $faker->city,
                        'alamat' => $faker->address,
                        'telepon' => $faker->phoneNumber,
                        'kenaikan_harga' => $faker->randomFloat(2, 0, 20),
                        'status' => $faker->boolean(80), // 80% active
                        'warna_background' => $faker->safeHexColor,
                        'tipe_penjualan' => $faker->randomElement(['Semua', 'Pajak', 'Non Pajak']),
                        'kode_invoice_pajak' => $kodeInvoicePajak,
                        'kode_invoice_non_pajak' => $kodeInvoiceNonPajak,
                        'kode_invoice_pajak_walkin' => $kodeInvoicePajakWalkin,
                        'nama_kwitansi' => 'Kwitansi ' . $faker->company,
                        'label_invoice_pajak' => 'Pajak ' . $faker->word,
                        'label_invoice_non_pajak' => 'Non Pajak ' . $faker->word,
                        'logo_invoice_non_pajak' => null,
                        'lihat_stok_cabang_lain' => $faker->boolean(30), // 30% can see other branch stock
                    ]
                );

                if ($cabang->wasRecentlyCreated) {
                    $additionalCreated++;
                }
            }

            $created += $additionalCreated;
        }

        $this->command->info("Successfully seeded cabang data. Created/updated {$created} records. Total cabang: " . \App\Models\Cabang::count());
    }
}
