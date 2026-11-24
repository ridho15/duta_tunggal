<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\MenuAccess;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan Ko Handy 24 Nov 2026 — DPP & PPN Purchase Invoice boleh disamakan dengan Faktur Pajak supplier.
 *
 *  - Default = hitungan sistem. Bila Faktur Pajak supplier beda karena pembulatan (mis. supplier menghitung dari harga satuan
 *    sebelum PPN yang dibulatkan), petugas mengisi DPP/PPN sesuai faktur SEBELUM faktur diterbitkan.
 *  - Selisih maksimal Rp500 per kolom (lebih dari itu kemungkinan besar harga satuan salah).
 *  - Hanya Finance / Finance Manager (dan super admin). Petugas wajib menginfokan ke Finance Manager; faktur ditandai.
 *  - Jurnal: PPN Masukan & Hutang = angka faktur; barang belum tertagih tetap nilai penerimaan; selisih DPP → 5160 Selisih
 *    Pembelian (lewat price_variance_amount yang sudah diposting LedgerPostingService).
 */
class PurchaseInvoiceTaxAdjustment
{
    public const MAX_DIFFERENCE = 500.0;

    public static function canAdjust($user): bool
    {
        return $user && (MenuAccess::isSuper($user)
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(MenuAccess::FINANCE_WRITE_ROLES)));
    }

    /**
     * Terapkan (atau lepas) penyesuaian. $fakturDpp / $fakturPpn null = ikut hitungan sistem.
     * Dipanggil setelah header & baris faktur disimpan dan SEBELUM posting.
     */
    /**
     * @param  bool  $authorized  true = dipanggil ulang oleh sistem (posting) untuk penyesuaian yang sudah disetujui sebelumnya
     * @param  array|null  $keepAudit  jejak lama (siapa & kapan) dipertahankan saat penerapan ulang
     */
    public function apply(Invoice $invoice, ?float $fakturDpp, ?float $fakturPpn, $user, bool $authorized = false, ?array $keepAudit = null): void
    {
        $invoice->load('invoiceItem');   // muat ulang: relasi bisa ter-cache kosong sebelum baris faktur disimpan
        $this->revert($invoice);

        $systemDpp = round((float) $invoice->subtotal, 2);
        $systemPpn = round((float) $invoice->invoiceItem->sum('tax_amount'), 2);
        $fakturDpp = $fakturDpp === null ? $systemDpp : round($fakturDpp, 2);
        $fakturPpn = $fakturPpn === null ? $systemPpn : round($fakturPpn, 2);
        $diffDpp = round($fakturDpp - $systemDpp, 2);
        $diffPpn = round($fakturPpn - $systemPpn, 2);

        if (abs($diffDpp) < 0.005 && abs($diffPpn) < 0.005) {
            return;
        }

        if (! $authorized && ! self::canAdjust($user)) {
            throw ValidationException::withMessages([
                'faktur_ppn' => 'DPP/PPN faktur hanya boleh diubah oleh Finance / Finance Manager. Solusi: kosongkan isian DPP/PPN faktur '
                    . '(pakai hitungan sistem) atau minta petugas Finance yang menyimpan faktur ini.',
            ]);
        }

        $errors = [];
        foreach (['faktur_dpp' => ['DPP', $diffDpp], 'faktur_ppn' => ['PPN', $diffPpn]] as $field => [$label, $diff]) {
            if (abs($diff) > self::MAX_DIFFERENCE) {
                $errors[$field] = sprintf(
                    'Selisih %s Rp %s melebihi batas Rp %s. Kemungkinan besar harga satuan salah. Solusi: cocokkan harga satuan di faktur '
                    . 'supplier dengan PO / penerimaan barang, perbaiki harganya, lalu isi ulang %s sesuai Faktur Pajak.',
                    $label, number_format(abs($diff), 0, ',', '.'), number_format(self::MAX_DIFFERENCE, 0, ',', '.'), $label
                );
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // PPN: selisih ditempel ke baris terakhir agar Σ PPN baris (dasar jurnal PPN Masukan) = PPN Faktur Pajak
        if (abs($diffPpn) >= 0.005 && ($lastItem = $invoice->invoiceItem->sortBy('id')->last())) {
            $lastItem->forceFill(['tax_amount' => round((float) $lastItem->tax_amount + $diffPpn, 2)])->saveQuietly();
        }

        $otherFee = (float) $invoice->other_fee_total;
        $invoice->forceFill([
            'subtotal' => $fakturDpp,
            'dpp' => $fakturDpp,
            'price_variance_amount' => round((float) $invoice->price_variance_amount + $diffDpp, 2),
            'total' => round($fakturDpp + $fakturPpn + $otherFee, 2),
            'dpp_nilai_lain' => $invoice->dpp_nilai_lain !== null ? round($fakturDpp * 11 / 12, 0) : null,
            'tax_adjustment' => [
                'system_dpp' => $systemDpp, 'system_ppn' => $systemPpn,
                'faktur_dpp' => $fakturDpp, 'faktur_ppn' => $fakturPpn,
                'selisih_dpp' => $diffDpp, 'selisih_ppn' => $diffPpn,
                'by_user_id' => $keepAudit['by_user_id'] ?? $user?->getKey(),
                'by_name' => $keepAudit['by_name'] ?? $user?->name,
                'at' => $keepAudit['at'] ?? now()->toDateTimeString(),
            ],
        ])->saveQuietly();
        $invoice->unsetRelation('invoiceItem');
    }

    /** Kembalikan faktur ke angka sistem bila sebelumnya pernah disesuaikan (agar penyesuaian ulang tidak menumpuk). */
    private function revert(Invoice $invoice): void
    {
        $adj = $invoice->tax_adjustment;
        if (! is_array($adj) || $adj === []) {
            return;
        }

        $diffDpp = (float) ($adj['selisih_dpp'] ?? 0);
        $diffPpn = (float) ($adj['selisih_ppn'] ?? 0);
        if (abs($diffPpn) >= 0.005 && ($lastItem = $invoice->invoiceItem->sortBy('id')->last())) {
            $lastItem->forceFill(['tax_amount' => round((float) $lastItem->tax_amount - $diffPpn, 2)])->saveQuietly();
        }

        $systemDpp = (float) ($adj['system_dpp'] ?? $invoice->subtotal);
        $invoice->forceFill([
            'subtotal' => $systemDpp,
            'dpp' => $systemDpp,
            'price_variance_amount' => round((float) $invoice->price_variance_amount - $diffDpp, 2),
            'total' => round((float) $invoice->total - $diffDpp - $diffPpn, 2),
            'dpp_nilai_lain' => $invoice->dpp_nilai_lain !== null ? round($systemDpp * 11 / 12, 2) : null,
            'tax_adjustment' => null,
        ])->saveQuietly();
        $invoice->unsetRelation('invoiceItem');
        $invoice->load('invoiceItem');
    }
}
