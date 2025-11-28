<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CompleteTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting complete transaction seeding...');
        
        // First, ensure master data exists
        $this->call(MasterDataSeeder::class);
        
        // Run sales transaction seeder
        $this->call(SalesTransactionSeeder::class);
        
        // Run purchase transaction seeder  
        $this->call(PurchaseTransactionSeeder::class);
        
        $this->command->info('Complete transaction seeding finished!');
        $this->command->info('');
        $this->showSummary();
    }
    
    private function checkPrerequisites(): void
    {
        $this->command->info('Checking prerequisites...');
        
        $requiredTables = [
            'customers' => \App\Models\Customer::count(),
            'suppliers' => \App\Models\Supplier::count(),
            'products' => \App\Models\Product::count(),
            'users' => \App\Models\User::count(),
        ];
        
        foreach ($requiredTables as $table => $count) {
            if ($count == 0) {
                $this->command->warn("No {$table} found. Please seed {$table} first.");
            } else {
                $this->command->info("✓ {$table}: {$count} records found");
            }
        }
    }
    
    private function showSummary(): void
    {
        $summary = [
            'Sale Orders' => \App\Models\SaleOrder::count(),
            'Purchase Orders' => \App\Models\PurchaseOrder::count(),
            'Invoices' => \App\Models\Invoice::count(),
            'Account Receivables' => \App\Models\AccountReceivable::count(),
            'Account Payables' => \App\Models\AccountPayable::count(),
            'Ageing Schedules' => \App\Models\AgeingSchedule::count(),
        ];
        
        $this->command->info('Summary of created records:');
        foreach ($summary as $model => $count) {
            $this->command->info("- {$model}: {$count}");
        }
        
        // Show invoice status breakdown
        $invoiceStats = \App\Models\Invoice::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        $this->command->info('Invoice Status Breakdown:');
        foreach ($invoiceStats as $status => $count) {
            $this->command->info("- {$status}: {$count}");
        }
        
        // Show receivables with remaining balance
        $receivablesWithBalance = \App\Models\AccountReceivable::where('remaining', '>', 0)->count();
        $totalReceivables = \App\Models\AccountReceivable::count();
        $this->command->info("Receivables with outstanding balance: {$receivablesWithBalance} / {$totalReceivables}");
        
        // Show payables with remaining balance
        $payablesWithBalance = \App\Models\AccountPayable::where('remaining', '>', 0)->count();
        $totalPayables = \App\Models\AccountPayable::count();
        $this->command->info("Payables with outstanding balance: {$payablesWithBalance} / {$totalPayables}");
    }
}
