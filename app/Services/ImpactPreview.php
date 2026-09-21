<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CustomerReceipt;
use App\Support\LineAmounts;
use App\Support\StatusLabels;
use Illuminate\Support\HtmlString;

/**
 * Pratinjau dampak aksi berefek (T7.3, usulan 16): "preview = eksekusi". Angka dihitung oleh fungsi MURNI yang sama dengan eksekutor
 * (`CreditNoteService::impact()/journalPlan()`, `CustomerReceiptCancellation::restoreTargets()/reversibleJournals()`), sehingga pesan
 * di modal konfirmasi tidak pernah berbeda dari hasil nyata. Tidak menulis apa pun.
 *
 * @phpstan-type Preview array{title: string, lines: array<int, string>, warnings: array<int, string>, data: array<string, mixed>}
 */
class ImpactPreview
{
    /** @return array{title: string, lines: array<int, string>, warnings: array<int, string>, data: array<string, mixed>} */
    public function creditNoteIssue(CreditNote $creditNote): array
    {
        $creditNote->loadMissing('invoice');
        $invoice = $creditNote->invoice;
        $ar = AccountReceivable::query()->where('invoice_id', $invoice->id)->first();
        $out = ['title' => "Dampak penerbitan Nota Kredit {$creditNote->credit_note_number}", 'lines' => [], 'warnings' => [], 'data' => []];

        if (! $ar) {
            $out['warnings'][] = "Invoice {$invoice->invoice_number} tidak memiliki data piutang; Nota Kredit tidak dapat diterbitkan otomatis.";

            return $out;
        }

        $service = app(CreditNoteService::class);
        $impact = $service->impact($creditNote, $invoice, $ar);
        $money = fn (float $v) => LineAmounts::money($v);
        $out['data'] = $impact;

        if ($impact['apply_to_ar'] > 0) {
            $out['lines'][] = sprintf('Piutang invoice %s turun %s (sisa %s → %s).', $invoice->invoice_number, $money($impact['apply_to_ar']), $money((float) $ar->remaining), $money($impact['ar_after']['remaining']));
        }
        if ($impact['to_deposit'] > 0) {
            $out['lines'][] = sprintf('%s yang sudah dibayar customer menjadi Deposit Customer.', $money($impact['to_deposit']));
        }

        try {
            $plan = $service->journalPlan($creditNote, $invoice, $impact['apply_to_ar'], $impact['to_deposit']);
            $names = ChartOfAccount::query()->whereIn('id', array_column($plan, 'coa_id'))->pluck('name', 'id');
            $out['data']['journal'] = $plan;
            $out['lines'][] = sprintf('Jurnal %d baris (total %s): %s.', count($plan), $money((float) array_sum(array_column($plan, 'debit'))), collect($plan)->map(
                fn ($e) => ($e['debit'] > 0 ? 'Dr ' : 'Cr ').($names[$e['coa_id']] ?? "Akun #{$e['coa_id']}").' '.$money($e['debit'] > 0 ? $e['debit'] : $e['credit'])
            )->implode('; '));
        } catch (\RuntimeException $e) {
            $out['warnings'][] = $e->getMessage();
        }

        if ($impact['fully_credited']) {
            $out['lines'][] = "Invoice {$invoice->invoice_number} menjadi Dibatalkan (seluruh nilainya dikreditkan).";
        } elseif ($impact['invoice_status_after'] !== null && $impact['invoice_status_after'] !== $invoice->status) {
            $out['lines'][] = sprintf('Status invoice %s menjadi %s.', $invoice->invoice_number, StatusLabels::label('invoice', $impact['invoice_status_after']));
        }
        $out['lines'][] = 'Setelah terbit Nota Kredit bersifat final; koreksi dengan Nota Kredit baru.';

        return $out;
    }

    /** @return array{title: string, lines: array<int, string>, warnings: array<int, string>, data: array<string, mixed>} */
    public function receiptCancellation(CustomerReceipt $receipt): array
    {
        $service = app(CustomerReceiptCancellation::class);
        $money = fn (float $v) => LineAmounts::money($v);
        $out = ['title' => 'Dampak pembatalan penerimaan', 'lines' => [], 'warnings' => [], 'data' => []];

        if ($blocker = $service->blocker($receipt)) {
            $out['warnings'][] = $blocker;

            return $out;
        }

        $journals = $service->reversibleJournals($receipt);
        $out['data']['reversed_entries'] = $journals->count();
        $out['data']['reversed_debit'] = round((float) $journals->sum('debit'), 2);
        $out['lines'][] = sprintf('%d entri jurnal (total debit %s) dibalik dengan entri cermin; jurnal asli tetap tersimpan.', $journals->count(), $money($out['data']['reversed_debit']));

        $out['data']['restored'] = [];
        foreach ($service->restoreTargets($receipt) as $target) {
            $ar = AccountReceivable::query()->with('invoice')->where('invoice_id', $target['invoice_id'])->first();
            if (! $ar) {
                continue;
            }
            $remainingAfter = (float) $ar->remaining + $target['amount'];
            $paidAfter = max(0.0, (float) $ar->paid - $target['amount']);
            $statusAfter = $paidAfter > 0 ? 'partially_paid' : 'unpaid';
            $out['data']['restored'][$target['invoice_id']] = ['amount' => $target['amount'], 'remaining_after' => $remainingAfter, 'invoice_status_after' => $statusAfter];
            $out['lines'][] = sprintf('Piutang invoice %s bertambah %s (sisa %s → %s); status menjadi %s.', $ar->invoice?->invoice_number ?? "#{$target['invoice_id']}", $money($target['amount']),
                $money((float) $ar->remaining), $money($remainingAfter), StatusLabels::label('invoice', $statusAfter));
        }
        $out['lines'][] = 'Penerimaan berstatus Dibatalkan dan tidak dapat dibatalkan dua kali.';

        return $out;
    }

    /** Daftar poin untuk modal konfirmasi Filament (nilai di-escape). */
    public function html(array $preview): HtmlString
    {
        $items = collect($preview['warnings'])->map(fn ($w) => '<li style="color:#b45309">⚠ '.e($w).'</li>')
            ->merge(collect($preview['lines'])->map(fn ($l) => '<li>'.e($l).'</li>'))->implode('');

        return new HtmlString('<div style="text-align:left"><strong>'.e($preview['title']).'</strong><ul style="list-style:disc;padding-left:1.25rem;margin-top:.5rem">'.$items.'</ul></div>');
    }
}
