<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use Carbon\Carbon;

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
        }

        return $result;
    }

    public function checkCreditLimit(Customer $customer, float $orderAmount): array
    {
        if ($customer->kredit_limit <= 0) {
            return [
                'is_valid' => false,
                'message' => 'Customer tidak memiliki kredit limit yang valid'
            ];
        }

        $currentCreditUsage = $this->getCurrentCreditUsage($customer);
        $totalAfterOrder = $currentCreditUsage + $orderAmount;

        if ($totalAfterOrder > $customer->kredit_limit) {
            return [
                'is_valid' => false,
                'message' => sprintf(
                    'Kredit limit tidak mencukupi. Limit: Rp %s, Terpakai: Rp %s, Order: Rp %s, Total akan menjadi: Rp %s',
                    number_format($customer->kredit_limit, 0, ',', '.'),
                    number_format($currentCreditUsage, 0, ',', '.'),
                    number_format($orderAmount, 0, ',', '.'),
                    number_format($totalAfterOrder, 0, ',', '.')
                )
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Kredit limit mencukupi'
        ];
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
            ->where('status', 'Belum Lunas')
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

        return [
            'credit_limit' => $customer->kredit_limit,
            'current_usage' => $currentUsage,
            'available_credit' => $customer->kredit_limit - $currentUsage,
            'usage_percentage' => $usagePercentage,
            'overdue_count' => $overdueInvoices->count(),
            'overdue_total' => $overdueInvoices->sum('total'),
            'tempo_kredit_days' => $customer->tempo_kredit,
            'payment_type' => $customer->tipe_pembayaran
        ];
    }
}
