<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentRequestResource\Pages;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\Cabang;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PaymentRequestResource extends Resource
{
    protected static ?string $model = PaymentRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Payment Request';
    protected static ?string $modelLabel = 'Payment Request';
    protected static ?string $pluralModelLabel = 'Payment Requests';
    protected static ?string $navigationGroup = 'Finance - Pembayaran';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Payment Request')
                    ->schema([
                        Section::make('Informasi Dasar')
                            ->columns(2)
                            ->schema([
                                TextInput::make('request_number')
                                    ->label('Nomor PR')
                                    ->default(fn () => PaymentRequest::generateNumber())
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->required(),

                                Select::make('supplier_id')
                                    ->label('Vendor / Supplier')
                                    ->options(fn () => Supplier::all()->mapWithKeys(fn ($s) => [
                                        $s->id => "({$s->code}) {$s->perusahaan}"
                                    ]))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($set) => $set('selected_invoices', [])),

                                DatePicker::make('request_date')
                                    ->label('Tanggal Request')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('payment_date')
                                    ->label('Tanggal Pembayaran yang Diminta')
                                    ->required(),

                                Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->options(fn () => Cabang::all()->mapWithKeys(fn ($c) => [
                                        $c->id => "({$c->kode}) {$c->nama}"
                                    ]))
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => Auth::user()?->cabang_id)
                                    ->required(),

                                TextInput::make('total_amount')
                                    ->label('Total Pembayaran (Rp)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('Rp'),
                            ]),

                        Section::make('Pilih Invoice yang akan Dibayar')
                            ->schema([
                                CheckboxList::make('selected_invoices')
                                    ->label('')
                                    ->options(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) return [];

                                        return Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereHas('fromModel.supplier', fn ($q) => $q->where('id', $supplierId))
                                            ->whereIn('status', ['unpaid', 'draft', 'sent', 'overdue', 'partially_paid'])
                                            ->get()
                                            ->mapWithKeys(function ($invoice) {
                                                $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '-';
                                                $total = number_format($invoice->total, 0, ',', '.');
                                                $isOverdue = $invoice->due_date && Carbon::parse($invoice->due_date)->isPast();
                                                $label = "{$invoice->invoice_number} - Rp {$total} (Due: {$dueDate})";
                                                if ($isOverdue) $label .= ' ⚠ TERLAMBAT';
                                                return [$invoice->id => $label];
                                            });
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $state) {
                                        if (!$state || empty($state)) {
                                            $set('total_amount', 0);
                                            return;
                                        }
                                        $total = Invoice::whereIn('id', $state)->sum('total');
                                        $set('total_amount', $total);
                                    }),
                            ]),

                        Section::make('Catatan')
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Catatan Permintaan')
                                    ->rows(3)
                                    ->placeholder('Alasan atau keterangan tambahan...'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Nomor PR')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.perusahaan')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('request_date')
                    ->label('Tanggal Request')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('Tgl Pembayaran')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total (Rp)')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => PaymentRequest::STATUS_COLORS[$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => PaymentRequest::STATUS_LABELS[$state] ?? $state),

                TextColumn::make('requestedBy.name')
                    ->label('Diminta Oleh')
                    ->sortable(),

                TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->default('-'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PaymentRequest::STATUS_LABELS),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'perusahaan'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn ($record) => in_array($record->status, ['draft'])),

                    // Submit for Approval
                    Tables\Actions\Action::make('submit')
                        ->label('Ajukan Persetujuan')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'draft')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'pending_approval',
                                'requested_by' => Auth::id(),
                            ]);
                            Notification::make()->title('Payment Request diajukan untuk persetujuan')->success()->send();
                        }),

                    // Approve
                    Tables\Actions\Action::make('approve')
                        ->label('Setujui')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === 'pending_approval')
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('approval_notes')
                                ->label('Catatan Persetujuan')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'status' => 'approved',
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                                'approval_notes' => $data['approval_notes'] ?? null,
                            ]);
                            Notification::make()->title('Payment Request disetujui')->success()->send();
                        }),

                    // Reject
                    Tables\Actions\Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status === 'pending_approval')
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('approval_notes')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'status' => 'rejected',
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                                'approval_notes' => $data['approval_notes'],
                            ]);
                            Notification::make()->title('Payment Request ditolak')->warning()->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn ($record) => $record->status === 'draft'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPaymentRequests::route('/'),
            'create' => Pages\CreatePaymentRequest::route('/create'),
            'view'   => Pages\ViewPaymentRequest::route('/{record}'),
            'edit'   => Pages\EditPaymentRequest::route('/{record}/edit'),
        ];
    }
}
