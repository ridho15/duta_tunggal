<?php

/**
 * Keputusan Ko Handy #1 (15 Nov): harga penawaran boleh di bawah harga JUAL, tetapi tidak boleh di bawah harga BELI (modal).
 */

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function quotationPayload(array $ctx, float $price, float $discount = 0): array
{
    return [
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'currency_id' => $ctx['idr']->id,
        'action' => 'draft',
        'items' => [[
            'product_id' => $ctx['product']->id,
            'quantity' => 2,
            'unit_price' => $price,
            'discount' => $discount,
            'tax_type' => 'None',
        ]],
    ];
}

function quotationActor(array $ctx): User
{
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');

    return $ctx['user'];
}

it('menolak harga penawaran di bawah harga beli (modal)', function () {
    $ctx = stkContext();                       // product cost_price 5000, sell_price 10000
    $this->actingAs(quotationActor($ctx));

    // 4000 < modal 5000 -> ditolak
    $this->post(route('penjualan.quotations.store'), quotationPayload($ctx, 4000))
        ->assertSessionHasErrors('items.0.unit_price');
    expect(Quotation::count())->toBe(0);
});

it('menerima harga di bawah harga jual tetapi di atas harga beli', function () {
    $ctx = stkContext();
    $this->actingAs(quotationActor($ctx));

    // 7000: di bawah jual 10000, di atas modal 5000 -> boleh
    $this->post(route('penjualan.quotations.store'), quotationPayload($ctx, 7000))
        ->assertSessionHasNoErrors();
    expect(Quotation::count())->toBe(1);
});

it('diskon yang menembus harga beli juga ditolak', function () {
    $ctx = stkContext();
    $this->actingAs(quotationActor($ctx));

    // 10000 - 60% = 4000 < modal 5000 -> ditolak
    $this->post(route('penjualan.quotations.store'), quotationPayload($ctx, 10000, 60))
        ->assertSessionHasErrors('items.0.unit_price');
});
