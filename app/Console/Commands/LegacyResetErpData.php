<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyResetErpData extends Command
{
    protected $signature = 'legacy:reset-erp-data
        {--force : Jalankan reset tanpa konfirmasi interaktif}
        {--keep-sessions : Pertahankan tabel sessions}
        {--prepare-import : Buat cabang dan gudang minimal untuk import setelah reset}';

    protected $description = 'Kosongkan tabel domain ERP dan pertahankan tabel akses (users/roles/permissions) untuk persiapan import legacy';

    private array $preserveTables = [
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'password_reset_tokens',
        'migrations',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Perintah ini akan menghapus data master, transaksi, gudang, dan konfigurasi domain ERP. Lanjutkan?')) {
            $this->warn('Reset dibatalkan.');
            return self::INVALID;
        }

        $preserve = $this->preserveTables;
        if ($this->option('keep-sessions')) {
            $preserve[] = 'sessions';
        }

        $allTables = array_map(
            fn ($row) => $row->name,
            DB::select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")
        );

        $truncateTables = array_values(array_diff($allTables, $preserve));

        if (! $truncateTables) {
            $this->warn('Tidak ada tabel domain yang perlu dikosongkan.');
            return self::SUCCESS;
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($truncateTables as $table) {
                DB::statement("TRUNCATE TABLE `{$table}`");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $throwable) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Reset ERP gagal: ' . $throwable->getMessage());
            return self::FAILURE;
        }

        $mapping = null;

        if ($this->option('prepare-import')) {
            $mapping = $this->prepareImportTargets();
        }

        $this->info('Reset ERP selesai.');
        $this->table(['Type', 'Name'], array_map(fn ($table) => ['truncated', $table], $truncateTables));

        if ($mapping) {
            $this->newLine();
            $this->info('Target import minimal berhasil dibuat.');
            $this->table(
                ['Key', 'Value'],
                [
                    ['access_cabang_id', $mapping['access_cabang_id']],
                    ['access_warehouse_id', $mapping['access_warehouse_id']],
                    ['inventory_cabang_id', $mapping['inventory_cabang_id']],
                    ['inventory_warehouse_id', $mapping['inventory_warehouse_id']],
                    ['inventory_cab_cabang_id', $mapping['inventory_cab_cabang_id']],
                    ['inventory_cab_warehouse_id', $mapping['inventory_cab_warehouse_id']],
                ]
            );
        }

        return self::SUCCESS;
    }

    private function prepareImportTargets(): array
    {
        $now = now();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('cabangs')->truncate();
        DB::table('warehouses')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $accessCabangId = DB::table('cabangs')->insertGetId([
            'kode' => 'CBG-ACCESS',
            'nama' => 'Cabang Akses Sistem',
            'alamat' => 'Default access branch after legacy reset',
            'telepon' => '-',
            'kenaikan_harga' => 0,
            'status' => 1,
            'warna_background' => '#1f2937',
            'tipe_penjualan' => 'Semua',
            'kode_invoice_pajak' => null,
            'kode_invoice_non_pajak' => null,
            'kode_invoice_pajak_walkin' => null,
            'nama_kwitansi' => 'Cabang Akses Sistem',
            'label_invoice_pajak' => 'Cabang Akses Sistem',
            'label_invoice_non_pajak' => 'Cabang Akses Sistem',
            'logo_invoice_non_pajak' => null,
            'lihat_stok_cabang_lain' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $inventoryCabangId = DB::table('cabangs')->insertGetId([
            'kode' => 'CBG-LEG-INV',
            'nama' => 'Cabang Import Inventory',
            'alamat' => 'Target import source inventory',
            'telepon' => '-',
            'kenaikan_harga' => 0,
            'status' => 1,
            'warna_background' => '#0f766e',
            'tipe_penjualan' => 'Semua',
            'kode_invoice_pajak' => null,
            'kode_invoice_non_pajak' => null,
            'kode_invoice_pajak_walkin' => null,
            'nama_kwitansi' => 'Cabang Import Inventory',
            'label_invoice_pajak' => 'Cabang Import Inventory',
            'label_invoice_non_pajak' => 'Cabang Import Inventory',
            'logo_invoice_non_pajak' => null,
            'lihat_stok_cabang_lain' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $inventoryCabCabangId = DB::table('cabangs')->insertGetId([
            'kode' => 'CBG-LEG-CAB',
            'nama' => 'Cabang Import Inventory CAB',
            'alamat' => 'Target import source inventory_cab',
            'telepon' => '-',
            'kenaikan_harga' => 0,
            'status' => 1,
            'warna_background' => '#7c2d12',
            'tipe_penjualan' => 'Semua',
            'kode_invoice_pajak' => null,
            'kode_invoice_non_pajak' => null,
            'kode_invoice_pajak_walkin' => null,
            'nama_kwitansi' => 'Cabang Import Inventory CAB',
            'label_invoice_pajak' => 'Cabang Import Inventory CAB',
            'label_invoice_non_pajak' => 'Cabang Import Inventory CAB',
            'logo_invoice_non_pajak' => null,
            'lihat_stok_cabang_lain' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $accessWarehouseId = DB::table('warehouses')->insertGetId([
            'kode' => 'WH-ACCESS',
            'name' => 'Gudang Akses Sistem',
            'cabang_id' => $accessCabangId,
            'location' => 'Default warehouse for retained user access',
            'telepon' => '-',
            'tipe' => 'Besar',
            'status' => 1,
            'warna_background' => '#111827',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $inventoryWarehouseId = DB::table('warehouses')->insertGetId([
            'kode' => 'WH-LEG-INV',
            'name' => 'Gudang Import Inventory',
            'cabang_id' => $inventoryCabangId,
            'location' => 'Warehouse target for legacy inventory source',
            'telepon' => '-',
            'tipe' => 'Besar',
            'status' => 1,
            'warna_background' => '#115e59',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $inventoryCabWarehouseId = DB::table('warehouses')->insertGetId([
            'kode' => 'WH-LEG-CAB',
            'name' => 'Gudang Import Inventory CAB',
            'cabang_id' => $inventoryCabCabangId,
            'location' => 'Warehouse target for legacy inventory_cab source',
            'telepon' => '-',
            'tipe' => 'Besar',
            'status' => 1,
            'warna_background' => '#9a3412',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('users')->update([
            'cabang_id' => $accessCabangId,
            'warehouse_id' => $accessWarehouseId,
            'updated_at' => $now,
        ]);

        return [
            'access_cabang_id' => $accessCabangId,
            'access_warehouse_id' => $accessWarehouseId,
            'inventory_cabang_id' => $inventoryCabangId,
            'inventory_warehouse_id' => $inventoryWarehouseId,
            'inventory_cab_cabang_id' => $inventoryCabCabangId,
            'inventory_cab_warehouse_id' => $inventoryCabWarehouseId,
        ];
    }
}