<?php

namespace App\Filament\Support;

use App\Services\ApprovalControlService;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Bantuan aksi persetujuan (T3.1): override pembuat=penyetuju wajib beralasan; visibilitas mengikuti aturan persetujuan. */
class ApprovalActions
{
    /** Form alasan override — hanya muncul bila penyetuju menyetujui dokumen buatannya sendiri (Owner/Super Admin) dan aturan T3.1 hidup. */
    public static function overrideForm(Model $record): array
    {
        if (! app(ApprovalControlService::class)->requiresOverrideReason(Auth::user(), $record)) {
            return [];
        }

        return [
            Textarea::make('override_reason')
                ->label('Alasan override')
                ->helperText('Anda menyetujui dokumen buatan sendiri (pengecualian Owner/Super Admin). Alasan tercatat di audit persetujuan.')
                ->required()
                ->minLength(10)
                ->rows(3),
        ];
    }

    /**
     * Form alasan pengecualian limit kredit (T3.2): hanya muncul bila SO ini akan DITOLAK karena kredit dan pengguna adalah
     * Owner/Super Admin/Finance Manager (satu-satunya peran yang dapat mengecualikan).
     */
    public static function creditOverrideForm(Model $record): array
    {
        $user = Auth::user();
        if (! config('sales.controls.credit_policy', false) || ! $user?->hasRole(['Super Admin', 'Owner', 'Finance Manager'])) {
            return [];
        }

        $customer = $record->customer;
        if (! $customer || $customer->tipe_pembayaran !== 'Kredit') {
            return [];
        }

        $check = app(\App\Services\CreditValidationService::class)->canCustomerMakePurchase($customer, (float) $record->total_amount);
        if ($check['can_purchase']) {
            return [];
        }

        return [
            Textarea::make('credit_override_reason')
                ->label('Alasan pengecualian limit kredit')
                ->helperText(implode(' | ', $check['messages']))
                ->required()
                ->minLength(10)
                ->rows(3),
        ];
    }

    /** Gabungan form persetujuan SO: override pembuat=penyetuju + pengecualian limit kredit (masing-masing hanya bila berlaku). */
    public static function saleOrderForm(Model $record): array
    {
        return array_merge(self::overrideForm($record), self::creditOverrideForm($record));
    }

    /** Boleh menyetujui menurut aturan (dokumen Quotation/SO)? Flag mati → hanya pemeriksaan lama (izin). */
    public static function canApprove(Model $record): bool
    {
        if (! config('sales.controls.approval_rules', false)) {
            return true;
        }

        return app(ApprovalControlService::class)->canApprove(Auth::user(), $record)['allowed'];
    }
}
