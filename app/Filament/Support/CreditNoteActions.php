<?php

namespace App\Filament\Support;

use App\Filament\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Aksi Nota Kredit (T5.3, D10/D35–D40) — satu sumber untuk tabel dan halaman View (Filament\Tables\Actions dan Filament\Actions
 * berbeda kelas, jadi setiap metode menerima instance aksi). Semua tersembunyi bila flag credit_notes mati.
 */
class CreditNoteActions
{
    private const NON_CREDITABLE = ['draft', 'cancelled', 'canceled'];

    /** Invoice terbit, belum dibatalkan, dan pengguna berhak membuat Nota Kredit. */
    public static function canCreateFor(Invoice $invoice): bool
    {
        return CreditNoteService::enabled()
            && (bool) Auth::user()?->hasPermissionTo('create credit note')
            && ! in_array(strtolower((string) $invoice->status), self::NON_CREDITABLE, true);
    }

    /** "Buat Nota Kredit" dari Invoice: pilih jenis (koreksi/retur/pembatalan), kuantitas per baris, biaya kirim, alasan. */
    public static function create($action)
    {
        return $action
            ->label('Buat Nota Kredit')
            ->icon('heroicon-o-receipt-refund')
            ->color('warning')
            ->modalHeading('Buat Nota Kredit (Draf)')
            ->modalDescription('Nota Kredit dibuat sebagai DRAF dan baru berdampak pada jurnal dan piutang setelah diterbitkan oleh pihak yang berwenang.')
            ->modalSubmitActionLabel('Simpan Draf')
            ->visible(fn (Invoice $record): bool => self::canCreateFor($record))
            ->fillForm(fn (Invoice $record): array => [
                'type' => CreditNote::TYPE_CORRECTION,
                'credit_date' => now()->toDateString(),
                'lines' => self::lineDefaults($record),
            ])
            ->form(fn (Invoice $record): array => [
                Select::make('type')->label('Jenis')->options(CreditNote::TYPE_LABELS)->required()->live()
                    ->helperText('Pembatalan Invoice = seluruh sisa kuantitas + biaya pengiriman; Retur/Koreksi = sebagian sesuai kuantitas di bawah.'),
                DatePicker::make('credit_date')->label('Tanggal Nota Kredit')->required()->native(false),
                Repeater::make('lines')
                    ->label('Kuantitas yang dikreditkan')
                    ->visible(fn (Get $get): bool => $get('type') !== CreditNote::TYPE_CANCELLATION)
                    ->addable(false)->deletable(false)->reorderable(false)
                    ->schema([
                        Hidden::make('invoice_item_id'),
                        Placeholder::make('label')->label('Produk')->content(fn (Get $get): string => (string) $get('label')),
                        TextInput::make('quantity')->label('Qty')->numeric()->minValue(0)->default(0)
                            ->maxValue(fn (Get $get): float => (float) $get('remaining'))
                            ->helperText(fn (Get $get): string => 'Sisa yang dapat dikreditkan: '.rtrim(rtrim(number_format((float) $get('remaining'), 2, ',', '.'), '0'), ',')),
                        Hidden::make('remaining'),
                    ])
                    ->columns(3),
                Toggle::make('include_shipping')->label('Ikut mengkreditkan biaya pengiriman')
                    ->visible(fn (Get $get): bool => $get('type') !== CreditNote::TYPE_CANCELLATION && self::hasShipping($record)),
                Textarea::make('reason')->label('Alasan')->required()->minLength(10)->rows(3)
                    ->helperText('Wajib, minimal 10 karakter; tercatat pada dokumen dan audit.'),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    $quantities = [];
                    foreach ((array) ($data['lines'] ?? []) as $line) {
                        $quantities[(int) $line['invoice_item_id']] = (float) ($line['quantity'] ?? 0);
                    }
                    $creditNote = app(CreditNoteService::class)->draft(
                        $record,
                        (string) $data['type'],
                        $quantities,
                        (string) $data['reason'],
                        ['credit_date' => $data['credit_date'] ?? null, 'include_shipping' => (bool) ($data['include_shipping'] ?? false)],
                        Auth::user()
                    );
                } catch (ValidationException $e) {
                    self::failure('Nota Kredit Tidak Dapat Dibuat', $e);

                    return null;
                }

                Notification::make()->success()->title('Draf Nota Kredit Dibuat')->body("{$creditNote->credit_note_number} — terbitkan setelah ditinjau.")->send();

                return redirect(CreditNoteResource::getUrl('view', ['record' => $creditNote]));
            });
    }

    /** "Batalkan Invoice" — pintasan: Nota Kredit pembatalan PENUH (D39); invoice menjadi Dibatalkan saat Nota Kredit diterbitkan. */
    public static function cancelInvoice($action)
    {
        return $action
            ->label('Batalkan Invoice')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->modalHeading('Batalkan Invoice')
            ->modalDescription('Dibuat Nota Kredit pembatalan PENUH (seluruh sisa kuantitas, PPN, dan biaya pengiriman) sebagai draf. Invoice berstatus Dibatalkan setelah Nota Kredit diterbitkan; invoice tidak dihapus.')
            ->modalSubmitActionLabel('Buat Draf Pembatalan')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->form([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->minLength(10)->rows(3),
            ])
            ->visible(fn (Invoice $record): bool => self::canCreateFor($record))
            ->action(function (Invoice $record, array $data) {
                try {
                    $creditNote = app(CreditNoteService::class)->draft($record, CreditNote::TYPE_CANCELLATION, [], (string) $data['reason'], [], Auth::user());
                } catch (ValidationException $e) {
                    self::failure('Pembatalan Tidak Dapat Dibuat', $e);

                    return null;
                }

                Notification::make()->success()->title('Draf Pembatalan Dibuat')->body("{$creditNote->credit_note_number} — buka dan terbitkan untuk membatalkan invoice.")->send();

                return redirect(CreditNoteResource::getUrl('view', ['record' => $creditNote]));
            });
    }

    /** "Buat Nota Kredit dari Retur" (D38): item berkeputusan "Refund / Nota Kredit". */
    public static function fromReturn($action)
    {
        return $action
            ->label('Buat Nota Kredit')
            ->icon('heroicon-o-receipt-refund')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Buat Nota Kredit dari Retur')
            ->modalDescription('Dibuat draf Nota Kredit (tipe Retur) dari item berkeputusan "Refund / Nota Kredit". Stok dan HPP tidak dijurnal ulang di sini.')
            ->visible(fn (CustomerReturn $record): bool => CreditNoteService::enabled()
                && (bool) Auth::user()?->hasPermissionTo('create credit note')
                && in_array($record->status, [CustomerReturn::STATUS_APPROVED, CustomerReturn::STATUS_COMPLETED], true)
                && $record->customerReturnItems()->where('decision', CustomerReturnItem::DECISION_CREDIT)->whereNotNull('invoice_item_id')->exists()
                && ! CreditNote::query()->where('customer_return_id', $record->id)->exists())
            ->action(function (CustomerReturn $record) {
                try {
                    $creditNote = app(CreditNoteService::class)->draftFromReturn($record, Auth::user());
                } catch (ValidationException $e) {
                    self::failure('Nota Kredit Tidak Dapat Dibuat', $e);

                    return null;
                }

                Notification::make()->success()->title('Draf Nota Kredit Dibuat')->body("{$creditNote->credit_note_number} — terbitkan setelah ditinjau.")->send();

                return redirect(CreditNoteResource::getUrl('view', ['record' => $creditNote]));
            });
    }

    /** "Terbitkan": jurnal cermin + piutang/Deposit; nomor Nota Retur Pajak opsional; override beralasan bila menyetujui buatan sendiri. */
    public static function issue($action)
    {
        return $action
            ->label('Terbitkan')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->modalHeading('Terbitkan Nota Kredit')
            ->modalDescription('Jurnal cermin diposting, piutang dikurangi (kelebihan yang sudah dibayar menjadi Deposit Customer). Setelah terbit Nota Kredit FINAL; koreksi dengan Nota Kredit baru.')
            ->modalSubmitActionLabel('Ya, Terbitkan')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->form(fn (CreditNote $record): array => array_merge([
                TextInput::make('tax_document_number')->label('No. Nota Retur Pajak (opsional)')->maxLength(100)
                    ->helperText('Format bebas; dapat diisi atau diubah setelah terbit.'),
            ], ApprovalActions::overrideForm($record)))
            ->visible(fn (CreditNote $record): bool => (bool) Auth::user()?->can('issue', $record) && ApprovalActions::canApprove($record))
            ->action(function (CreditNote $record, array $data) {
                try {
                    app(CreditNoteService::class)->issue($record, Auth::user(), [
                        'tax_document_number' => $data['tax_document_number'] ?? null,
                        'override_reason' => $data['override_reason'] ?? null,
                    ]);
                } catch (ValidationException $e) {
                    self::failure('Nota Kredit Tidak Dapat Diterbitkan', $e);

                    return null;
                }

                Notification::make()->success()->title('Nota Kredit Diterbitkan')->body('Jurnal diposting dan piutang diperbarui.')->send();

                return null;
            });
    }

    /** "Isi No. Nota Retur Pajak" pada Nota Kredit yang sudah terbit (metadata saja). */
    public static function taxDocumentNumber($action)
    {
        return $action
            ->label(fn (CreditNote $record): string => filled($record->tax_document_number) ? 'Ubah No. Nota Retur Pajak' : 'Isi No. Nota Retur Pajak')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading('No. Nota Retur Pajak')
            ->modalSubmitActionLabel('Simpan')
            ->form([TextInput::make('tax_document_number')->label('No. Nota Retur Pajak')->maxLength(100)])
            ->fillForm(fn (CreditNote $record): array => ['tax_document_number' => $record->tax_document_number])
            ->visible(fn (CreditNote $record): bool => CreditNoteService::enabled() && $record->isIssued() && (bool) Auth::user()?->hasPermissionTo('approve credit note'))
            ->action(function (CreditNote $record, array $data): void {
                try {
                    app(CreditNoteService::class)->setTaxDocumentNumber($record, $data['tax_document_number'] ?? null, Auth::user());
                    Notification::make()->success()->title('No. Nota Retur Pajak Disimpan')->body('Jurnal dan piutang tidak berubah.')->send();
                } catch (ValidationException $e) {
                    self::failure('Tidak Dapat Disimpan', $e);
                }
            });
    }

    /** "Hapus Draf" — hanya draf; yang terbit final (D36). */
    public static function deleteDraft($action)
    {
        return $action
            ->label('Hapus Draf')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus Draf Nota Kredit')
            ->modalDescription('Hanya draf yang dapat dihapus. Kuantitas yang dikreditkan pada draf ini kembali tersedia.')
            ->visible(fn (CreditNote $record): bool => (bool) Auth::user()?->can('delete', $record))
            ->action(function (CreditNote $record) {
                try {
                    app(CreditNoteService::class)->deleteDraft($record);
                } catch (ValidationException $e) {
                    self::failure('Draf Tidak Dapat Dihapus', $e);

                    return null;
                }

                Notification::make()->success()->title('Draf Dihapus')->send();

                return redirect(CreditNoteResource::getUrl('index'));
            });
    }

    /** @return array<int, array{invoice_item_id: int, label: string, remaining: float, quantity: float}> */
    private static function lineDefaults(Invoice $invoice): array
    {
        $remaining = app(CreditNoteService::class)->creditableQuantities($invoice);
        $lines = [];
        foreach ($invoice->invoiceItem()->with('product')->get() as $item) {
            $left = (float) ($remaining[$item->id] ?? 0);
            if ($left <= 0) {
                continue;
            }
            $lines[] = ['invoice_item_id' => $item->id, 'label' => (string) ($item->product?->name ?? "Item #{$item->id}"), 'remaining' => $left, 'quantity' => 0];
        }

        return $lines;
    }

    private static function hasShipping(Invoice $invoice): bool
    {
        return collect((array) ($invoice->other_fee ?? []))->contains(fn ($fee) => (float) ($fee['amount'] ?? 0) > 0);
    }

    private static function failure(string $title, ValidationException $e): void
    {
        Notification::make()->danger()->title($title)->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send();
    }
}
