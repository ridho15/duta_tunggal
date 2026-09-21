<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditValidationService
{
    public function canCustomerMakePurchase(Customer $customer, float $orderAmount): array
    {
        $result = [
            'can_purchase' => true,
            'messages' => [],
            'warnings' => []
        ];

        if ($customer->tipe_pembayaran === 'Kredit') {
            $creditLimitCheck = $this->checkCreditLimit($customer, $orderAmount);
            if (!$creditLimitCheck['is_valid']) {
                $result['can_purchase'] = false;
                $result['messages'][] = $creditLimitCheck['message'];
            }

            $overdueCheck = $this->checkOverdueCredits($customer);
            if (!$overdueCheck['is_valid']) {
                $result['can_purchase'] = false;
                $result['messages'][] = $overdueCheck['message'];
            }

            $creditUsage = $this->getCreditUsagePercentage($customer);
            if ($creditUsage >= 80 && $creditUsage < 100) {
                $result['warnings'][] = "Peringatan: Penggunaan kredit customer sudah mencapai {$creditUsage}% dari limit";
            }
        } elseif ($this->policyEnabled()) {
            // D7: COD/Bebas tidak diblokir oleh limit — hanya informasi (tagihan jatuh tempo & piutang berjalan).
            $overdueCheck = $this->checkOverdueCredits($customer);
            if (! $overdueCheck['is_valid']) {
                $result['warnings'][] = 'Peringatan: ' . $overdueCheck['message'];
            }
        }

        return $result;
    }

    /**
     * Check credit limit atomically using a row-level lock to prevent race conditions
     * where two concurrent SO submissions both pass the credit limit check.
     */
    public function checkCreditLimit(Customer $customer, float $orderAmount): array
    {
        if ($customer->kredit_limit <= 0) {
            return [
                'is_valid' => false,
                'message' => 'Customer tidak memiliki kredit limit yang valid'
            ];
        }

        // Lock the customer row for the duration of this check so that concurrent
        // requests cannot both read the same outstanding balance before either commits.
        return DB::transaction(function () use ($customer, $orderAmount) {
            // Re-fetch inside the transaction with a write lock.
            $lockedCustomer = Customer::withoutGlobalScopes()
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->first();

            // T3.2 (flag credit_policy, D27): pemakaian = paparan (piutang + SO aktif yang belum ditagih); perilaku lama: piutang saja.
            $exposure = $this->policyEnabled() ? app(CreditExposure::class) : null;
            $currentCreditUsage = $exposure ? $exposure->exposure($lockedCustomer) : $this->getCurrentCreditUsage($lockedCustomer);
            $totalAfterOrder = $currentCreditUsage + $orderAmount;

            if ($totalAfterOrder > $lockedCustomer->kredit_limit) {
                $openSo = $exposure ? $exposure->openSalesOrders($lockedCustomer)['total'] : 0;

                return [
                    'is_valid' => false,
                    'message' => sprintf(
                        'Kredit limit tidak mencukupi. Limit: Rp %s, Terpakai: Rp %s%s, Order: Rp %s, Total akan menjadi: Rp %s',
                        number_format($lockedCustomer->kredit_limit, 0, ',', '.'),
                        number_format($currentCreditUsage, 0, ',', '.'),
                        $openSo > 0 ? ' (termasuk SO terbuka Rp ' . number_format($openSo, 0, ',', '.') . ')' : '',
                        number_format($orderAmount, 0, ',', '.'),
                        number_format($totalAfterOrder, 0, ',', '.')
                    )
                ];
            }

            return [
                'is_valid' => true,
                'message' => 'Kredit limit mencukupi'
            ];
        });
    }

    public function checkOverdueCredits(Customer $customer): array
    {
        $overdueInvoices = $this->getOverdueInvoices($customer);

        if ($overdueInvoices->count() > 0) {
            $totalOverdue = $overdueInvoices->sum('total');
            $oldestOverdue = $overdueInvoices->first();
            $daysPastDue = Carbon::parse($oldestOverdue->due_date)->diffInDays(Carbon::now());

            return [
                'is_valid' => false,
                'message' => sprintf(
                    'Customer memiliki %d tagihan yang sudah jatuh tempo dengan total Rp %s. Tagihan tertua telah jatuh tempo %d hari (Invoice: %s)',
                    $overdueInvoices->count(),
                    number_format($totalOverdue, 0, ',', '.'),
                    $daysPastDue,
                    $oldestOverdue->invoice_number
                )
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Tidak ada tagihan yang jatuh tempo'
        ];
    }

    public function getCurrentCreditUsage(Customer $customer): float
    {
        return AccountReceivable::where('customer_id', $customer->id)
            ->where('status', PaymentStatus::UNPAID->value)
            ->sum('remaining') ?? 0;
    }

    public function getCreditUsagePercentage(Customer $customer): float
    {
        if ($customer->kredit_limit <= 0) {
            return 0;
        }

        $currentUsage = $this->getCurrentCreditUsage($customer);
        return round(($currentUsage / $customer->kredit_limit) * 100, 2);
    }

    public function getOverdueInvoices(Customer $customer)
    {
        return Invoice::withoutGlobalScope('App\Models\Scopes\CabangScope')
            ->join('sale_orders', function ($join) use ($customer) {
                $join->on('invoices.from_model_id', '=', 'sale_orders.id')
                     ->where('sale_orders.customer_id', '=', $customer->id);
            })
            ->where('invoices.from_model_type', 'App\Models\SaleOrder')
            ->where('invoices.due_date', '<', Carbon::now())
            ->whereIn('invoices.status', ['sent', 'partially_paid'])
            ->select('invoices.*')
            ->orderBy('invoices.due_date', 'asc')
            ->get();
    }

    public function getCreditSummary(Customer $customer): array
    {
        $currentUsage = $this->getCurrentCreditUsage($customer);
        $overdueInvoices = $this->getOverdueInvoices($customer);
        $usagePercentage = $this->getCreditUsagePercentage($customer);

        $legacy = [
            'credit_limit' => $customer->kredit_limit,
            'current_usage' => $currentUsage,
            'available_credit' => $customer->kredit_limit - $currentUsage,
            'usage_percentage' => $usagePercentage,
            'overdue_count' => $overdueInvoices->count(),
            'overdue_total' => $overdueInvoices->sum('total'),
            'tempo_kredit_days' => $customer->tempo_kredit,
            'payment_type' => $customer->tipe_pembayaran
        ];

        // T3.2: kunci tambahan (paparan, SO terbuka, umur tagihan tertua, deposit, kebijakan) untuk SEMUA tipe pembayaran;
        // kunci lama tidak berubah nilainya.
        return $legacy + array_diff_key(app(CreditExposure::class)->summary($customer), $legacy);
    }

    /** Kebijakan kredit T3.2 (paparan, COD/Bebas informasi) aktif? */
    public function policyEnabled(): bool
    {
        return (bool) config('sales.controls.credit_policy', false);
    }
}
