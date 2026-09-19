<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanUatMasterDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-uat-master-data {--dry-run : Only show what would be cleaned without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean UAT master data inconsistencies: typos in UOM, comma-formatted phone numbers, duplicate suffixes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $this->info($dryRun ? 'Running Master Data Cleanup in DRY-RUN mode...' : 'Executing Master Data Cleanup...');

        $this->cleanUomTypos($dryRun);
        $this->cleanCustomerPhoneNumbers($dryRun);
        $this->auditDuplicateProducts($dryRun);
        $this->auditDuplicateSuppliers($dryRun);

        $this->info('Master Data Cleanup check completed.');
        return 0;
    }

    protected function cleanUomTypos(bool $dryRun): void
    {
        $this->line("\nChecking Unit of Measure (UOM) typos...");
        $corruptedUoms = UnitOfMeasure::where('name', 'like', '%]%')
            ->orWhere('abbreviation', 'like', '%]%')
            ->orWhere('name', 'like', '%BH%')
            ->orWhere('abbreviation', 'like', '%BH%')
            ->get();

        if ($corruptedUoms->isEmpty()) {
            $this->info("✓ No corrupted UOM records found with trailing brackets or typo BH].");
            return;
        }

        foreach ($corruptedUoms as $uom) {
            $cleanedName = trim(str_replace([']', '['], '', $uom->name));
            $cleanedAbbr = trim(str_replace([']', '['], '', $uom->abbreviation));

            $this->warn("Found UOM ID {$uom->id}: '{$uom->name}' ({$uom->abbreviation}) -> Clean: '{$cleanedName}' ({$cleanedAbbr})");

            if (! $dryRun) {
                $uom->update([
                    'name' => $cleanedName,
                    'abbreviation' => $cleanedAbbr,
                ]);
            }
        }
    }

    protected function cleanCustomerPhoneNumbers(bool $dryRun): void
    {
        $this->line("\nChecking Customer & Supplier phone numbers formatted with commas/decimals...");

        $customers = Customer::where('phone', 'like', '%,%')
            ->orWhere('phone', 'like', '%.00%')
            ->get();

        if ($customers->isEmpty()) {
            $this->info("✓ No customer phone numbers with comma formatting found.");
        } else {
            foreach ($customers as $c) {
                // e.g. "6006593,63" -> strip comma and decimal decimals if invalid or clean to plain string
                $cleanPhone = preg_replace('/,[0-9]+$/', '', (string) $c->phone);
                $cleanPhone = str_replace(',', '', $cleanPhone);
                $this->warn("Customer ID {$c->id} ({$c->name}): '{$c->phone}' -> '{$cleanPhone}'");

                if (! $dryRun) {
                    $c->update(['phone' => $cleanPhone]);
                }
            }
        }

        $suppliers = Supplier::where('phone', 'like', '%,%')
            ->orWhere('phone', 'like', '%.00%')
            ->get();

        if ($suppliers->isEmpty()) {
            $this->info("✓ No supplier phone numbers with comma formatting found.");
        } else {
            foreach ($suppliers as $s) {
                $cleanPhone = preg_replace('/,[0-9]+$/', '', (string) $s->phone);
                $cleanPhone = str_replace(',', '', $cleanPhone);
                $this->warn("Supplier ID {$s->id} ({$s->perusahaan}): '{$s->phone}' -> '{$cleanPhone}'");

                if (! $dryRun) {
                    $s->update(['phone' => $cleanPhone]);
                }
            }
        }
    }

    protected function auditDuplicateProducts(bool $dryRun): void
    {
        $this->line("\nChecking Products with duplicate suffixes (-DUP, -DUP2)...");

        $dupProducts = Product::where('sku', 'like', '%-DUP%')
            ->orWhere('name', 'like', '%-DUP%')
            ->get();

        if ($dupProducts->isEmpty()) {
            $this->info("✓ No products found with suffix -DUP in current database.");
        } else {
            $this->warn("Found " . $dupProducts->count() . " products with -DUP suffix.");
            foreach ($dupProducts as $p) {
                $this->line("- [ID {$p->id}] SKU: {$p->sku} | Name: {$p->name}");
            }
        }
    }

    protected function auditDuplicateSuppliers(bool $dryRun): void
    {
        $this->line("\nChecking Suppliers with duplicate prefix (CAB-)...");

        $cabSuppliers = Supplier::where('perusahaan', 'like', 'CAB-%')
            ->orWhere('code', 'like', 'CAB-%')
            ->get();

        if ($cabSuppliers->isEmpty()) {
            $this->info("✓ No suppliers found with prefix CAB- in current database.");
        } else {
            $this->warn("Found " . $cabSuppliers->count() . " suppliers with CAB- prefix.");
            foreach ($cabSuppliers as $s) {
                $this->line("- [ID {$s->id}] Code: {$s->code} | Perusahaan: {$s->perusahaan}");
            }
        }
    }
}
