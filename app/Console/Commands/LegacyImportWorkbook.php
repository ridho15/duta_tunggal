<?php

namespace App\Console\Commands;

use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LegacyImportWorkbook extends Command
{
    private const SHEET_ORDER = ['categories', 'uoms', 'customers', 'suppliers', 'products', 'stocks'];

    protected $signature = 'legacy:import-workbook
        {file : Path workbook .xlsx yang digenerate oleh legacy:export-import-workbooks}
        {--only= : Sheet comma-separated: categories,uoms,customers,suppliers,products,stocks}
        {--execute : Jalankan upsert ke database ERP. Tanpa flag ini command hanya dry-run}';

    protected $description = 'Import workbook Excel legacy yang sudah dinormalisasi ke database ERP saat ini';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $path = $this->resolvePath((string) $this->argument('file'));
        $execute = (bool) $this->option('execute');
        $steps = $this->normalizeSteps($this->option('only'));

        if (! is_file($path)) {
            $this->error("File workbook tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $spreadsheet = $reader->load($path);
        $sheetMap = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetMap[strtolower(trim($worksheet->getTitle()))] = $worksheet;
        }

        $state = $this->loadState();
        $summary = [];
        $notes = [];

        foreach ($steps as $step) {
            if (! isset($sheetMap[$step])) {
                $notes[] = "Sheet {$step} tidak ditemukan, dilewati.";
                continue;
            }

            $rows = $this->readSheetRows($sheetMap[$step]);

            $result = match ($step) {
                'categories' => $this->importCategories($rows, $state, $execute),
                'uoms' => $this->importUoms($rows, $state, $execute),
                'customers' => $this->importCustomers($rows, $state, $execute),
                'suppliers' => $this->importSuppliers($rows, $state, $execute),
                'products' => $this->importProducts($rows, $state, $execute),
                'stocks' => $this->importStocks($rows, $state, $execute),
            };

            $summary[$step] = $result['summary'];
            $notes = array_merge($notes, $result['notes']);
        }

        $this->info('Workbook import summary');
        $this->table(
            ['Entity', 'Source Rows', 'Created', 'Updated', 'Unchanged', 'Skipped'],
            array_map(
                fn (string $entity) => [
                    $entity,
                    $summary[$entity]['source_rows'] ?? 0,
                    $summary[$entity]['created'] ?? 0,
                    $summary[$entity]['updated'] ?? 0,
                    $summary[$entity]['unchanged'] ?? 0,
                    $summary[$entity]['skipped'] ?? 0,
                ],
                array_keys($summary),
            )
        );

        if (! $execute) {
            $notes[] = 'Command berjalan dalam mode dry-run. Tambahkan --execute untuk menulis ke database ERP.';
        }

        if ($notes !== []) {
            $this->newLine();
            $this->warn('Notes');
            foreach (array_values(array_unique($notes)) as $note) {
                $this->line('- ' . $note);
            }
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        return str_starts_with($file, DIRECTORY_SEPARATOR)
            ? $file
            : base_path($file);
    }

    private function normalizeSteps(?string $only): array
    {
        if (! $only) {
            return self::SHEET_ORDER;
        }

        $steps = array_values(array_filter(array_map('trim', explode(',', $only))));
        $allowed = array_flip(self::SHEET_ORDER);

        foreach ($steps as $step) {
            if (! isset($allowed[$step])) {
                throw new \InvalidArgumentException("Sheet import tidak dikenali: {$step}");
            }
        }

        if (in_array('products', $steps, true)) {
            if (! in_array('categories', $steps, true)) {
                array_unshift($steps, 'categories');
            }
            if (! in_array('uoms', $steps, true)) {
                array_splice($steps, 1, 0, ['uoms']);
            }
        }

        return array_values(array_unique($steps));
    }

    private function loadState(): array
    {
        $categoryState = [];
        foreach (ProductCategory::withTrashed()->get() as $category) {
            $categoryState[$category->kode] = $category;
        }

        $uomState = [];
        foreach (UnitOfMeasure::withTrashed()->get() as $uom) {
            $uomState[$this->normalizeLabel($uom->name)] = $uom;
            $uomState[$this->normalizeLabel($uom->abbreviation)] = $uom;
        }

        return [
            'categories' => $categoryState,
            'uoms' => $uomState,
            'customers' => DB::table('customers')->select([
                'id', 'code', 'name', 'address', 'phone', 'telephone', 'email', 'perusahaan', 'tipe', 'fax',
                'isSpecial', 'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp', 'keterangan',
                'cabang_id', 'deleted_at', 'created_at',
            ])->get()->keyBy('code')->all(),
            'suppliers' => DB::table('suppliers')->select([
                'id', 'code', 'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'created_at',
            ])->get()->keyBy('code')->all(),
            'products' => DB::table('products')->select([
                'id', 'sku', 'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price',
                'description', 'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'created_at',
            ])->get()->keyBy('sku')->all(),
            'stocks' => DB::table('inventory_stocks')->select([
                'id', 'product_id', 'warehouse_id', 'rak_id', 'qty_available', 'qty_reserved', 'qty_min', 'deleted_at',
            ])->get()->keyBy(fn ($row) => $this->stockKey((int) $row->product_id, (int) $row->warehouse_id, $row->rak_id))->all(),
            'next_virtual_id' => -1,
        ];
    }

    private function readSheetRows(Worksheet $worksheet): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow < 1 || $highestColumnIndex < 1) {
            return [];
        }

        $headerRow = $worksheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headerRow);
        $rows = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $values = $worksheet->rangeToArray("A{$rowIndex}:{$highestColumn}{$rowIndex}", null, true, false)[0] ?? [];

            if (! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function importCategories(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];

        foreach ($rows as $row) {
            $summary['source_rows']++;

            $code = $this->filledString($row['kode'] ?? null);
            if (! $code) {
                $summary['skipped']++;
                $notes[] = 'Ada row categories tanpa kode; row dilewati.';
                continue;
            }

            $payload = [
                'name' => $this->filledString($row['name'] ?? null) ?: $code,
                'kode' => $code,
                'kenaikan_harga' => $this->decimal($row['kenaikan_harga'] ?? 0),
            ];

            $existing = $state['categories'][$code] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $category = $existing ?: new ProductCategory();
                $category->forceFill($payload);
                if ($category->trashed()) {
                    $category->restore();
                }
                $category->save();
                $state['categories'][$code] = $category->fresh();
                continue;
            }

            $state['categories'][$code] = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
                'deleted_at' => null,
            ]);
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function importUoms(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];

        foreach ($rows as $row) {
            $summary['source_rows']++;

            $name = $this->filledString($row['name'] ?? null);
            if (! $name) {
                $summary['skipped']++;
                $notes[] = 'Ada row uoms tanpa name; row dilewati.';
                continue;
            }

            $payload = [
                'name' => $name,
                'abbreviation' => $this->filledString($row['abbreviation'] ?? null) ?: $name,
            ];

            $key = $this->normalizeLabel($name);
            $existing = $state['uoms'][$key] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $uom = $existing ?: new UnitOfMeasure();
                $uom->forceFill($payload);
                if ($uom->trashed()) {
                    $uom->restore();
                }
                $uom->save();
                $fresh = $uom->fresh();
                $state['uoms'][$this->normalizeLabel($fresh->name)] = $fresh;
                $state['uoms'][$this->normalizeLabel($fresh->abbreviation)] = $fresh;
                continue;
            }

            $virtual = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
                'deleted_at' => null,
            ]);
            $state['uoms'][$this->normalizeLabel($name)] = $virtual;
            $state['uoms'][$this->normalizeLabel($payload['abbreviation'])] = $virtual;
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function importCustomers(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];
        $batch = [];
        $timestamp = now();

        foreach ($rows as $row) {
            $summary['source_rows']++;
            $code = $this->filledString($row['code'] ?? null);

            if (! $code) {
                $summary['skipped']++;
                $notes[] = 'Ada row customers tanpa code; row dilewati.';
                continue;
            }

            $payload = [
                'code' => $code,
                'name' => $this->filledString($row['name'] ?? null) ?: $code,
                'address' => $this->filledString($row['address'] ?? null) ?: '-',
                'phone' => $this->filledString($row['phone'] ?? null) ?: '-',
                'telephone' => $this->filledString($row['telephone'] ?? null) ?: $this->filledString($row['phone'] ?? null) ?: '-',
                'email' => $this->filledString($row['email'] ?? null) ?: '',
                'perusahaan' => $this->filledString($row['perusahaan'] ?? null) ?: $this->filledString($row['name'] ?? null) ?: '-',
                'tipe' => in_array($this->filledString($row['tipe'] ?? null), ['PKP', 'PRI'], true) ? $row['tipe'] : 'PRI',
                'fax' => $this->filledString($row['fax'] ?? null) ?: '',
                'isSpecial' => $this->boolInt($row['isspecial'] ?? 0),
                'tempo_kredit' => (int) ($row['tempo_kredit'] ?? 0),
                'kredit_limit' => (int) round($this->decimal($row['kredit_limit'] ?? 0)),
                'tipe_pembayaran' => $this->filledString($row['tipe_pembayaran'] ?? null) ?: 'Bebas',
                'nik_npwp' => $this->filledString($row['nik_npwp'] ?? null) ?: '',
                'keterangan' => $this->filledString($row['keterangan'] ?? null),
                'cabang_id' => (int) ($row['cabang_id'] ?? 0),
                'deleted_at' => null,
            ];

            $existing = $state['customers'][$code] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $batch[] = array_merge($payload, [
                    'created_at' => $existing->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (count($batch) >= 500) {
                    DB::table('customers')->upsert($batch, ['code'], [
                        'name', 'address', 'phone', 'telephone', 'email', 'perusahaan', 'tipe', 'fax', 'isSpecial',
                        'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
                    ]);
                    $batch = [];
                }
            }

            $state['customers'][$code] = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
                'created_at' => $existing->created_at ?? $timestamp,
            ]);
        }

        if ($execute && $batch) {
            DB::table('customers')->upsert($batch, ['code'], [
                'name', 'address', 'phone', 'telephone', 'email', 'perusahaan', 'tipe', 'fax', 'isSpecial',
                'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
            ]);
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function importSuppliers(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];
        $batch = [];
        $timestamp = now();

        foreach ($rows as $row) {
            $summary['source_rows']++;
            $code = $this->filledString($row['code'] ?? null);

            if (! $code) {
                $summary['skipped']++;
                $notes[] = 'Ada row suppliers tanpa code; row dilewati.';
                continue;
            }

            $payload = [
                'code' => $code,
                'perusahaan' => $this->filledString($row['perusahaan'] ?? null) ?: $code,
                'address' => $this->filledString($row['address'] ?? null) ?: '-',
                'phone' => $this->filledString($row['phone'] ?? null) ?: '-',
                'email' => $this->filledString($row['email'] ?? null) ?: '',
                'handphone' => $this->filledString($row['handphone'] ?? null) ?: '-',
                'fax' => $this->filledString($row['fax'] ?? null) ?: '',
                'npwp' => $this->filledString($row['npwp'] ?? null) ?: '',
                'tempo_hutang' => (int) ($row['tempo_hutang'] ?? 0),
                'kontak_person' => $this->filledString($row['kontak_person'] ?? null),
                'keterangan' => $this->filledString($row['keterangan'] ?? null),
                'cabang_id' => (int) ($row['cabang_id'] ?? 0),
                'deleted_at' => null,
            ];

            $existing = $state['suppliers'][$code] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $batch[] = array_merge($payload, [
                    'created_at' => $existing->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (count($batch) >= 500) {
                    DB::table('suppliers')->upsert($batch, ['code'], [
                        'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                        'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
                    ]);
                    $batch = [];
                }
            }

            $state['suppliers'][$code] = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
                'created_at' => $existing->created_at ?? $timestamp,
            ]);
        }

        if ($execute && $batch) {
            DB::table('suppliers')->upsert($batch, ['code'], [
                'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
            ]);
        }

        if ($execute) {
            $state['suppliers'] = DB::table('suppliers')->select([
                'id', 'code', 'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'created_at',
            ])->get()->keyBy('code')->all();
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function importProducts(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];
        $batch = [];
        $timestamp = now();

        foreach ($rows as $row) {
            $summary['source_rows']++;
            $sku = $this->filledString($row['sku'] ?? null);

            if (! $sku) {
                $summary['skipped']++;
                $notes[] = 'Ada row products tanpa sku; row dilewati.';
                continue;
            }

            $categoryCode = $this->filledString($row['product_category_kode'] ?? null) ?: 'LEGACY-UNCAT';
            $category = $state['categories'][$categoryCode] ?? null;
            if (! $category) {
                $summary['skipped']++;
                $notes[] = "Kategori {$categoryCode} tidak ditemukan untuk sku {$sku}; row products dilewati.";
                continue;
            }

            $uomName = $this->filledString($row['uom_name'] ?? null) ?: 'PCS';
            $uom = $state['uoms'][$this->normalizeLabel($uomName)] ?? null;
            if (! $uom) {
                $summary['skipped']++;
                $notes[] = "UOM {$uomName} tidak ditemukan untuk sku {$sku}; row products dilewati.";
                continue;
            }

            $supplierCode = $this->filledString($row['supplier_code'] ?? null);
            $supplierId = $supplierCode && isset($state['suppliers'][$supplierCode])
                ? $state['suppliers'][$supplierCode]->id
                : null;

            $payload = [
                'sku' => $sku,
                'name' => $this->filledString($row['name'] ?? null) ?: $sku,
                'product_category_id' => $category->id,
                'cabang_id' => $row['cabang_id'] !== null && $row['cabang_id'] !== '' ? (int) $row['cabang_id'] : null,
                'cost_price' => $this->decimal($row['cost_price'] ?? 0),
                'sell_price' => $this->decimal($row['sell_price'] ?? 0),
                'description' => $this->filledString($row['description'] ?? null),
                'uom_id' => $uom->id,
                'supplier_id' => $supplierId,
                'harga_batas' => (int) ($row['harga_batas'] ?? 0),
                'item_value' => $this->decimal($row['item_value'] ?? 0),
                'tipe_pajak' => $this->filledString($row['tipe_pajak'] ?? null) ?: 'Non Pajak',
                'pajak' => $this->decimal($row['pajak'] ?? 0),
                'jumlah_kelipatan_gudang_besar' => (int) ($row['jumlah_kelipatan_gudang_besar'] ?? 0),
                'jumlah_jual_kategori_banyak' => (int) ($row['jumlah_jual_kategori_banyak'] ?? 0),
                'kode_merk' => $this->filledString($row['kode_merk'] ?? null) ?: '',
                'biaya' => $this->decimal($row['biaya'] ?? 0),
                'is_manufacture' => $this->boolInt($row['is_manufacture'] ?? 0),
                'is_raw_material' => $this->boolInt($row['is_raw_material'] ?? 0),
                'is_active' => $this->boolInt($row['is_active'] ?? 1),
                'deleted_at' => null,
            ];

            $existing = $state['products'][$sku] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $batch[] = array_merge($payload, [
                    'created_at' => $existing->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (count($batch) >= 500) {
                    DB::table('products')->upsert($batch, ['sku'], [
                        'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price', 'description',
                        'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                        'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                        'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'updated_at',
                    ]);
                    $batch = [];
                }
            }

            $state['products'][$sku] = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
                'created_at' => $existing->created_at ?? $timestamp,
            ]);
        }

        if ($execute && $batch) {
            DB::table('products')->upsert($batch, ['sku'], [
                'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price', 'description',
                'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'updated_at',
            ]);
        }

        if ($execute) {
            $state['products'] = DB::table('products')->select([
                'id', 'sku', 'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price',
                'description', 'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'created_at',
            ])->get()->keyBy('sku')->all();
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function importStocks(array $rows, array &$state, bool $execute): array
    {
        $summary = $this->emptySummary();
        $notes = [];

        foreach ($rows as $row) {
            $summary['source_rows']++;
            $sku = $this->filledString($row['sku'] ?? null);

            if (! $sku || ! isset($state['products'][$sku])) {
                $summary['skipped']++;
                $notes[] = $sku ? "Produk {$sku} tidak ditemukan untuk row stocks." : 'Ada row stocks tanpa sku; row dilewati.';
                continue;
            }

            $product = $state['products'][$sku];
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            if (! $warehouseId) {
                $summary['skipped']++;
                $notes[] = "Warehouse kosong untuk sku {$sku}; row stocks dilewati.";
                continue;
            }

            $rakId = $row['rak_id'] !== null && trim((string) $row['rak_id']) !== ''
                ? (int) $row['rak_id']
                : null;

            $payload = [
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'rak_id' => $rakId,
                'qty_available' => (float) $this->decimal($row['qty_available'] ?? 0),
                'qty_reserved' => (float) $this->decimal($row['qty_reserved'] ?? 0),
                'qty_min' => (float) $this->decimal($row['qty_min'] ?? 0),
                'deleted_at' => null,
            ];

            $key = $this->stockKey($payload['product_id'], $warehouseId, $rakId);
            $existing = $state['stocks'][$key] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $query = DB::table('inventory_stocks')
                    ->where('product_id', $payload['product_id'])
                    ->where('warehouse_id', $warehouseId);

                if ($rakId === null) {
                    $query->whereNull('rak_id');
                } else {
                    $query->where('rak_id', $rakId);
                }

                if ($existing) {
                    $query->update([
                        'qty_available' => $payload['qty_available'],
                        'qty_reserved' => $payload['qty_reserved'],
                        'qty_min' => $payload['qty_min'],
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('inventory_stocks')->insert([
                        'product_id' => $payload['product_id'],
                        'warehouse_id' => $warehouseId,
                        'rak_id' => $rakId,
                        'qty_available' => $payload['qty_available'],
                        'qty_reserved' => $payload['qty_reserved'],
                        'qty_min' => $payload['qty_min'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);
                }
            }

            $state['stocks'][$key] = (object) array_merge($payload, [
                'id' => $existing->id ?? $state['next_virtual_id']--,
            ]);
        }

        return ['summary' => $summary, 'notes' => $notes];
    }

    private function stockKey(int $productId, int $warehouseId, int|string|null $rakId): string
    {
        $rakSegment = $rakId === null || $rakId === '' ? '0' : (string) $rakId;

        return $productId . ':' . $warehouseId . ':' . $rakSegment;
    }

    private function emptySummary(): array
    {
        return [
            'source_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
        ];
    }

    private function rowStatus(mixed $existing, array $payload): string
    {
        if (! $existing) {
            return 'created';
        }

        if ($existing->deleted_at ?? null) {
            return 'updated';
        }

        foreach ($payload as $column => $value) {
            if ($this->valuesDiffer($existing->{$column} ?? null, $value)) {
                return 'updated';
            }
        }

        return 'unchanged';
    }

    private function valuesDiffer(mixed $current, mixed $incoming): bool
    {
        if ($current === null || $incoming === null) {
            return $current !== $incoming;
        }

        if (is_numeric($current) && is_numeric($incoming)) {
            return round((float) $current, 4) !== round((float) $incoming, 4);
        }

        return trim((string) $current) !== trim((string) $incoming);
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function decimal(mixed $value): float
    {
        return round((float) ($value ?: 0), 2);
    }

    private function normalizeLabel(mixed $value): string
    {
        $normalized = strtoupper((string) $value);
        $normalized = preg_replace('/\b(PT|CV|TBK)\b/u', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]+/u', '', $normalized);

        return trim((string) $normalized);
    }

    private function boolInt(mixed $value): int
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'Y', 'YES', 'TRUE'], true) ? 1 : 0;
    }
}