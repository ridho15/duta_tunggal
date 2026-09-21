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

    /** Boleh menyetujui menurut aturan (dokumen Quotation/SO)? Flag mati → hanya pemeriksaan lama (izin). */
    public static function canApprove(Model $record): bool
    {
        if (! config('sales.controls.approval_rules', false)) {
            return true;
        }

        return app(ApprovalControlService::class)->canApprove(Auth::user(), $record)['allowed'];
    }
}
