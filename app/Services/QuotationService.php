<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function updateTotalAmount($quotation)
    {
        $total = 0;
        foreach ($quotation->quotationItem as $item) {
            $total += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax, $item->tax_type ?? 'PPN Excluded');
        }

        $quotation->update([
            'total_amount' => $total
        ]);
    }

    public function requestApprove($quotation)
    {
        return $quotation->update([
            'status' => 'request_approve',
            'request_approve_by' => Auth::user()->id,
            'request_approve_at' => Carbon::now()
        ]);
    }

    public function approve($quotation)
    {
        // Quotation yang sudah lewat masa berlakunya tidak boleh disetujui: penawaran harga
        // yang disetujui harus langsung dapat dipakai.
        if ($quotation->valid_until !== null && $quotation->valid_until->copy()->startOfDay()->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'status' => "Quotation {$quotation->quotation_number} sudah melewati masa berlaku ({$quotation->valid_until->format('d/m/Y')}) dan tidak dapat disetujui. Tolak lalu buat revisi dengan tanggal berlaku yang baru.",
            ]);
        }

        return $quotation->update([
            'status' => 'approve',
            'approve_by' => Auth::user()->id,
            'approve_at' => Carbon::now()
        ]);
    }

    public function reject($quotation)
    {
        return $quotation->update([
            'status' => 'reject',
            'reject_by' => Auth::user()->id,
            'reject_at' => Carbon::now()
        ]);
    }

    /**
     * Buat REVISI dari quotation yang terkunci (Disetujui / Kedaluwarsa): salin header + item
     * menjadi Draft baru bernomor "<nomor-dasar>-R{n}". Versi lama tidak diubah; ia baru
     * ditandai "digantikan" saat revisi DISETUJUI (lihat hook updated() di model Quotation).
     *
     * @throws ValidationException bila quotation tidak dapat direvisi atau sudah ada revisi berjalan
     */
    public function createRevision(Quotation $source): Quotation
    {
        if (! in_array($source->status, [Quotation::STATUS_APPROVE, Quotation::STATUS_EXPIRED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Revisi hanya dapat dibuat dari quotation yang sudah Disetujui atau Kedaluwarsa. Quotation Draft/Ditolak dapat langsung diubah.',
            ]);
        }

        if ($source->superseded_at !== null) {
            throw ValidationException::withMessages([
                'status' => "Quotation {$source->quotation_number} sudah digantikan revisi yang lebih baru. Revisi dari versi terbaru.",
            ]);
        }

        $open = Quotation::query()
            ->where('revision_of_id', $source->id)
            ->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_REQUEST_APPROVE])
            ->first();
        if ($open) {
            throw ValidationException::withMessages([
                'status' => "Sudah ada revisi yang sedang berjalan: {$open->quotation_number} ({$open->statusLabel()}). Selesaikan atau hapus revisi tersebut terlebih dahulu.",
            ]);
        }

        return DB::transaction(function () use ($source) {
            $source->loadMissing('quotationItem');

            $revisionNo = ((int) $source->revision_no) + 1;
            $baseNumber = preg_replace('/-R\d+$/', '', (string) $source->quotation_number);
            do {
                $number = $baseNumber . '-R' . $revisionNo;
                $exists = Quotation::withoutGlobalScopes()->withTrashed()->where('quotation_number', $number)->exists();
                if ($exists) {
                    $revisionNo++;
                }
            } while ($exists);

            $revision = Quotation::create([
                'quotation_number' => $number,
                'revision_of_id' => $source->id,
                'revision_no' => $revisionNo,
                'customer_id' => $source->customer_id,
                'cabang_id' => $source->cabang_id,
                'date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'currency_id' => $source->currency_id,
                'exchange_rate' => $source->exchange_rate,
                'tempo_pembayaran' => $source->tempo_pembayaran,
                'shipped_to' => $source->shipped_to,
                'notes' => $source->notes,
                'total_amount' => $source->total_amount,
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            foreach ($source->quotationItem as $item) {
                QuotationItem::create([
                    'quotation_id' => $revision->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                    'tax_type' => $item->tax_type,
                    'notes' => $item->notes,
                ]);
            }

            $this->updateTotalAmount($revision->fresh());

            return $revision->fresh();
        });
    }

    /**
     * Tandai quotation Approved yang valid_until-nya sudah lewat sebagai Kedaluwarsa.
     *
     * @return Collection<int, Quotation> quotation yang (akan) ditandai kedaluwarsa
     */
    public function expireOverdue(bool $dryRun = false): Collection
    {
        $overdue = Quotation::withoutGlobalScopes()->overdueApproved()->orderBy('id')->get();

        if (! $dryRun) {
            foreach ($overdue as $quotation) {
                $quotation->update([
                    'status' => Quotation::STATUS_EXPIRED,
                    'expired_at' => now(),
                ]);
            }
        }

        return $overdue;
    }

    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'QO-' . $date . '-';

        // Use sequential numbering (consistent with SO/Invoice generators) to
        // guarantee monotonic, audit-friendly document numbers.
        $max = Quotation::withoutGlobalScopes()
            ->where('quotation_number', 'like', $prefix . '%')
            ->max('quotation_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts
        do {
            $candidate = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $exists = Quotation::withoutGlobalScopes()
                ->where('quotation_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }
}
