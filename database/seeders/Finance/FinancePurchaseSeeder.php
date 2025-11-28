<?php

namespace Database\Seeders\Finance;

use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinancePurchaseSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): array
    {
        $userId = $this->context->getDefaultUserId();
        $warehouse = $this->context->ensureWarehouse();
        $rak = $this->context->ensureRak();
        [, , $rawMaterial] = $this->context->getSeedProductSet();

        $suppliers = Supplier::whereIn('code', ['SUPP-FIN-001', 'SUPP-FIN-002'])
            ->get()
            ->keyBy('code');

        $purchases = [
            [
                'po_number' => 'PO-2025-001',
                'supplier_code' => 'SUPP-FIN-001',
                'order_date' => Carbon::now()->subDays(95),
                'expected_date' => Carbon::now()->subDays(85),
                'status' => 'completed',
                'items' => [
                    ['product' => $rawMaterial, 'qty' => 500, 'price' => 185000],
                    ['product' => $rawMaterial, 'qty' => 200, 'price' => 180000],
                ],
                'invoice' => [
                    'number' => 'INV-AP-001',
                    'invoice_date' => Carbon::now()->subDays(88),
                    'due_date' => Carbon::now()->subDays(58),
                    'status' => 'partially_paid',
                    'tax_rate' => 0.11,
                ],
                'payments' => [
                    ['number' => 'VP-2025-001A', 'date' => Carbon::now()->subDays(52), 'amount' => 50000000, 'method' => 'Bank Transfer'],
                ],
            ],
            [
                'po_number' => 'PO-2025-002',
                'supplier_code' => 'SUPP-FIN-002',
                'order_date' => Carbon::now()->subDays(50),
                'expected_date' => Carbon::now()->subDays(40),
                'status' => 'completed',
                'items' => [
                    ['product' => $rawMaterial, 'qty' => 300, 'price' => 190000],
                ],
                'invoice' => [
                    'number' => 'INV-AP-002',
                    'invoice_date' => Carbon::now()->subDays(42),
                    'due_date' => Carbon::now()->subDays(12),
                    'status' => 'paid',
                    'tax_rate' => 0.11,
                ],
                'payments' => [
                    ['number' => 'VP-2025-002A', 'date' => Carbon::now()->subDays(8), 'amount' => 63270000, 'method' => 'Bank Transfer'],
                ],
            ],
        ];

        $paymentsSummary = [];

        foreach ($purchases as $purchase) {
            $supplier = $suppliers[$purchase['supplier_code']] ?? null;
            if (!$supplier) {
                continue;
            }

            $tempoHutang = $purchase['tempo_hutang']
                ?? $purchase['invoice']['invoice_date']->diffInDays($purchase['invoice']['due_date']);

            $purchaseOrder = PurchaseOrder::updateOrCreate(
                ['po_number' => $purchase['po_number']],
                [
                    'supplier_id' => $supplier->id,
                    'order_date' => $purchase['order_date'],
                    'expected_date' => $purchase['expected_date'],
                    'status' => $purchase['status'],
                    'total_amount' => 0,
                    'warehouse_id' => $warehouse->id,
                    'tempo_hutang' => $tempoHutang,
                ]
            );

            $subtotal = 0;
            foreach ($purchase['items'] as $item) {
                $lineTotal = $item['qty'] * $item['price'];
                $subtotal += $lineTotal;

                PurchaseOrderItem::updateOrCreate(
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $item['product']->id,
                    ],
                    [
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'discount' => 0,
                        'tax' => 0,
                        'tipe_pajak' => 'Inklusif',
                        'currency_id' => Currency::where('code', 'IDR')->first()?->id,
                    ]
                );
            }

            $taxAmount = round($subtotal * $purchase['invoice']['tax_rate']);
            $total = $subtotal + $taxAmount;

            // persist the computed total to the purchase order record
            $purchaseOrder->update([
                'total_amount' => $total,
            ]);

            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => $purchase['invoice']['number']],
                [
                    'from_model_type' => PurchaseOrder::class,
                    'from_model_id' => $purchaseOrder->id,
                    'invoice_date' => $purchase['invoice']['invoice_date']->toDateString(),
                    'due_date' => $purchase['invoice']['due_date']->toDateString(),
                    'subtotal' => $subtotal,
                    'tax' => $taxAmount,
                    'other_fee' => 0,
                    'total' => $total,
                    'status' => $purchase['invoice']['status'],
                    'supplier_name' => $supplier->name,
                    'supplier_phone' => $supplier->phone,
                ]
            );

            $paid = 0;
            foreach ($purchase['payments'] as $paymentData) {
                $payment = VendorPayment::updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'payment_date' => $paymentData['date']->toDateString(),
                        'total_payment' => $paymentData['amount'],
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'payment_date' => $paymentData['date']->toDateString(),
                        'ntpn' => null,
                        'total_payment' => $paymentData['amount'],
                        'coa_id' => optional($this->context->getCoa('1112.01'))->id,
                        'payment_method' => $paymentData['method'],
                        'notes' => 'Pembayaran atas ' . $invoice->invoice_number,
                        'status' => $paymentData['amount'] >= $total ? 'Paid' : 'Partial',
                        'selected_invoices' => [
                            ['invoice_id' => $invoice->id, 'amount' => $paymentData['amount']],
                        ],
                        'invoice_receipts' => [
                            ['invoice_id' => $invoice->id, 'amount' => $paymentData['amount']],
                        ],
                        'diskon' => 0,
                        'payment_adjustment' => 0,
                    ]
                );

                VendorPaymentDetail::updateOrCreate(
                    [
                        'vendor_payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'method' => $paymentData['method'],
                        'payment_date' => $paymentData['date']->toDateString(),
                    ],
                    [
                        'amount' => $paymentData['amount'],
                        'coa_id' => optional($this->context->getCoa('1112.01'))->id,
                        'notes' => 'Pembayaran ke ' . $supplier->name,
                    ]
                );

                $payment->recalculateTotalPayment();
                $paid += $paymentData['amount'];
                $paymentsSummary[] = [
                    'entity' => $supplier->name,
                    'amount' => $paymentData['amount'],
                    'date' => $paymentData['date']->copy(),
                ];
            }

            $remaining = max(0, $total - $paid);

            $payable = AccountPayable::updateOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'supplier_id' => $supplier->id,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $remaining <= 0 ? 'Lunas' : 'Belum Lunas',
                    'created_by' => $userId,
                ]
            );

            $ageingDays = $this->context->calculateDaysOutstanding($purchase['invoice']['due_date']);
            $invoiceDate = Carbon::parse($invoice->invoice_date);
            $dueDate = Carbon::parse($invoice->due_date);
            AgeingSchedule::updateOrCreate(
                [
                    'from_model_type' => AccountPayable::class,
                    'from_model_id' => $payable->id,
                ],
                [
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'days_outstanding' => $ageingDays,
                    'bucket' => $this->context->determineAgeingBucket($ageingDays),
                ]
            );
        }

        return [
            'payments' => $paymentsSummary,
        ];
    }
}
