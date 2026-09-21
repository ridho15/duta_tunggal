<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\CurrencyConversionResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nota Kredit (T5, D10/D35–D40): koreksi resmi invoice terbit tanpa menghapusnya.
 *
 * Jurnal saat terbit = cermin bagian yang dikreditkan: Dr Retur Penjualan (akun produk) + Dr PPN Keluaran (+ Dr Biaya Pengiriman),
 * Cr Piutang (bagian yang masih terutang) dan Cr Deposit Customer (bagian yang sudah dibayar customer). Piutang tidak pernah negatif.
 * Stok/HPP TIDAK disentuh di sini (retur fisik sudah dijurnal CustomerReturnService).
 */
class CreditNoteService
{
    /** Status invoice yang sudah terbit (boleh dikreditkan). */
    public const POSTED_STATUSES = ['sent', 'unpaid', 'partially_paid', 'paid', 'overdue'];

    private const EPS = 0.05;

    public static function enabled(): bool
    {
        return (bool) config('sales.controls.credit_notes', false);
    }

    /**
     * Sisa kuantitas yang masih dapat dikreditkan per baris invoice.
     *
     * @param  bool  $includeDrafts  ikut menghitung Nota Kredit draf (saat membuat draf baru, mencegah dua draf melebihi qty)
     * @return array<int, float> [invoice_item_id => sisa qty]
     */
    public function creditableQuantities(Invoice $invoice, bool $includeDrafts = true, ?int $ignoreCreditNoteId = null): array
    {
        $credited = CreditNoteItem::query()
            ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
            ->whereNull('credit_notes.deleted_at')
            ->where('credit_notes.invoice_id', $invoice->id)
            ->whereIn('credit_notes.status', $includeDrafts ? [CreditNote::STATUS_DRAFT, CreditNote::STATUS_ISSUED] : [CreditNote::STATUS_ISSUED])
            ->when($ignoreCreditNoteId, fn ($q) => $q->where('credit_notes.id', '!=', $ignoreCreditNoteId))
            ->whereNotNull('credit_note_items.invoice_item_id')
            ->selectRaw('credit_note_items.invoice_item_id as item_id, SUM(credit_note_items.quantity) as qty')
            ->groupBy('credit_note_items.invoice_item_id')
            ->pluck('qty', 'item_id');

        $remaining = [];
        foreach ($invoice->invoiceItem()->get() as $item) {
            $remaining[$item->id] = max(0.0, round((float) $item->quantity - (float) ($credited[$item->id] ?? 0), 2));
        }

        return $remaining;
    }

    /**
     * Buat Nota Kredit DRAF.
     *
     * @param  array<int, float|int|string>  $quantities  [invoice_item_id => qty] (diabaikan untuk tipe pembatalan: semua sisa)
     * @param  array{credit_date?: string|null, include_shipping?: bool, customer_return_id?: int|null, replacement_invoice_id?: int|null}  $options
     *
     * @throws ValidationException
     */
    public function draft(Invoice $invoice, string $type, array $quantities, string $reason, array $options = [], ?User $actor = null): CreditNote
    {
        $this->assertEnabled();

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan Nota Kredit wajib diisi (minimal 10 karakter).']);
        }
        if (! array_key_exists($type, CreditNote::TYPE_LABELS)) {
            throw ValidationException::withMessages(['type' => "Jenis Nota Kredit \"{$type}\" tidak dikenal."]);
        }
        $this->assertInvoiceCreditable($invoice);

        return DB::transaction(function () use ($invoice, $type, $quantities, $reason, $options, $actor) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $remaining = $this->creditableQuantities($invoice, includeDrafts: true);

            $items = $invoice->invoiceItem()->get()->keyBy('id');
            $lines = [];
            $wantsShipping = $type === CreditNote::TYPE_CANCELLATION ? true : (bool) ($options['include_shipping'] ?? false);

            foreach ($items as $id => $item) {
                $qty = $type === CreditNote::TYPE_CANCELLATION ? $remaining[$id] : round((float) ($quantities[$id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $remaining[$id] + 0.0001) {
                    throw ValidationException::withMessages(['quantities' => sprintf('Kuantitas untuk "%s" (%s) melebihi sisa yang dapat dikreditkan (%s).', $item->product?->name ?? "Item #{$id}", $this->fmt($qty), $this->fmt($remaining[$id]))]);
                }
                $lines[] = $this->lineFor($item, $qty, $remaining[$id], includeDrafts: true);
            }

            $fee = $wantsShipping ? $this->creditableShipping($invoice) : 0.0;
            if ($lines === [] && $fee <= self::EPS) {
                throw ValidationException::withMessages(['quantities' => 'Tidak ada kuantitas atau biaya yang dapat dikreditkan pada invoice ini.']);
            }

            $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
            $tax = round(array_sum(array_column($lines, 'tax_amount')), 2);
            $total = round($subtotal + $tax + $fee, 2);

            $cabangId = $invoice->cabang_id ?: null;
            $creditNote = CreditNote::create([
                'credit_note_number' => $this->nextNumber($cabangId),
                'type' => $type, 'status' => CreditNote::STATUS_DRAFT, 'invoice_id' => $invoice->id,
                'customer_id' => $this->customerIdOf($invoice), 'cabang_id' => $cabangId,
                'customer_return_id' => $options['customer_return_id'] ?? null, 'replacement_invoice_id' => $options['replacement_invoice_id'] ?? null,
                'credit_date' => $options['credit_date'] ?? now()->toDateString(), 'reason' => $reason,
                'subtotal' => $subtotal, 'other_fee_amount' => $fee, 'tax_amount' => $tax, 'total' => $total,
                'currency_id' => $invoice->currency_id, 'exchange_rate' => (float) ($invoice->exchange_rate ?: 1),
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            foreach ($lines as $line) {
                $creditNote->items()->create($line);
            }
            if ($fee > self::EPS) {
                $creditNote->items()->create(['invoice_item_id' => null, 'description' => 'Biaya pengiriman', 'quantity' => 1, 'unit_price' => $fee, 'subtotal' => $fee, 'tax_amount' => 0, 'total' => $fee]);
            }

            return $creditNote->load('items');
        });
    }

    /**
     * Nota Kredit tipe retur dari item retur berkeputusan "Refund / Nota Kredit" (D38). Stok dan HPP sudah dijurnal
     * CustomerReturnService (seperti Penggantian) — di sini hanya sisi uang; kuantitas ≤ sisa yang dapat dikreditkan.
     *
     * @throws ValidationException
     */
    public function draftFromReturn(\App\Models\CustomerReturn $customerReturn, ?User $actor = null): CreditNote
    {
        $this->assertEnabled();

        if (! in_array($customerReturn->status, [\App\Models\CustomerReturn::STATUS_APPROVED, \App\Models\CustomerReturn::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['customer_return' => "Retur {$customerReturn->return_number} belum disetujui/selesai; Nota Kredit dibuat setelah barang diterima dan diputuskan."]);
        }
        if (CreditNote::query()->where('customer_return_id', $customerReturn->id)->exists()) {
            throw ValidationException::withMessages(['customer_return' => "Retur {$customerReturn->return_number} sudah memiliki Nota Kredit."]);
        }

        $quantities = [];
        foreach ($customerReturn->customerReturnItems()->where('decision', \App\Models\CustomerReturnItem::DECISION_CREDIT)->whereNotNull('invoice_item_id')->get() as $line) {
            $quantities[$line->invoice_item_id] = ($quantities[$line->invoice_item_id] ?? 0) + (float) $line->quantity;
        }
        if ($quantities === []) {
            throw ValidationException::withMessages(['customer_return' => "Retur {$customerReturn->return_number} tidak memiliki item berkeputusan \"Refund / Nota Kredit\" yang terhubung ke baris invoice."]);
        }

        return $this->draft(
            $customerReturn->invoice,
            CreditNote::TYPE_RETURN,
            $quantities,
            "Retur {$customerReturn->return_number}: ".trim((string) $customerReturn->reason),
            ['customer_return_id' => $customerReturn->id, 'credit_date' => ($customerReturn->completed_at ?? now())->toDateString()],
            $actor
        );
    }

    /**
     * Isi/ubah nomor Nota Retur Pajak (D37, format bebas) — hanya metadata; jurnal dan piutang tidak berubah.
     *
     * @throws ValidationException
     */
    public function setTaxDocumentNumber(CreditNote $creditNote, ?string $number, ?User $actor = null): CreditNote
    {
        $actor ??= Auth::user();
        if (! $actor || ! $actor->hasPermissionTo('approve credit note')) {
            throw ValidationException::withMessages(['approval' => 'Anda tidak memiliki hak akses mengisi nomor Nota Retur Pajak.']);
        }
        if (! $creditNote->isIssued()) {
            throw ValidationException::withMessages(['credit_note' => 'Nomor Nota Retur Pajak diisi saat menerbitkan (draf) atau setelah terbit.']);
        }

        $creditNote->forceFill(['tax_document_number' => filled($number) ? mb_substr(trim((string) $number), 0, 100) : null])->save();

        return $creditNote->refresh();
    }

    /** Hapus draf (yang sudah terbit FINAL — D36). */
    public function deleteDraft(CreditNote $creditNote): void
    {
        if (! $creditNote->isDraft()) {
            throw ValidationException::withMessages(['credit_note' => 'Nota Kredit yang sudah terbit bersifat final dan tidak dapat dihapus; koreksi dengan Nota Kredit baru.']);
        }

        $creditNote->items()->delete();
        $creditNote->delete();
    }

    /**
     * Terbitkan: persetujuan, jurnal, piutang/Deposit, status invoice — atomik dan idempoten (dua panggilan = satu efek).
     *
     * @param  array{override_reason?: string|null, tax_document_number?: string|null}  $options
     *
     * @throws ValidationException
     */
    public function issue(CreditNote $creditNote, ?User $actor = null, array $options = []): CreditNote
    {
        $this->assertEnabled();
        $actor ??= Auth::user();

        if (! $actor || ! $actor->hasPermissionTo('approve credit note')) {
            throw ValidationException::withMessages(['approval' => 'Anda tidak memiliki hak akses menerbitkan Nota Kredit.']);
        }

        return DB::transaction(function () use ($creditNote, $actor, $options) {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);
            if ($locked->isIssued()) {
                return $locked->refresh();   // sudah terbit (klik ganda / proses lain): tanpa efek kedua
            }

            // Persetujuan bertingkat (flag approval_rules): peran × nominal × pemisahan tugas pembuat≠penerbit; override beralasan
            app(ApprovalControlService::class)->enforce($actor, $locked, $options);

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);
            $this->assertInvoiceCreditable($invoice);
            $ar = AccountReceivable::query()->where('invoice_id', $invoice->id)->lockForUpdate()->first();
            if (! $ar) {
                throw ValidationException::withMessages(['invoice' => "Invoice {$invoice->invoice_number} tidak memiliki data piutang; Nota Kredit tidak dapat diterbitkan otomatis."]);
            }

            // Validasi ulang terhadap Nota Kredit yang SUDAH TERBIT (draf lain tidak dihitung)
            $remaining = $this->creditableQuantities($invoice, includeDrafts: false, ignoreCreditNoteId: $locked->id);
            foreach ($locked->items()->whereNotNull('invoice_item_id')->get() as $line) {
                if ((float) $line->quantity > ($remaining[$line->invoice_item_id] ?? 0) + 0.0001) {
                    throw ValidationException::withMessages(['quantities' => 'Kuantitas pada Nota Kredit ini melebihi sisa yang dapat dikreditkan (sudah dikreditkan oleh Nota Kredit lain).']);
                }
            }

            $total = round((float) $locked->total, 2);
            $applyToAr = round(min($total, max(0.0, (float) $ar->remaining)), 2);
            $toDeposit = round($total - $applyToAr, 2);

            $this->postJournal($locked, $invoice, $applyToAr, $toDeposit, $actor);
            $this->applyToReceivable($ar, $invoice, $total, $applyToAr, $toDeposit);
            if ($toDeposit > self::EPS) {
                $this->creditDeposit($locked, $invoice, $toDeposit, $actor);
            }

            $locked->forceFill([
                'status' => CreditNote::STATUS_ISSUED, 'applied_to_ar' => $applyToAr, 'applied_to_deposit' => $toDeposit,
                'issued_by' => $actor->getKey(), 'issued_at' => now(),
                'tax_document_number' => filled($options['tax_document_number'] ?? null) ? trim((string) $options['tax_document_number']) : $locked->tax_document_number,
            ])->save();

            $this->finalizeInvoice($invoice, $ar->refresh(), $locked);

            return $locked->refresh();
        });
    }

    // ------------------------------------------------------------------ internal

    private function assertEnabled(): void
    {
        if (! self::enabled()) {
            throw ValidationException::withMessages(['credit_note' => 'Nota Kredit belum diaktifkan (SALES_CONTROLS_CREDIT_NOTES).']);
        }
    }

    private function assertInvoiceCreditable(Invoice $invoice): void
    {
        $status = strtolower((string) $invoice->status);
        if ($status === 'cancelled' || $status === 'canceled') {
            throw ValidationException::withMessages(['invoice' => "Invoice {$invoice->invoice_number} sudah dibatalkan."]);
        }
        if (! in_array($status, self::POSTED_STATUSES, true)) {
            throw ValidationException::withMessages(['invoice' => "Invoice {$invoice->invoice_number} berstatus \"{$status}\" (belum terbit); invoice draf cukup dihapus."]);
        }
    }

    /** @return array{invoice_item_id: int, product_id: int|null, description: string, quantity: float, unit_price: float, subtotal: float, tax_amount: float, total: float} */
    private function lineFor(InvoiceItem $item, float $qty, float $remainingQty, bool $includeDrafts): array
    {
        $invoiceQty = (float) $item->quantity;
        $creditedSubtotal = $this->creditedAmount($item, 'subtotal', $includeDrafts);
        $creditedTax = $this->creditedAmount($item, 'tax_amount', $includeDrafts);

        // Sisa terakhir memakai selisih terhadap nilai baris agar kumulatif tepat (tanpa sisa pembulatan)
        $isLast = $qty >= $remainingQty - 0.0001;
        $subtotal = $isLast ? round((float) $item->subtotal - $creditedSubtotal, 2) : round($qty * ((float) $item->subtotal / max($invoiceQty, 0.0001)), 2);
        $tax = $isLast ? round((float) $item->tax_amount - $creditedTax, 2) : round($qty * ((float) $item->tax_amount / max($invoiceQty, 0.0001)), 2);

        return [
            'invoice_item_id' => $item->id, 'product_id' => $item->product_id, 'description' => $item->product?->name,
            'quantity' => $qty, 'unit_price' => $qty > 0 ? round($subtotal / $qty, 4) : 0,
            'subtotal' => max(0.0, $subtotal), 'tax_amount' => max(0.0, $tax), 'total' => max(0.0, round($subtotal + $tax, 2)),
        ];
    }

    private function creditedAmount(InvoiceItem $item, string $column, bool $includeDrafts): float
    {
        return (float) CreditNoteItem::query()
            ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
            ->whereNull('credit_notes.deleted_at')
            ->where('credit_note_items.invoice_item_id', $item->id)
            ->whereIn('credit_notes.status', $includeDrafts ? [CreditNote::STATUS_DRAFT, CreditNote::STATUS_ISSUED] : [CreditNote::STATUS_ISSUED])
            ->sum("credit_note_items.{$column}");
    }

    /** Biaya pengiriman invoice yang belum dikreditkan. */
    private function creditableShipping(Invoice $invoice): float
    {
        $creditedFee = (float) CreditNote::query()->where('invoice_id', $invoice->id)->whereIn('status', [CreditNote::STATUS_DRAFT, CreditNote::STATUS_ISSUED])->sum('other_fee_amount');

        return max(0.0, round((float) $invoice->other_fee_total - $creditedFee, 2));
    }

    private function postJournal(CreditNote $creditNote, Invoice $invoice, float $applyToAr, float $toDeposit, User $actor): void
    {
        $settings = app(AccountingSettings::class);
        $arCoa = ($invoice->arCoa?->exists ? $invoice->arCoa : null) ?? $settings->first('accounts_receivable');
        $vatCoa = ($invoice->ppnKeluaranCoa?->exists ? $invoice->ppnKeluaranCoa : null) ?? $settings->first('sales_output_vat');
        $shipCoa = ($invoice->biayaPengirimanCoa?->exists ? $invoice->biayaPengirimanCoa : null) ?? $settings->first('sales_shipping');
        $returnDefault = $settings->first('sales_returns');

        if (! $arCoa) {
            throw new \RuntimeException('Akun Piutang Dagang tidak ditemukan; atur di Pengaturan Akuntansi.');
        }

        $currencyId = is_numeric($invoice->currency_id ?? null) ? (int) $invoice->currency_id : CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');
        $rate = (float) ($invoice->exchange_rate ?: 1);
        $rate = $rate > 0 ? $rate : 1.0;

        $entries = [];
        $push = function (?ChartOfAccount $coa, string $description, float $debit, float $credit, string $missing) use (&$entries) {
            if ($debit <= 0 && $credit <= 0) {
                return;
            }
            if (! $coa) {
                throw new \RuntimeException("{$missing} tidak ditemukan; atur di Pengaturan Akuntansi.");
            }
            $entries[] = ['coa_id' => $coa->id, 'description' => $description, 'debit' => round($debit, 2), 'credit' => round($credit, 2)];
        };

        // Dr Retur Penjualan per baris (akun retur produk; bila kosong akun default), digabung per akun
        $returnByAccount = [];
        foreach ($creditNote->items()->whereNotNull('invoice_item_id')->with('product')->get() as $line) {
            $coa = ($line->product?->salesReturnCoa?->exists ? $line->product->salesReturnCoa : null) ?? $returnDefault;
            if (! $coa) {
                throw new \RuntimeException('Akun Retur Penjualan tidak ditemukan; atur di Pengaturan Akuntansi.');
            }
            $returnByAccount[$coa->id]['coa'] = $coa;
            $returnByAccount[$coa->id]['amount'] = ($returnByAccount[$coa->id]['amount'] ?? 0) + (float) $line->subtotal;
        }
        foreach ($returnByAccount as $row) {
            $push($row['coa'], 'Nota Kredit - Retur/Koreksi Penjualan', $row['amount'], 0, 'Akun Retur Penjualan');
        }
        $push($vatCoa, 'Nota Kredit - Pembalikan PPn Keluaran', (float) $creditNote->tax_amount, 0, 'Akun PPN Keluaran');
        $push($shipCoa, 'Nota Kredit - Pembalikan Biaya Pengiriman', (float) $creditNote->other_fee_amount, 0, 'Akun Biaya Pengiriman');
        $push($arCoa, 'Nota Kredit - Pengurangan Piutang', 0, $applyToAr, 'Akun Piutang Dagang');
        if ($toDeposit > self::EPS) {
            $push($this->depositCoa($invoice), 'Nota Kredit - Kelebihan Bayar ke Deposit Customer', 0, $toDeposit, 'Akun Deposit Customer');
        }

        $debit = round(array_sum(array_column($entries, 'debit')), 2);
        $credit = round(array_sum(array_column($entries, 'credit')), 2);
        if (abs($debit - $credit) > self::EPS) {
            throw new \RuntimeException("Jurnal Nota Kredit {$creditNote->credit_note_number} tidak seimbang (debit {$debit} ≠ kredit {$credit}); dibatalkan.");
        }

        foreach ($entries as $entry) {
            JournalEntry::create($entry + [
                'date' => $creditNote->credit_date?->toDateString() ?? now()->toDateString(), 'reference' => $creditNote->credit_note_number, 'journal_type' => 'credit_note',
                'source_type' => CreditNote::class, 'source_id' => $creditNote->id, 'cabang_id' => $creditNote->cabang_id,
                'currency_id' => $currencyId, 'exchange_rate' => $rate, 'amount_original_currency' => round(max($entry['debit'], $entry['credit']) / $rate, 4),
            ]);
        }
    }

    private function depositCoa(Invoice $invoice): ?ChartOfAccount
    {
        $settings = app(AccountingSettings::class);

        return $settings->explicit('customer_deposit') ?? $settings->first('customer_deposit');
    }

    /** AR.total −= NK; remaining −= bagian piutang; paid −= bagian yang dialihkan ke Deposit. Invarian: total − paid = remaining. */
    private function applyToReceivable(AccountReceivable $ar, Invoice $invoice, float $total, float $applyToAr, float $toDeposit): void
    {
        $rate = (float) ($ar->exchange_rate ?? 1);
        $rate = $rate > 0 ? $rate : 1.0;

        $ar->total = max(0.0, (float) $ar->total - $total);
        $ar->paid = max(0.0, (float) $ar->paid - $toDeposit);
        $ar->remaining = max(0.0, (float) $ar->remaining - $applyToAr);
        $ar->total_original = round((float) $ar->total / $rate, 4);
        $ar->paid_original = round((float) $ar->paid / $rate, 4);
        $ar->remaining_original = round((float) $ar->remaining / $rate, 4);
        $ar->status = $ar->remaining > self::EPS ? 'Belum Lunas' : 'Lunas';
        $ar->save();

        if ($ar->remaining <= self::EPS && $ar->ageingSchedule()->exists()) {
            $ar->ageingSchedule->delete();   // tak ada lagi yang jatuh tempo
        }
    }

    private function creditDeposit(CreditNote $creditNote, Invoice $invoice, float $amount, User $actor): void
    {
        $customerId = $this->customerIdOf($invoice);
        $deposit = Deposit::where('from_model_type', \App\Models\Customer::class)->where('from_model_id', $customerId)->lockForUpdate()->first();

        if (! $deposit) {
            $coa = $this->depositCoa($invoice);
            $deposit = Deposit::create([
                'from_model_type' => \App\Models\Customer::class, 'from_model_id' => $customerId, 'amount' => $amount, 'used_amount' => 0,
                'remaining_amount' => $amount, 'coa_id' => $coa?->id, 'status' => 'active', 'created_by' => $actor->getKey(),
                'deposit_number' => (new DepositNumberGenerator)->generate(), 'note' => "Dari Nota Kredit {$creditNote->credit_note_number}",
            ]);
        } else {
            // Tanpa observer: DepositObserver::updated menulis ulang jurnal DEP-{id} saat amount berubah (H3); jurnal Deposit sudah dibuat di sini.
            $deposit->forceFill(['amount' => (float) $deposit->amount + $amount, 'remaining_amount' => (float) $deposit->remaining_amount + $amount, 'status' => 'active'])->saveQuietly();
        }

        DepositLog::create([
            'deposit_id' => $deposit->id, 'type' => 'add', 'reference_type' => CreditNote::class, 'reference_id' => $creditNote->id,
            'amount' => $amount, 'note' => "Kelebihan bayar dari Nota Kredit {$creditNote->credit_note_number} (invoice {$invoice->invoice_number})", 'created_by' => $actor->getKey(),
        ]);
    }

    /** Status invoice: penuh dikreditkan → cancelled; piutang habis → paid. Tanpa observer (jurnal sudah dikerjakan di sini). */
    private function finalizeInvoice(Invoice $invoice, AccountReceivable $ar, CreditNote $creditNote): void
    {
        $creditedTotal = (float) CreditNote::query()->where('invoice_id', $invoice->id)->where('status', CreditNote::STATUS_ISSUED)->sum('total');
        $fully = $creditedTotal >= (float) $invoice->total - self::EPS;

        if ($fully) {
            $invoice->forceFill([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $creditNote->issued_by, 'cancel_reason' => $creditNote->reason,
            ])->saveQuietly();

            return;
        }

        if ($ar->remaining <= self::EPS) {
            $invoice->forceFill(['status' => 'paid'])->saveQuietly();
        } elseif ((float) $ar->paid > 0) {
            $invoice->forceFill(['status' => 'partially_paid'])->saveQuietly();
        }
    }

    private function customerIdOf(Invoice $invoice): ?int
    {
        $id = $invoice->fromModel?->customer_id ?? null;

        return $id ? (int) $id : null;
    }

    private function nextNumber(?int $cabangId): string
    {
        if (DocumentNumberService::enabled()) {
            return app(DocumentNumberService::class)->next('credit_note', $cabangId);
        }

        return SequentialNumberGenerator::generate('credit_notes', 'credit_note_number', 'CN-', 4, 'Ymd');
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') ?: '0';
    }
}
