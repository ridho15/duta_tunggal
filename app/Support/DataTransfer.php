<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Export / import data per halaman (config/data_transfer.php). Khusus Owner / Super Admin.
 * Kolom export = kolom tabel; kolom "xxx_id" ditambah kolom nama relasinya agar terbaca orang awam.
 * Import hanya untuk data master: baris dengan kunci sama diperbarui, sisanya dibuat baru. Tidak ada penghapusan.
 */
class DataTransfer
{
    private const DISPLAY_COLUMNS = ['name', 'nama', 'code', 'kode', 'sku', 'plate', 'username'];

    private const NEVER_IMPORT = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'merged_into', 'legacy_code'];

    public static function datasets(): array
    {
        return config('data_transfer.datasets', []);
    }

    public static function dataset(string $pageKey): array
    {
        $ds = self::datasets()[$pageKey] ?? null;
        abort_if($ds === null, 404, 'Data halaman ini belum bisa diexport. Solusi: hubungi tim IT untuk menambahkannya.');

        return $ds;
    }

    public static function label(string $pageKey): string
    {
        return Access::pages()[$pageKey]['label'] ?? $pageKey;
    }

    public static function model(string $pageKey): Model
    {
        return new (self::dataset($pageKey)['model']);
    }

    /** @return array<int, string> kolom tabel yang boleh tampil */
    public static function columns(string $pageKey): array
    {
        $model = self::model($pageKey);
        $hidden = array_merge(config('data_transfer.hidden_columns', []), $model->getHidden());

        return array_values(array_diff(Schema::getColumnListing($model->getTable()), $hidden));
    }

    /** @return array<string, array{relation: string, display: string, model: Model}> kolom FK => relasi */
    public static function relations(string $pageKey): array
    {
        $model = self::model($pageKey);
        $out = [];
        foreach (self::columns($pageKey) as $col) {
            if (! str_ends_with($col, '_id')) {
                continue;
            }
            $method = Str::camel(substr($col, 0, -3));
            if (! method_exists($model, $method)) {
                continue;
            }
            try {
                $rel = $model->{$method}();
            } catch (Throwable) {
                continue;
            }
            if (! $rel instanceof BelongsTo || $rel->getForeignKeyName() !== $col) {
                continue;
            }
            $related = $rel->getRelated();
            $display = collect(self::DISPLAY_COLUMNS)->first(fn ($c) => Schema::hasColumn($related->getTable(), $c))
                ?? collect(Schema::getColumnListing($related->getTable()))->first(fn ($c) => str_ends_with($c, '_number'));
            if ($display) {
                $out[$col] = ['relation' => $method, 'display' => $display, 'model' => $related];
            }
        }

        return $out;
    }

    /** @return array<int, string> judul kolom file export */
    public static function headings(string $pageKey): array
    {
        $rels = self::relations($pageKey);
        $out = [];
        foreach (self::columns($pageKey) as $col) {
            $out[] = $col;
            if (isset($rels[$col])) {
                $out[] = substr($col, 0, -3) . '_' . $rels[$col]['display'];
            }
        }

        return $out;
    }

    public static function query(string $pageKey, ?string $from = null, ?string $to = null): Builder
    {
        $ds = self::dataset($pageKey);
        $query = $ds['model']::query()->with(array_column(self::relations($pageKey), 'relation'));
        foreach ($ds['where_in'] ?? [] as $col => $values) {
            $query->whereIn($col, $values);
        }
        if (! empty($ds['date'])) {
            if ($from) {
                $query->whereDate($ds['date'], '>=', $from);
            }
            if ($to) {
                $query->whereDate($ds['date'], '<=', $to);
            }
        }

        return $query->orderBy($query->getModel()->getKeyName());
    }

    /** Satu baris export sesuai urutan headings(). */
    public static function row(string $pageKey, Model $record, array $rels): array
    {
        $out = [];
        foreach (self::columns($pageKey) as $col) {
            $value = $record->getRawOriginal($col);
            $out[] = is_array($value) || is_object($value) ? json_encode($value) : $value;
            if (isset($rels[$col])) {
                $out[] = $record->{$rels[$col]['relation']}?->{$rels[$col]['display']};
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ import

    public static function importable(string $pageKey): bool
    {
        return ! empty(self::datasets()[$pageKey]['import']['key'] ?? null);
    }

    /** @return array<int, string> kolom yang bisa diisi lewat import (urut tabel) */
    public static function importColumns(string $pageKey): array
    {
        return array_values(array_diff(self::columns($pageKey), self::NEVER_IMPORT));
    }

    /** @return array<int, string> kolom wajib saat membuat data baru (NOT NULL tanpa nilai bawaan) */
    public static function requiredColumns(string $pageKey): array
    {
        $table = self::model($pageKey)->getTable();
        $required = [];
        foreach (DB::select("SHOW COLUMNS FROM `{$table}`") as $c) {
            if ($c->Null === 'NO' && $c->Default === null && $c->Extra === '' && ! in_array($c->Field, self::NEVER_IMPORT, true)) {
                $required[] = $c->Field;
            }
        }

        return array_values(array_intersect($required, self::importColumns($pageKey)));
    }

    /**
     * Periksa (dan bila $commit, simpan) baris import. Semua-atau-tidak-sama-sekali.
     *
     * @param  array<int, array<int|string, mixed>>  $rows  baris pertama = judul kolom
     * @return array{created: int, updated: int, errors: array<int, string>, unknown: array<int, string>}
     */
    public static function import(string $pageKey, array $rows, bool $commit): array
    {
        $ds = self::dataset($pageKey);
        abort_unless(self::importable($pageKey), 404, 'Data halaman ini tidak bisa diimpor. Solusi: import hanya untuk data master.');
        $key = $ds['import']['key'];
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'unknown' => []];

        $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->replace(' ', '_')->value(), array_shift($rows) ?? []);
        if ($header === [] || ! in_array($key, $header, true)) {
            $result['errors'][] = "Kolom kunci \"{$key}\" tidak ada di baris judul. Solusi: unduh template dari tombol Import lalu isi tanpa mengubah baris judul.";

            return $result;
        }
        $max = (int) config('data_transfer.max_import_rows', 5000);
        if (count($rows) > $max) {
            $result['errors'][] = 'Jumlah baris ' . count($rows) . " melebihi batas {$max}. Solusi: pecah file menjadi beberapa bagian.";

            return $result;
        }

        $allowed = self::importColumns($pageKey);
        $rels = self::relations($pageKey);
        $required = self::requiredColumns($pageKey);
        $displayToFk = [];
        foreach ($rels as $fk => $rel) {
            $displayToFk[substr($fk, 0, -3) . '_' . $rel['display']] = $fk;
        }
        $result['unknown'] = array_values(array_filter($header, fn ($h) => $h !== '' && ! in_array($h, $allowed, true) && ! isset($displayToFk[$h])
            && ! in_array($h, ['id', 'created_at', 'updated_at', 'deleted_at'], true)));

        $modelClass = $ds['model'];
        DB::beginTransaction();
        try {
            foreach ($rows as $i => $raw) {
                $line = $i + 2;
                $data = [];
                foreach ($header as $pos => $col) {
                    if ($col === '' || ! array_key_exists($pos, $raw)) {
                        continue;
                    }
                    $value = is_string($raw[$pos]) ? trim($raw[$pos]) : $raw[$pos];
                    if (in_array($col, $allowed, true)) {
                        $data[$col] = $value === '' ? null : $value;
                    } elseif (isset($displayToFk[$col]) && $value !== null && $value !== '') {
                        $fk = $displayToFk[$col];
                        if (! empty($data[$fk])) {
                            continue;   // ID diisi langsung, abaikan nama
                        }
                        $rel = $rels[$fk];
                        $found = $rel['model']->newQuery()->where($rel['display'], $value)->value($rel['model']->getKeyName());
                        if ($found === null) {
                            $result['errors'][] = "Baris {$line}: {$col} \"{$value}\" tidak ditemukan. Solusi: cek ejaan atau isi kolom {$fk} dengan ID-nya.";

                            continue 2;
                        }
                        $data[$fk] = $found;
                    }
                }
                if (array_filter($data, fn ($v) => $v !== null) === []) {
                    continue;   // baris kosong
                }
                if (empty($data[$key])) {
                    $result['errors'][] = "Baris {$line}: kolom kunci {$key} kosong. Solusi: isi {$key} supaya sistem tahu data mana yang dibuat atau diperbarui.";

                    continue;
                }

                $existing = $modelClass::query()->where($key, $data[$key])->first();
                if (! $existing) {
                    $missing = array_filter($required, fn ($c) => ! isset($data[$c]) || $data[$c] === null);
                    if ($missing) {
                        $result['errors'][] = "Baris {$line} ({$key} {$data[$key]}): data baru tetapi kolom wajib kosong: " . implode(', ', $missing) . '. Solusi: lengkapi kolom tersebut.';

                        continue;
                    }
                }
                try {
                    $record = $existing ?? new $modelClass;
                    $record->forceFill($existing ? array_filter($data, fn ($v) => $v !== null) : $data);
                    $record->save();
                    $existing ? $result['updated']++ : $result['created']++;
                } catch (Throwable $e) {
                    $result['errors'][] = "Baris {$line} ({$key} {$data[$key]}): gagal disimpan, " . Str::limit($e->getMessage(), 180) . '. Solusi: periksa isian baris ini.';
                }
            }

            if ($commit && $result['errors'] === []) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }
}
