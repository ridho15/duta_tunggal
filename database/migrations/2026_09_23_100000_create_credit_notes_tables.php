<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T5 (D10/D35–D39) — Nota Kredit (pembatalan penuh, retur, koreksi sebagian), status invoice `cancelled`, keputusan retur `credit`,
     * jenis persetujuan `credit_note`, dan izin baru. SEMUA langkah idempoten: enum/kolom invoice `cancelled` juga ditambahkan pihak lain
     * (migrasi pembatalan invoice pembelian) — di sini hanya ditambahkan bila belum ada.
     */
    public function up(): void
    {
        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->string('credit_note_number')->unique();
                $table->string('type', 20);                       // pembatalan | retur | koreksi
                $table->string('status', 20)->default('draft');   // draft | issued
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->unsignedBigInteger('customer_return_id')->nullable()->index();
                $table->unsignedBigInteger('replacement_invoice_id')->nullable();
                $table->date('credit_date');
                $table->text('reason');
                $table->decimal('subtotal', 18, 2)->default(0);        // DPP baris
                $table->decimal('other_fee_amount', 18, 2)->default(0); // biaya pengiriman yang ikut dikreditkan
                $table->decimal('tax_amount', 18, 2)->default(0);       // PPN yang dibalik
                $table->decimal('total', 18, 2)->default(0);
                $table->decimal('applied_to_ar', 18, 2)->default(0);      // mengurangi piutang invoice
                $table->decimal('applied_to_deposit', 18, 2)->default(0); // sudah dibayar customer → Deposit Customer
                $table->string('tax_document_number')->nullable();        // nomor Nota Retur Pajak (manual)
                $table->unsignedBigInteger('currency_id')->nullable();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('credit_note_items')) {
            Schema::create('credit_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('credit_note_id')->index();
                $table->unsignedBigInteger('invoice_item_id')->nullable()->index();   // null = baris biaya pengiriman
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('description')->nullable();
                $table->decimal('quantity', 12, 2)->default(0);
                $table->decimal('unit_price', 18, 4)->default(0);   // harga bersih setelah diskon
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->timestamps();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->ensureInvoiceCancelledEnum();
            $this->ensureReturnDecisionCredit();
        }

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['cancelled_at' => 'timestamp', 'cancelled_by' => 'unsignedBigInteger', 'cancel_reason' => 'text'] as $column => $type) {
                if (! Schema::hasColumn('invoices', $column)) {
                    $type === 'timestamp' ? $table->timestamp($column)->nullable() : ($type === 'text' ? $table->text($column)->nullable() : $table->unsignedBigInteger($column)->nullable());
                }
            }
        });

        $this->seedApprovalRules();
        $this->ensurePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        // Enum invoices.status, kolom cancelled_* dan keputusan retur `credit` sengaja DIBIARKAN (dapat dipakai pihak lain / data historis).
        DB::table('approval_rules')->where('document_type', 'credit_note')->delete();
    }

    private function ensureInvoiceCancelledEnum(): void
    {
        $column = DB::selectOne("SHOW COLUMNS FROM invoices LIKE 'status'");
        if ($column && ! str_contains((string) $column->Type, "'cancelled'")) {
            $values = preg_replace('/^enum\((.*)\)$/i', '$1', (string) $column->Type);
            DB::statement("ALTER TABLE invoices MODIFY status ENUM({$values},'cancelled') NOT NULL DEFAULT 'draft'");
        }
    }

    private function ensureReturnDecisionCredit(): void
    {
        if (! Schema::hasTable('customer_return_items')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM customer_return_items LIKE 'decision'");
        if ($column && ! str_contains((string) $column->Type, "'credit'")) {
            $values = preg_replace('/^enum\((.*)\)$/i', '$1', (string) $column->Type);
            DB::statement("ALTER TABLE customer_return_items MODIFY decision ENUM({$values},'credit') NULL");
        }
    }

    private function seedApprovalRules(): void
    {
        if (! Schema::hasTable('approval_rules') || DB::table('approval_rules')->where('document_type', 'credit_note')->exists()) {
            return;
        }

        $now = now();
        DB::table('approval_rules')->insert([
            [
                'document_type' => 'credit_note', 'label' => 'Nota Kredit sampai Rp 10.000.000', 'above_amount' => null, 'up_to_amount' => 10000000,
                'roles' => json_encode(['Finance Manager', 'Sales Manager', 'Super Admin', 'Owner', 'Admin']), 'approver_label' => 'Finance Manager / Sales Manager / Direktur',
                'is_active' => true, 'notes' => 'Nilai awal Nota Kredit', 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'document_type' => 'credit_note', 'label' => 'Nota Kredit di atas Rp 10.000.000', 'above_amount' => 10000000, 'up_to_amount' => null,
                'roles' => json_encode(['Finance Manager', 'Super Admin', 'Owner', 'Admin']), 'approver_label' => 'Direktur / Owner / Finance Manager',
                'is_active' => true, 'notes' => 'Nilai awal Nota Kredit', 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    private function ensurePermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $names = ['view any credit note', 'view credit note', 'create credit note', 'update credit note', 'delete credit note', 'approve credit note'];
        foreach ($names as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['Super Admin', 'Owner', 'Finance Manager'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($names);
        }
    }
};
