<?php

/**
 * Tests for the bug fixes applied during the 2026-03 ERP audit.
 *
 * Fixes verified here:
 *  1. MoneyHelper::parse() handles Indonesian thousand-separator format (e.g. "20.000.000")
 *  2. PaymentRequest `approved_at` accessor/mutator guards against '-' DB values
 *  3. Deposit tambahSaldo/kurangiSaldo validation accepts Indonesian money format
 *  4. OrderRequest supports extended status enum (request_approve, partial, complete)
 *  5. order_request_items now has supplier_id column
 */

use App\Helpers\MoneyHelper;
use App\Models\Cabang;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// 1. MoneyHelper::parse() — Indonesian money format
// ─────────────────────────────────────────────────────────────────────────────

describe('MoneyHelper::parse()', function () {

    it('parses a plain integer string', function () {
        expect(MoneyHelper::parse('1000000'))->toBe(1000000.0);
    });

    it('parses Indonesian thousands-dot format "20.000.000"', function () {
        expect(MoneyHelper::parse('20.000.000'))->toBe(20000000.0);
    });

    it('parses Indonesian format with Rp prefix "Rp 1.500.000"', function () {
        expect(MoneyHelper::parse('Rp 1.500.000'))->toBe(1500000.0);
    });

    it('parses Western decimal format "1500000.50"', function () {
        expect(MoneyHelper::parse('1500000.50'))->toBe(1500000.5);
    });

    it('parses Indonesian decimal format "1.500.000,75"', function () {
        expect(MoneyHelper::parse('1.500.000,75'))->toBe(1500000.75);
    });

    it('returns 0.0 for null', function () {
        expect(MoneyHelper::parse(null))->toBe(0.0);
    });

    it('returns 0.0 for empty string', function () {
        expect(MoneyHelper::parse(''))->toBe(0.0);
    });

    it('returns 0.0 for a dash "-"', function () {
        expect(MoneyHelper::parse('-'))->toBe(0.0);
    });

    it('parses raw DB decimal "20000000.00"', function () {
        expect(MoneyHelper::parse('20000000.00'))->toBe(20000000.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. PaymentRequest approved_at accessor / mutator
// ─────────────────────────────────────────────────────────────────────────────

describe('PaymentRequest approved_at accessor', function () {

    it('returns null when raw DB attribute is "-"', function () {
        // Simulate legacy DB data where '-' was stored in approved_at.
        // MySQL strict mode rejects writing '-' directly, so we test the accessor
        // by injecting the raw attribute value on an in-memory model instance.
        $pr = new PaymentRequest();
        $pr->setRawAttributes(['approved_at' => '-'], true);

        // Accessor must return null — NOT throw DateMalformedStringException
        expect($pr->approved_at)->toBeNull();
    });

    it('returns null when DB stores null', function () {
        $cabang   = Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);

        $pr = PaymentRequest::create([
            'request_number'    => 'PR-AUDIT-0002',
            'supplier_id'       => $supplier->id,
            'cabang_id'         => $cabang->id,
            'requested_by'      => $user->id,
            'request_date'      => now()->toDateString(),
            'total_amount'      => 100_000,
            'selected_invoices' => [],
            'status'            => 'draft',
        ]);

        $fresh = PaymentRequest::findOrFail($pr->id);
        expect($fresh->approved_at)->toBeNull();
    });

    it('returns a Carbon instance when DB stores a valid date', function () {
        $cabang   = Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);

        $now = now()->startOfMinute(); // truncate for DB precision

        $pr = PaymentRequest::create([
            'request_number'    => 'PR-AUDIT-0003',
            'supplier_id'       => $supplier->id,
            'cabang_id'         => $cabang->id,
            'requested_by'      => $user->id,
            'request_date'      => now()->toDateString(),
            'total_amount'      => 200_000,
            'selected_invoices' => [],
            'status'            => 'approved',
            'approved_at'       => $now,
        ]);

        $fresh = PaymentRequest::findOrFail($pr->id);

        expect($fresh->approved_at)->not->toBeNull()
            ->and($fresh->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('mutator converts "-" to null in model attributes', function () {
        // Test the mutator in isolation — setting '-' should store null in raw attributes
        $pr = new PaymentRequest();
        $pr->setApprovedAtAttribute('-');

        $attrs = $pr->getAttributes();
        expect($attrs['approved_at'])->toBeNull();
    });

    it('mutator converts empty string to null', function () {
        $pr = new PaymentRequest();
        $pr->setApprovedAtAttribute('');

        $attrs = $pr->getAttributes();
        expect($attrs['approved_at'])->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Deposit tambahSaldo / kurangiSaldo — custom money validation rule
// ─────────────────────────────────────────────────────────────────────────────

describe('Deposit money validation rule', function () {

    /**
     * The validation closure mirrors what DepositResource uses in tambahSaldo/kurangiSaldo.
     */
    function makeDepositMoneyRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $parsed = \App\Helpers\MoneyHelper::parse($value);
            if ($parsed < 1) {
                $fail('Total minimal Rp 1.');
            }
        };
    }

    it('passes for "20.000.000" (Indonesian format)', function () {
        $rule      = makeDepositMoneyRule();
        $failed    = false;
        $rule('amount', '20.000.000', function () use (&$failed) { $failed = true; });
        expect($failed)->toBeFalse();
    });

    it('passes for "1.000" (one thousand)', function () {
        $rule   = makeDepositMoneyRule();
        $failed = false;
        $rule('amount', '1.000', function () use (&$failed) { $failed = true; });
        expect($failed)->toBeFalse();
    });

    it('passes for plain integer "5000000"', function () {
        $rule   = makeDepositMoneyRule();
        $failed = false;
        $rule('amount', '5000000', function () use (&$failed) { $failed = true; });
        expect($failed)->toBeFalse();
    });

    it('fails for "0"', function () {
        $rule   = makeDepositMoneyRule();
        $failed = false;
        $rule('amount', '0', function () use (&$failed) { $failed = true; });
        expect($failed)->toBeTrue();
    });

    it('fails for empty string', function () {
        $rule   = makeDepositMoneyRule();
        $failed = false;
        $rule('amount', '', function () use (&$failed) { $failed = true; });
        expect($failed)->toBeTrue();
    });

    it('would have FAILED under the old numeric rule for "20.000.000"', function () {
        // Demonstrates the original bug: PHP's is_numeric() returns false for Indonesian format
        expect(is_numeric('20.000.000'))->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. OrderRequest extended status enum + supplier_id on items
// ─────────────────────────────────────────────────────────────────────────────

describe('OrderRequest status enum migration', function () {

    /** Create the prerequisite records that OrderRequestFactory needs. */
    beforeEach(function () {
        $cabang          = Cabang::factory()->create();
        $this->supplier  = Supplier::factory()->create(['cabang_id' => $cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    });

    it('accepts request_approve status', function () {
        $or = OrderRequest::factory()->create([
            'status'       => 'draft',
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);
        $or->update(['status' => 'request_approve']);
        $or->refresh();
        expect($or->status)->toBe('request_approve');
    });

    it('accepts partial status', function () {
        $or = OrderRequest::factory()->create([
            'status'       => 'approved',
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);
        $or->update(['status' => 'partial']);
        $or->refresh();
        expect($or->status)->toBe('partial');
    });

    it('accepts complete status', function () {
        $or = OrderRequest::factory()->create([
            'status'       => 'approved',
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);
        $or->update(['status' => 'complete']);
        $or->refresh();
        expect($or->status)->toBe('complete');
    });

    it('retains existing draft and approved statuses', function () {
        $draft = OrderRequest::factory()->create([
            'status'       => 'draft',
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);
        $approved = OrderRequest::factory()->create([
            'status'       => 'approved',
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);

        expect($draft->status)->toBe('draft')
            ->and($approved->status)->toBe('approved');
    });
});

describe('order_request_items supplier_id column', function () {

    beforeEach(function () {
        $this->cabang    = Cabang::factory()->create();
        $this->supplier  = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create(['supplier_id' => $this->supplier->id]);
        $this->or        = OrderRequest::factory()->create([
            'status'       => 'draft',
            'cabang_id'    => $this->cabang->id,
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
        ]);
    });

    it('has supplier_id column in schema', function () {
        expect(Schema::hasColumn('order_request_items', 'supplier_id'))->toBeTrue();
    });

    it('allows saving supplier_id on an order request item', function () {
        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $this->or->id,
            'product_id'       => $this->product->id,
            'supplier_id'      => $this->supplier->id,
        ]);

        expect($item->supplier_id)->toBe($this->supplier->id);
    });

    it('allows null supplier_id (nullable column)', function () {
        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $this->or->id,
            'product_id'       => $this->product->id,
            'supplier_id'      => null,
        ]);

        expect($item->supplier_id)->toBeNull();
    });
});
