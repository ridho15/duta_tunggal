<?php

namespace App\Services;

use App\Helpers\MoneyHelper;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\User;
use App\Support\CustomerReceiptAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tunggal penerimaan uang customer (Fase 5A / Isu 7), dipakai halaman Buat dan Ubah.
 *
 *  - invoice harus milik customer yang dipilih dan masih memiliki sisa piutang;
 *  - semua invoice dalam SATU penerimaan berasal dari satu cabang (D9-A), dan pengguna non-"all" hanya
 *    boleh menerima untuk cabangnya; cabang penerimaan = cabang invoice (bukan cabang customer);
 *  - nominal yang melebihi sisa tagihan TIDAK PERNAH dipotong senyap (D4-B): ditolak dengan pesan jelas,
 *    kecuali user memilih mencatat kelebihan sebagai Deposit Customer;
 *  - akun penerima harus akun yang boleh menerima uang untuk metode pembayarannya.
 *
 * Kunci galat ValidationException tanpa awalan ("total_payment", "cabang_id", ...); halaman Filament
 * menambahkan "data." agar tampil pada field terkait.
 */
class CustomerReceiptAllocator
{
    private const TOLERANCE = 1.00;

    /**
     * @param  array<int|string, mixed>  $invoiceReceipts  invoice_id => nominal yang diterima
     * @param  bool  $allowDepositOverpayment  true = kelebihan dicatat sebagai Deposit Customer
     * @param  CustomerReceipt|null  $existing  penerimaan yang sedang diubah (nominalnya sendiri tidak dihitung sebagai "sudah dibayar")
     * @return array{applied: array<int, float>, overpayment: float, excess: array<int, float>, cabang_id: int|null, invoices: Collection, invoice_numbers: array<int, string>}
     *
     * @throws ValidationException
     */
    public function plan(
        int $customerId,
        array $invoiceReceipts,
        ?string $paymentMethod,
        bool $allowDepositOverpayment = false,
        ?CustomerReceipt $existing = null,
        ?User $user = null,
    ): array {
        $user ??= Auth::user();

        $amounts = [];
        foreach ($invoiceReceipts as $invoiceId => $amount) {
            $numeric = round(MoneyHelper::safeParse($amount), 2);
            if ($numeric > 0 && is_numeric($invoiceId)) {
                $amounts[(int) $invoiceId] = $numeric;
            }
        }

        if ($amounts === []) {
            throw ValidationException::withMessages([
                'selected_invoices' => 'Pilih minimal satu invoice dan isi nominal yang diterima.',
            ]);
        }

        $invoices = Invoice::withoutGlobalScopes()->whereIn('id', array_keys($amounts))->get()->keyBy('id');
        $ownPrevious = $this->previousAmounts($existing);

        $errors = [];
        $applied = [];
        $excess = [];
        $numbers = [];

        foreach ($amounts as $invoiceId => $amount) {
            /** @var Invoice|null $invoice */
            $invoice = $invoices->get($invoiceId);
            if (! $invoice) {
                $errors['selected_invoices'][] = "Invoice #{$invoiceId} tidak ditemukan.";

                continue;
            }

            $numbers[$invoiceId] = (string) $invoice->invoice_number;

            if (! $this->belongsToCustomer($invoice, $customerId)) {
                $errors['customer_id'][] = "Invoice {$invoice->invoice_number} bukan milik customer yang dipilih.";

                continue;
            }

            $remaining = $this->remainingFor($invoice) + ($ownPrevious[$invoiceId] ?? 0.0);

            if ($remaining <= self::TOLERANCE) {
                $errors['total_payment'][] = "Invoice {$invoice->invoice_number} sudah lunas (tidak ada sisa tagihan).";

                continue;
            }

            if ($amount > $remaining + self::TOLERANCE) {
                $over = round($amount - $remaining, 2);
                $excess[$invoiceId] = $over;
                $applied[$invoiceId] = round($remaining, 2);
            } else {
                if (abs($amount - $remaining) <= self::TOLERANCE) {
                    $applied[$invoiceId] = round($remaining, 2);
                } else {
                    $applied[$invoiceId] = min($amount, round($remaining, 2));
                }
            }
        }

        // ── Cabang: satu cabang per penerimaan, sama dengan cabang invoice ──
        $cabangIds = $invoices->pluck('cabang_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($cabangIds->count() > 1) {
            $errors['cabang_id'][] = 'Invoice yang dipilih berasal dari lebih dari satu cabang ('
                . $invoices->groupBy('cabang_id')->map(fn ($group) => $group->pluck('invoice_number')->implode(', '))->map(fn ($numbers, $cabang) => "cabang #{$cabang}: {$numbers}")->implode('; ')
                . '). Buat penerimaan terpisah untuk setiap cabang.';
        }

        $cabangId = $cabangIds->count() === 1 ? $cabangIds->first() : null;

        if ($cabangId && $user && ! $this->canAccessAllBranches($user) && (int) $user->cabang_id !== $cabangId) {
            $errors['cabang_id'][] = 'Invoice yang dipilih milik cabang lain; Anda hanya dapat mencatat penerimaan untuk cabang Anda sendiri.';
        }

        // ── Kelebihan bayar ──
        $overpayment = round(array_sum($excess), 2);
        if ($overpayment > 0) {
            if (strtolower((string) $paymentMethod) === 'deposit') {
                $errors['total_payment'][] = 'Pembayaran memakai Deposit tidak boleh melebihi sisa tagihan.';
            } elseif (! $allowDepositOverpayment) {
                foreach ($excess as $invoiceId => $over) {
                    $errors['total_payment'][] = sprintf(
                        'Nominal %s melebihi sisa tagihan %s untuk invoice %s (kelebihan %s).',
                        MoneyHelper::rupiah($applied[$invoiceId] + $over),
                        MoneyHelper::rupiah($applied[$invoiceId]),
                        $numbers[$invoiceId] ?? "#{$invoiceId}",
                        MoneyHelper::rupiah($over),
                    );
                }
                $errors['total_payment'][] = 'Kurangi nominal, atau nyalakan opsi "Catat kelebihan sebagai Deposit Customer".';
            } elseif ($invoices->contains(fn (Invoice $invoice) => (float) ($invoice->exchange_rate ?? 1) !== 1.0)) {
                $errors['total_payment'][] = 'Kelebihan pada invoice mata uang asing belum dapat dicatat sebagai Deposit Customer. Sesuaikan nominal.';
            } elseif ($existing) {
                $errors['total_payment'][] = 'Kelebihan bayar tidak dapat dicatat pada penerimaan yang diubah. Kurangi nominal, lalu buat penerimaan baru untuk kelebihannya.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (array $messages) => implode(' ', array_unique($messages)), $errors));
        }

        return [
            'applied' => $applied,
            'overpayment' => $overpayment,
            'excess' => $excess,
            'cabang_id' => $cabangId,
            'invoices' => $invoices,
            'invoice_numbers' => $numbers,
        ];
    }

    /**
     * Akun penerima harus termasuk yang diizinkan untuk metode pembayaran (bukan akun induk,
     * DEPOSITO/INVESTASI, atau akun bebas lain).
     *
     * @throws ValidationException
     */
    public function assertAccountAllowed(?int $coaId, ?string $paymentMethod): void
    {
        if (! $coaId) {
            throw ValidationException::withMessages(['coa_id' => 'Pilih akun kas/bank penerima uang.']);
        }

        if (! CustomerReceiptAccounts::isAllowed($coaId, $paymentMethod)) {
            throw ValidationException::withMessages([
                'coa_id' => 'Akun yang dipilih bukan akun penerima uang untuk metode ' . ($paymentMethod ?: '-')
                    . '. Pilih akun kas/bank yang tersedia pada daftar.',
            ]);
        }
    }

    /** Sisa piutang invoice: dari Account Receivable; tanpa AR = total invoice dikurangi penerimaan tercatat. */
    public function remainingFor(Invoice $invoice): float
    {
        $accountReceivable = $invoice->accountReceivable;

        if ($accountReceivable?->getKey()) {
            return max(0, (float) MoneyHelper::safeParse($accountReceivable->remaining ?? 0));
        }

        $paidTotal = (float) CustomerReceiptItem::query()
            ->where('invoice_id', $invoice->id)
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_total')
            ->value('paid_total');

        return max(0, (float) MoneyHelper::safeParse($invoice->total ?? 0) - $paidTotal);
    }

    public function canAccessAllBranches(User $user): bool
    {
        $manageType = $user->manage_type ?? [];

        return in_array('all', is_array($manageType) ? $manageType : [$manageType], true);
    }

    protected function belongsToCustomer(Invoice $invoice, int $customerId): bool
    {
        if ($invoice->from_model_type !== SaleOrder::class) {
            return false;
        }

        return SaleOrder::withoutGlobalScopes()
            ->whereKey($invoice->from_model_id)
            ->where('customer_id', $customerId)
            ->exists();
    }

    /** @return array<int, float> invoice_id => nominal penerimaan yang sedang diubah */
    protected function previousAmounts(?CustomerReceipt $existing): array
    {
        if (! $existing || ! $existing->exists) {
            return [];
        }

        return CustomerReceiptItem::withoutGlobalScopes()
            ->where('customer_receipt_id', $existing->id)
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id')
            ->map(fn ($total) => (float) $total)
            ->all();
    }
}
