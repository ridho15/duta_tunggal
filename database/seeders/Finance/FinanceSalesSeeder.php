<?php

namespace Database\Seeders\Finance;

use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceSalesSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): array
    {
        $userId = $this->context->getDefaultUserId();
        $warehouse = $this->context->ensureWarehouse();
        $rak = $this->context->ensureRak();
        [$finishedPrimary, $finishedSecondary] = $this->context->getSeedProductSet();
        $rawMaterial = $this->context->getSeedProductSet()[2];
        // suppress unused warning
        unset($rawMaterial);

        $customers = Customer::whereIn('code', ['CUST-FIN-001', 'CUST-FIN-002', 'CUST-FIN-003'])
            ->get()
            ->keyBy('code');

        $sales = [
            [
                'so_number' => 'SO-2025-001',
                'customer_code' => 'CUST-FIN-001',
                'order_date' => Carbon::now()->subDays(70),
                'delivery_date' => Carbon::now()->subDays(55),
                'status' => 'confirmed',
                'items' => [
                    ['product' => $finishedPrimary, 'qty' => 10, 'price' => 12500000],
                    ['product' => $finishedSecondary, 'qty' => 10, 'price' => 2500000],
                ],
                'invoice' => [
                    'number' => 'INV-AR-001',
                    'invoice_date' => Carbon::now()->subDays(60),
                    'due_date' => Carbon::now()->subDays(15),
                    'status' => 'paid',
                    'tax_rate' => 0.11,
                ],
                'receipts' => [
                    ['number' => 'CR-2025-001A', 'date' => Carbon::now()->subDays(18), 'amount' => 90000000, 'method' => 'Bank Transfer'],
                    ['number' => 'CR-2025-001B', 'date' => Carbon::now()->subDays(14), 'amount' => 76500000, 'method' => 'Bank Transfer'],
                ],
            ],
            [
                'so_number' => 'SO-2025-002',
                'customer_code' => 'CUST-FIN-002',
                'order_date' => Carbon::now()->subDays(90),
                'delivery_date' => Carbon::now()->subDays(80),
                'status' => 'received',
                'items' => [
                    ['product' => $finishedPrimary, 'qty' => 8, 'price' => 11000000],
                    ['product' => $finishedSecondary, 'qty' => 12, 'price' => 2200000],
                ],
                'invoice' => [
                    'number' => 'INV-AR-002',
                    'invoice_date' => Carbon::now()->subDays(75),
                    'due_date' => Carbon::now()->subDays(45),
                    'status' => 'partially_paid',
                    'tax_rate' => 0.11,
                ],
                'receipts' => [
                    ['number' => 'CR-2025-002A', 'date' => Carbon::now()->subDays(40), 'amount' => 60000000, 'method' => 'Bank Transfer'],
                ],
            ],
            [
                'so_number' => 'SO-2025-003',
                'customer_code' => 'CUST-FIN-003',
                'order_date' => Carbon::now()->subDays(150),
                'delivery_date' => Carbon::now()->subDays(140),
                'status' => 'confirmed',
                'items' => [
                    ['product' => $finishedPrimary, 'qty' => 5, 'price' => 10500000],
                    ['product' => $finishedSecondary, 'qty' => 10, 'price' => 1950000],
                ],
                'invoice' => [
                    'number' => 'INV-AR-003',
                    'invoice_date' => Carbon::now()->subDays(140),
                    'due_date' => Carbon::now()->subDays(110),
                    'status' => 'overdue',
                    'tax_rate' => 0.11,
                ],
                'receipts' => [],
            ],
        ];

        $receiptsSummary = [];
        $invoices = [];

        foreach ($sales as $sale) {
            $customer = $customers[$sale['customer_code']] ?? null;
            if (!$customer) {
                continue;
            }

            $saleOrder = SaleOrder::updateOrCreate(
                ['so_number' => $sale['so_number']],
                [
                    'customer_id' => $customer->id,
                    'order_date' => $sale['order_date'],
                    'delivery_date' => $sale['delivery_date'],
                    'status' => $sale['status'],
                    'total_amount' => 0,
                    'created_by' => $userId,
                ]
            );

            $subtotal = 0;
            foreach ($sale['items'] as $item) {
                $lineTotal = $item['qty'] * $item['price'];
                $subtotal += $lineTotal;

                SaleOrderItem::updateOrCreate(
                    [
                        'sale_order_id' => $saleOrder->id,
                        'product_id' => $item['product']->id,
                    ],
                    [
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'discount' => 0,
                        'tax' => 0,
                        'warehouse_id' => $warehouse->id,
                        'rak_id' => $rak->id,
                    ]
                );
            }

            $taxAmount = round($subtotal * $sale['invoice']['tax_rate']);
            $total = $subtotal + $taxAmount;

            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => $sale['invoice']['number']],
                [
                    'from_model_type' => SaleOrder::class,
                    'from_model_id' => $saleOrder->id,
                    'invoice_date' => $sale['invoice']['invoice_date']->toDateString(),
                    'due_date' => $sale['invoice']['due_date']->toDateString(),
                    'subtotal' => $subtotal,
                    'tax' => $taxAmount,
                    'other_fee' => 0,
                    'total' => $total,
                    'status' => $sale['invoice']['status'],
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                ]
            );

            foreach ($sale['items'] as $item) {
                InvoiceItem::updateOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'product_id' => $item['product']->id,
                    ],
                    [
                        'quantity' => $item['qty'],
                        'price' => $item['price'],
                        'total' => $item['qty'] * $item['price'],
                    ]
                );
            }

            $paid = 0;
            foreach ($sale['receipts'] as $receiptData) {
                $receipt = CustomerReceipt::updateOrCreate(
                    ['ntpn' => $receiptData['number']],
                    [
                        'ntpn' => $receiptData['number'],
                        'invoice_id' => $invoice->id,
                        'customer_id' => $customer->id,
                        'payment_date' => $receiptData['date']->toDateString(),
                        'total_payment' => $receiptData['amount'],
                        'notes' => 'Pelunasan atas ' . $invoice->invoice_number,
                        'status' => $receiptData['amount'] >= $total ? 'Paid' : 'Partial',
                        'payment_method' => $receiptData['method'],
                        'coa_id' => optional($this->context->getCoa('1112.01'))->id,
                        'selected_invoices' => [
                            ['invoice_id' => $invoice->id, 'amount' => $receiptData['amount']],
                        ],
                        'invoice_receipts' => [
                            ['invoice_id' => $invoice->id, 'amount' => $receiptData['amount']],
                        ],
                        'diskon' => 0,
                        'payment_adjustment' => 0,
                    ]
                );

                CustomerReceiptItem::updateOrCreate(
                    [
                        'customer_receipt_id' => $receipt->id,
                        'invoice_id' => $invoice->id,
                        'method' => $receiptData['method'],
                        'payment_date' => $receiptData['date']->toDateString(),
                    ],
                    [
                        'amount' => $receiptData['amount'],
                        'coa_id' => optional($this->context->getCoa('1112.01'))->id,
                    ]
                );

                $receipt->recalculateTotalPayment();
                $paid += $receiptData['amount'];
                $receiptsSummary[] = [
                    'entity' => $customer->name,
                    'amount' => $receiptData['amount'],
                    'date' => $receiptData['date']->copy(),
                ];
            }

            $remaining = max(0, $total - $paid);

            $receivable = AccountReceivable::updateOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'customer_id' => $customer->id,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $remaining <= 0 ? 'Lunas' : 'Belum Lunas',
                    'created_by' => $userId,
                ]
            );

            $ageingDays = $this->context->calculateDaysOutstanding($sale['invoice']['due_date']);
            AgeingSchedule::updateOrCreate(
                [
                    'from_model_type' => AccountReceivable::class,
                    'from_model_id' => $receivable->id,
                ],
                [
                    'invoice_date' => $sale['invoice']['invoice_date']->toDateString(),
                    'due_date' => $sale['invoice']['due_date']->toDateString(),
                    'days_outstanding' => $ageingDays,
                    'bucket' => $this->context->determineAgeingBucket($ageingDays),
                ]
            );

            $invoices[] = $invoice;
        }

        return [
            'receipts' => $receiptsSummary,
            'invoices' => $invoices,
        ];
    }
}
