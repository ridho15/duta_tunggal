<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Currency;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Customer;
use App\Models\Cabang;
use App\Support\CurrencyConversionResolver;

class QuotationItemHeaderCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_items_inherit_header_currency_and_store_idr_anchor()
    {
        $cabang = Cabang::create([
            'kode' => 'CB-TEST',
            'nama' => 'Cabang Test',
            'alamat' => 'Alamat Cabang Test',
        ]);

        $customer = Customer::create([
            'name' => 'Customer A',
            'code' => 'CUST-A',
            'address' => 'Addr',
            'nik_npwp' => '123456789012345',
            'telephone' => '021000000',
            'phone' => '081234567890',
            'email' => 'a@example.com',
            'perusahaan' => 'PT A',
            'tipe' => 'PRI',
            'fax' => '021000',
            'tempo_kredit' => 0,
            'kredit_limit' => 0,
            'cabang_id' => $cabang->id,
        ]);

        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'to_rupiah' => 15000,
        ]);

        $productCategory = \App\Models\ProductCategory::create([
            'name' => 'Default Category',
            'kode' => 'PC-1',
        ]);

        // No product created: test will prepare item without persisting to DB

        $quotation = Quotation::create([
            'quotation_number' => 'Q-TEST-1',
            'customer_id' => $customer->id,
            'date' => now(),
            'valid_until' => now()->addDays(30),
            'tempo_pembayaran' => 0,
            'total_amount' => 0,
            'status' => 'draft',
            'cabang_id' => $cabang->id,
            'currency_id' => $usd->id,
            'exchange_rate' => CurrencyConversionResolver::resolveRate($usd->id),
        ]);

        $item = new QuotationItem([
            'product_id' => null,
            'notes' => 'Test item',
            'quantity' => 2,
            'unit_price' => 10,
            'discount' => 0,
            'tax' => 0,
            'tax_type' => 'None',
        ]);

        // Prepare computed fields without persisting to DB
        $item->setRelation('quotation', $quotation);
        $item->prepareForSave();

        $expectedAnchor = CurrencyConversionResolver::convertToIdrHighPrecision((string) 10, $usd->id);
        $this->assertEquals((float) $expectedAnchor, (float) $item->unit_price_idr);
    }
}
