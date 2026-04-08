<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $user = User::query()->where('email', 'ralamzah@gmail.com')->first() ?? User::query()->orderBy('id')->first();
    if (! $user) {
        throw new RuntimeException('No user found for journal consolidation playwright fixture.');
    }

    $user->forceFill([
        'manage_type' => 'all',
    ])->save();

    $branchA = Cabang::query()->updateOrCreate(
        ['kode' => 'JC-PW-A'],
        [
            'nama' => 'Cabang Fixture Journal A',
            'alamat' => 'Fixture A',
            'telepon' => '0210000001',
            'kenaikan_harga' => 0,
            'status' => true,
            'warna_background' => '#0f766e',
            'tipe_penjualan' => 'Semua',
            'kode_invoice_pajak' => 'INV-PJK-JCA',
            'kode_invoice_non_pajak' => 'INV-NPJK-JCA',
            'kode_invoice_pajak_walkin' => 'INV-WPJK-JCA',
            'nama_kwitansi' => 'Kwitansi Fixture A',
            'label_invoice_pajak' => 'Pajak A',
            'label_invoice_non_pajak' => 'Non Pajak A',
            'logo_invoice_non_pajak' => null,
            'lihat_stok_cabang_lain' => true,
        ]
    );

    $branchB = Cabang::query()->updateOrCreate(
        ['kode' => 'JC-PW-B'],
        [
            'nama' => 'Cabang Fixture Journal B',
            'alamat' => 'Fixture B',
            'telepon' => '0210000002',
            'kenaikan_harga' => 0,
            'status' => true,
            'warna_background' => '#1d4ed8',
            'tipe_penjualan' => 'Semua',
            'kode_invoice_pajak' => 'INV-PJK-JCB',
            'kode_invoice_non_pajak' => 'INV-NPJK-JCB',
            'kode_invoice_pajak_walkin' => 'INV-WPJK-JCB',
            'nama_kwitansi' => 'Kwitansi Fixture B',
            'label_invoice_pajak' => 'Pajak B',
            'label_invoice_non_pajak' => 'Non Pajak B',
            'logo_invoice_non_pajak' => null,
            'lihat_stok_cabang_lain' => true,
        ]
    );

    $cash = ChartOfAccount::query()->updateOrCreate(
        ['code' => '1-JC-PW-001'],
        ['name' => 'Kas Fixture Journal', 'type' => 'Asset', 'is_active' => true]
    );

    $revenue = ChartOfAccount::query()->updateOrCreate(
        ['code' => '4-JC-PW-001'],
        ['name' => 'Pendapatan Fixture Journal', 'type' => 'Revenue', 'is_active' => true]
    );

    $inventory = ChartOfAccount::query()->updateOrCreate(
        ['code' => '1-JC-PW-002'],
        ['name' => 'Persediaan Fixture Journal', 'type' => 'Asset', 'is_active' => true]
    );

    $payable = ChartOfAccount::query()->updateOrCreate(
        ['code' => '2-JC-PW-001'],
        ['name' => 'Hutang Fixture Journal', 'type' => 'Liability', 'is_active' => true]
    );

    $expense = ChartOfAccount::query()->updateOrCreate(
        ['code' => '6-JC-PW-001'],
        ['name' => 'Beban Fixture Journal', 'type' => 'Expense', 'is_active' => true]
    );

    JournalEntry::query()->whereIn('reference', [
        'JC-PW-A-MAN-001',
        'JC-PW-B-MAN-001',
        'JC-PW-A-SAL-001',
    ])->forceDelete();

    $rows = [
        [$cash->id, $branchA->id, '2026-04-05', 'JC-PW-A-MAN-001', 'Fixture manual branch A', 500000, 0, 'manual'],
        [$revenue->id, $branchA->id, '2026-04-05', 'JC-PW-A-MAN-001', 'Fixture manual branch A', 0, 500000, 'manual'],
        [$inventory->id, $branchB->id, '2026-04-06', 'JC-PW-B-MAN-001', 'Fixture manual branch B', 300000, 0, 'manual'],
        [$payable->id, $branchB->id, '2026-04-06', 'JC-PW-B-MAN-001', 'Fixture manual branch B', 0, 300000, 'manual'],
        [$expense->id, $branchA->id, '2026-04-07', 'JC-PW-A-SAL-001', 'Fixture sales branch A', 200000, 0, 'sales'],
        [$cash->id, $branchA->id, '2026-04-07', 'JC-PW-A-SAL-001', 'Fixture sales branch A', 0, 200000, 'sales'],
    ];

    foreach ($rows as [$coaId, $branchId, $date, $reference, $description, $debit, $credit, $journalType]) {
        JournalEntry::query()->create([
            'coa_id' => $coaId,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => $journalType,
            'cabang_id' => $branchId,
            'source_type' => null,
            'source_id' => null,
            'created_by' => $user->id,
        ]);
    }

    echo "✅ Journal consolidation fixture ready\n";
    echo "   Branch A : {$branchA->nama}\n";
    echo "   Branch B : {$branchB->nama}\n";
    echo "   Total debit fixture : 1000000\n";
});