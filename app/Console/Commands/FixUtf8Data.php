<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixUtf8Data extends Command
{
    protected $signature = 'fix:utf8-data';
    protected $description = 'Scan and fix non-UTF8 data in important fields (cabangs.nama, customers.name, invoices.*)';

    public function handle()
    {
        $targets = [
            ['table' => 'cabangs', 'field' => 'nama'],
            ['table' => 'customers', 'field' => 'name'],
            // invoices: check common textual fields
            ['table' => 'invoices', 'field' => 'customer_name'],
            ['table' => 'invoices', 'field' => 'supplier_name'],
        ];

        foreach ($targets as $info) {
            $table = $info['table'];
            $field = $info['field'];

            if (! Schema::hasTable($table)) {
                $this->warn("Skipping: table {$table} does not exist.");
                continue;
            }

            if (! Schema::hasColumn($table, $field)) {
                $this->warn("Skipping: column {$table}.{$field} does not exist.");
                continue;
            }

            $this->info("Scanning {$table}.{$field} ...");

            $rows = DB::table($table)
                ->select('id', $field)
                ->get();

            $count = 0;
            foreach ($rows as $row) {
                $value = $row->{$field};
                if ($value === null) {
                    continue;
                }

                $fixed = $this->sanitizeUtf8($value);
                if ($fixed !== $value) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$field => $fixed]);
                    $count++;
                }
            }

            $this->info("Fixed {$count} rows in {$table}.{$field}");
        }

        $this->info('Done fixing non-UTF8 data.');
    }

    private function sanitizeUtf8($value)
    {
        // Remove ASCII control chars except common whitespace
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        $res = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($res === false) {
            $res = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        return $res;
    }
}
