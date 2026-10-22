<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosShiftService
{
    /**
     * Dapatkan shift kasir yang sedang aktif untuk kasir/pengguna saat ini.
     */
    public function getActiveShift(User $user, ?int $cabangId = null): ?PosShift
    {
        $query = PosShift::withoutGlobalScopes()
            ->where('status', 'open')
            ->where('user_id', $user->id);

        if ($cabangId) {
            $query->where(function ($q) use ($cabangId) {
                $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id');
            });
        }

        return $query->latest('opened_at')->first();
    }

    /**
     * Buka shift kasir baru dengan modal awal (starting cash).
     */
    public function openShift(
        User $user,
        float $startingCash,
        ?int $warehouseId = null,
        ?int $cabangId = null,
        ?string $notes = null
    ): PosShift {
        $activeShift = $this->getActiveShift($user, $cabangId);
        if ($activeShift) {
            throw ValidationException::withMessages([
                'starting_cash' => "Anda masih memiliki shift aktif ({$activeShift->shift_number}). Harap tutup shift tersebut sebelum membuka shift baru.",
            ]);
        }

        $resolvedCabangId = $cabangId ?? $user->cabang_id;

        if (!$warehouseId) {
            $resolvedWarehouse = Warehouse::withoutGlobalScopes()
                ->when($resolvedCabangId, fn($q) => $q->where('cabang_id', $resolvedCabangId))
                ->first();
            $warehouseId = $resolvedWarehouse?->id;
        }

        $shiftNumber = SequentialNumberGenerator::generate('pos_shifts', 'shift_number', 'SHF-');

        return PosShift::create([
            'shift_number' => $shiftNumber,
            'user_id' => $user->id,
            'cabang_id' => $resolvedCabangId,
            'warehouse_id' => $warehouseId,
            'opened_at' => Carbon::now(),
            'starting_cash' => max(0, $startingCash),
            'cash_sales_total' => 0,
            'non_cash_sales_total' => 0,
            'total_sales' => 0,
            'total_transactions' => 0,
            'status' => 'open',
            'notes' => $notes,
        ]);
    }

    /**
     * Tutup shift kasir dan rekap selisih fisik uang di laci (Z-Report).
     */
    public function closeShift(PosShift $shift, float $cashActual, ?string $notes = null): PosShift
    {
        if ($shift->status === 'closed') {
            return $shift;
        }

        return DB::transaction(function () use ($shift, $cashActual, $notes) {
            $orders = PosOrder::withoutGlobalScopes()
                ->where('pos_shift_id', $shift->id)
                ->where('status', 'completed')
                ->get();

            $cashSales = (float) $orders->where('payment_method', 'cash')->sum('grand_total');
            $nonCashSales = (float) $orders->where('payment_method', '!=', 'cash')->sum('grand_total');
            $totalSales = $cashSales + $nonCashSales;
            $totalTransactions = $orders->count();

            $expectedCash = (float) $shift->starting_cash + $cashSales;
            $cashDifference = round($cashActual - $expectedCash, 2);

            $shift->update([
                'closed_at' => Carbon::now(),
                'cash_sales_total' => $cashSales,
                'non_cash_sales_total' => $nonCashSales,
                'total_sales' => $totalSales,
                'total_transactions' => $totalTransactions,
                'cash_actual' => $cashActual,
                'cash_difference' => $cashDifference,
                'status' => 'closed',
                'notes' => $notes ?: $shift->notes,
            ]);

            return $shift->fresh();
        });
    }

    /**
     * Buat rekapitulasi data shift kasir untuk tampilan laporan / Z-Report.
     */
    public function getShiftSummary(PosShift $shift): array
    {
        $orders = PosOrder::withoutGlobalScopes()
            ->where('pos_shift_id', $shift->id)
            ->where('status', 'completed')
            ->with(['items.product', 'cashier'])
            ->get();

        $paymentMethods = [
            'cash' => ['label' => 'Tunai (Cash)', 'count' => 0, 'total' => 0.0],
            'transfer' => ['label' => 'Transfer Bank', 'count' => 0, 'total' => 0.0],
            'qris' => ['label' => 'QRIS', 'count' => 0, 'total' => 0.0],
            'debit' => ['label' => 'Kartu Debit / EDC', 'count' => 0, 'total' => 0.0],
        ];

        foreach ($orders as $order) {
            $method = $order->payment_method ?? 'cash';
            if (!isset($paymentMethods[$method])) {
                $paymentMethods[$method] = [
                    'label' => ucfirst($method),
                    'count' => 0,
                    'total' => 0.0,
                ];
            }
            $paymentMethods[$method]['count']++;
            $paymentMethods[$method]['total'] += (float) $order->grand_total;
        }

        $expectedCash = (float) $shift->starting_cash + ($paymentMethods['cash']['total'] ?? 0);
        $cashActual = $shift->cash_actual !== null ? (float) $shift->cash_actual : null;
        $cashDifference = $shift->cash_difference !== null ? (float) $shift->cash_difference : null;

        return [
            'shift' => $shift,
            'orders' => $orders,
            'total_sales' => (float) $orders->sum('grand_total'),
            'total_transactions' => $orders->count(),
            'payment_methods' => $paymentMethods,
            'starting_cash' => (float) $shift->starting_cash,
            'expected_cash' => $expectedCash,
            'cash_actual' => $cashActual,
            'cash_difference' => $cashDifference,
        ];
    }
}
