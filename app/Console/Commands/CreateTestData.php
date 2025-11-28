<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test data for Customer Receipt and Vendor Payment functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating test data...');

        try {
            // Check if test data already exists
            $existingSalesInvoice = DB::table('invoices')->where('invoice_number', 'INV-TEST-001')->first();
            $existingPurchaseInvoice = DB::table('invoices')->where('invoice_number', 'PINV-TEST-001')->first();

            if ($existingSalesInvoice || $existingPurchaseInvoice) {
                $this->warn('⚠️  Test data already exists!');
                
                if ($this->confirm('Do you want to recreate the test data? (This will delete existing test data)')) {
                    // Clean existing test data
                    $this->info('🧹 Cleaning existing test data...');
                    
                    // Remove test customers with duplicate codes
                    DB::table('customers')->where('code', 'CUST001')->delete();
                    DB::table('suppliers')->where('code', 'SUPP001')->delete();
                    
                    // Remove existing test invoices and related data
                    DB::table('account_receivables')->whereIn('invoice_id', 
                        DB::table('invoices')->whereIn('invoice_number', ['INV-TEST-001', 'PINV-TEST-001'])->pluck('id')
                    )->delete();
                    
                    DB::table('account_payables')->whereIn('invoice_id', 
                        DB::table('invoices')->whereIn('invoice_number', ['INV-TEST-001', 'PINV-TEST-001'])->pluck('id')
                    )->delete();
                    
                    DB::table('invoices')->whereIn('invoice_number', ['INV-TEST-001', 'PINV-TEST-001'])->delete();
                } else {
                    $this->info('ℹ️  Using existing test data.');
                    $this->showDataSummary();
                    return 0;
                }
            }

            // Create unique test customer (check if exists first)
            $customerId = DB::table('customers')->where('code', 'CUST-TEST-001')->value('id');
            
            if (!$customerId) {
                $customerId = DB::table('customers')->insertGetId([
                    'name' => 'PT Test Customer',
                    'code' => 'CUST-TEST-001',
                    'address' => 'Jl. Test Customer No. 123, Jakarta',
                    'telephone' => '021-1234567',
                    'phone' => '08123456789',
                    'email' => 'customer.test@example.com',
                    'perusahaan' => 'PT Test Customer',
                    'tipe' => 'PKP',
                    'fax' => '021-1234568',
                    'tempo_kredit' => 30,
                    'kredit_limit' => 10000000,
                    'tipe_pembayaran' => 'Kredit',
                    'nik_npwp' => '1234567890123456',
                    'keterangan' => 'Test customer for auto-calculation',
                    'isSpecial' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create unique test supplier (check if exists first)
            $supplierId = DB::table('suppliers')->where('code', 'SUPP-TEST-001')->value('id');
            
            if (!$supplierId) {
                $supplierId = DB::table('suppliers')->insertGetId([
                    'name' => 'PT Test Supplier',
                    'code' => 'SUPP-TEST-001',
                    'address' => 'Jl. Test Supplier No. 456, Jakarta',
                    'phone' => '021-9876543',
                    'handphone' => '08987654321',
                    'email' => 'supplier.test@example.com',
                    'perusahaan' => 'PT Test Supplier',
                    'fax' => '021-9876542',
                    'npwp' => '9876543210987654',
                    'tempo_hutang' => 45,
                    'kontak_person' => 'Test Contact',
                    'keterangan' => 'Test supplier for auto-calculation',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create sales invoice with due_date
            $invoiceId1 = DB::table('invoices')->insertGetId([
                'invoice_number' => 'INV-TEST-001',
                'from_model_type' => 'App\Models\SaleOrder',
                'from_model_id' => 1,
                'invoice_date' => now()->subDays(10),
                'due_date' => now()->addDays(20),
                'subtotal' => 5000000,
                'tax' => 0,
                'other_fee' => 0,
                'total' => 5000000,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create purchase invoice with due_date
            $invoiceId2 = DB::table('invoices')->insertGetId([
                'invoice_number' => 'PINV-TEST-001',
                'from_model_type' => 'App\Models\PurchaseOrder',
                'from_model_id' => 1,
                'invoice_date' => now()->subDays(5),
                'due_date' => now()->addDays(25),
                'subtotal' => 8000000,
                'tax' => 0,
                'other_fee' => 0,
                'total' => 8000000,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create account receivable
            DB::table('account_receivables')->insert([
                'invoice_id' => $invoiceId1,
                'customer_id' => $customerId,
                'total' => 5000000,
                'paid' => 1500000,
                'remaining' => 3500000,
                'status' => 'Belum Lunas',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create account payable
            DB::table('account_payables')->insert([
                'invoice_id' => $invoiceId2,
                'supplier_id' => $supplierId,
                'total' => 8000000,
                'paid' => 1600000,
                'remaining' => 6400000,
                'status' => 'Belum Lunas',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->info('✅ Test data created successfully!');
            $this->showDataSummary();

        } catch (\Exception $e) {
            $this->error('❌ Error creating test data: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function showDataSummary()
    {
        $this->info('📊 Data Summary:');
        $this->info('   • Sales Invoice: INV-TEST-001 (Rp 5.000.000, remaining: Rp 3.500.000)');
        $this->info('   • Purchase Invoice: PINV-TEST-001 (Rp 8.000.000, remaining: Rp 6.400.000)');
        $this->info('');
        $this->info('🧪 Now you can test:');
        $this->info('   1. Customer Receipt - select INV-TEST-001');
        $this->info('   2. Vendor Payment - select PINV-TEST-001');
        $this->info('   3. Test auto-calculation features');
    }
}
