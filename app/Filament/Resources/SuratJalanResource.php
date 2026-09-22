<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratJalanResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanDocumentBuilder;
use App\Services\SuratJalanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use App\Models\Cabang;

class SuratJalanResource extends Resource
{
    protected static ?string $model = SuratJalan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Surat Jalan')
                    ->schema([
                        TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->required()
                            ->reactive()
                            ->default(fn () => app(SuratJalanService::class)->generateCode())
                            ->suffixAction(ActionsAction::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode')
                                ->action(function ($set, $get, $state) {
                                    $suratJalanService = app(SuratJalanService::class);
                                    $set('sj_number', $suratJalanService->generateCode());
                                }))
                            ->validationMessages([
                                'required' => "Surat Jalan Number tidak boleh kosong",
                                'unique' => 'Surat Jalan number sudah digunakan'
                            ])
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DateTimePicker::make('issued_at')
                            ->label('Issue At')
                            ->validationMessages([
                                'required' => 'Tanggal Surat jalan harus dibuat'
                            ])
                            ->helperText('Tanggal surat jalan dibuat')
                            ->required(),
                        Select::make('deliveryOrder')
                            ->label('Delivery Order')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->relationship('deliveryOrder', 'do_number', function (Builder $query, $get, ?SuratJalan $record) {
                                // Buat: hanya DO approved yang belum tercantum di Surat Jalan lain yang masih berlaku.
                                // Ubah (Draft): DO yang sudah tertaut tetap boleh dipilih untuk kompatibilitas.
                                $isCreatePage = ! $record || ! $record->exists;
                                if ($isCreatePage) {
                                    $query->where('status', 'approved');
                                } else {
                                    $query->whereIn('status', ['approved', 'sent', 'received']);
                                }

                                $query->whereDoesntHave('suratJalan', function (Builder $q) use ($record) {
                                    $q->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES)
                                        ->when($record?->getKey(), fn (Builder $q, $id) => $q->where('surat_jalans.id', '!=', $id));
                                });
                            })
                            ->multiple()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $ids = is_array($state) ? $state : (empty($state) ? [] : [$state]);
                                $deliveryOrders = DeliveryOrder::whereIn('id', $ids)->get();
                                if ($deliveryOrders->isNotEmpty()) {
                                    $set('cabang_id', $deliveryOrders->first()->cabang_id);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Delivery Order harus dipilih'
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->searchable()
                            ->preload()
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->helperText('Diisi otomatis dari Delivery Order. Dapat diubah bila perlu.')
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih'
                            ]),
                        FileUpload::make('document_path')
                            ->label('Upload Document')
                            ->directory('surat-jalan-documents')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload dokumen surat jalan (PDF, JPG, PNG, max 5MB)')
                            ->validationMessages([
                                'acceptedFileTypes' => 'File harus berupa PDF, JPG, atau PNG',
                                'maxSize' => 'Ukuran file maksimal 5MB'
                            ]),
                        Hidden::make('status')
                            ->default(1), // J2: auto-terbit, tidak perlu approval
                        Hidden::make('created_by')
                            ->default(fn () => Auth::id())
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sj_number')
                    ->label('Surat Jalan Number')
                    ->searchable(),
                TextColumn::make('delivery_orders_count')
                    ->label('Jumlah DO')
                    ->getStateUsing(function (SuratJalan $record): int {
                        return $record->deliveryOrder->count();
                    })
                    ->badge()
                    ->color('primary'),
                TextColumn::make('deliveryOrder.do_number')
                    ->searchable()
                    ->label('Delivery Order')
                    ->formatStateUsing(function (SuratJalan $record): string {
                        return $record->deliveryOrder->pluck('do_number')->implode(', ');
                    })
                    ->limit(50)
                    ->tooltip(function (SuratJalan $record): string {
                        return $record->deliveryOrder->pluck('do_number')->implode(', ');
                    }),
                TextColumn::make('customers')
                    ->label('Customer')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $customers = collect();
                        foreach ($record->deliveryOrder as $deliveryOrder) {
                            foreach ($deliveryOrder->salesOrders as $salesOrder) {
                                if ($salesOrder->customer) {
                                    $customers->push("({$salesOrder->customer->code}) {$salesOrder->customer->name}");
                                }
                            }
                        }
                        return $customers->unique()->implode(', ');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('deliveryOrder.salesOrders.customer', function (Builder $query) use ($search) {
                            $query->where('perusahaan', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('driver_info')
                    ->label('Driver / Ekspedisi')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $schedule = $record->primaryDeliverySchedule();

                        return $schedule ? $schedule->senderName() : SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('vehicle_info')
                    ->label('Kendaraan')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $schedule = $record->primaryDeliverySchedule();

                        return $schedule ? $schedule->vehicleLabel() : SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->description('Tanggal Surat Jalan dibuat')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable(),
                TextColumn::make('signedBy.name')
                    ->label('Signed By')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => SuratJalan::statusLabel($state))
                    ->color(fn ($state) => SuratJalan::statusColor($state))
                    ->sortable(),
                TextColumn::make('cancel_reason')
                    ->label('Alasan Batal')
                    ->placeholder('-')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('document_path')
                    ->label('Document')
                    ->getStateUsing(function (SuratJalan $record): string {
                        return $record->document_path ? 'Ada' : 'Tidak Ada';
                    })
                    ->badge()
                    ->color(function (SuratJalan $record): string {
                        return $record->document_path ? 'success' : 'danger';
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->label('Filter Customer')
                    ->relationship('deliveryOrder.salesOrders.customer', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Customer $record): string {
                        return "({$record->code}) {$record->name}";
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->whereHas('deliveryOrder.salesOrders', function (Builder $query) use ($data) {
                            $query->where('customer_id', $data['value']);
                        });
                    }),
                    
                SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options(collect(SuratJalan::STATUS_LABELS)->mapWithKeys(fn ($label, $value) => [(string) $value => $label])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        // '0' (Draft) valid; empty('0') bernilai true sehingga jangan dipakai.
                        if (! isset($data['value']) || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('status', (int) $data['value']);
                    }),

                Filter::make('issued_date_range')
                    ->label('Filter Tanggal Terbit')
                    ->form([
                        DatePicker::make('issued_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('issued_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '<=', $date),
                            );
                    }),
                    
            ])
            ->headerActions([])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->modal()
                        ->color('success')
                        ->visible(fn (SuratJalan $record) => $record->isEditable()),
                    DeleteAction::make()
                        ->visible(fn (SuratJalan $record) => $record->isEditable()),
                    static::issueAction(),
                    static::uploadDocumentAction(),
                    Action::make('download_document')
                        ->label('Download Document')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->visible(function ($record) {
                            return !empty($record->document_path);
                        })
                        ->action(function ($record) {
                            return response()->download(storage_path('app/public/' . $record->document_path));
                        }),
                    Action::make('download_surat_jalan')
                        ->label('Preview / Download PDF')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->url(fn ($record) => route('pdf-stream', ['type' => 'surat-jalan', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                    static::cancelAction(),
                    static::reissueAction(),
                ])
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (DeleteBulkAction $action, $records) {
                            // Hanya Draft yang boleh dihapus; yang terbit dikoreksi lewat Batalkan.
                            [$deletable, $locked] = $records->partition(fn (SuratJalan $r) => $r->isEditable());
                            $deletable->each(fn (SuratJalan $r) => $r->delete());

                            if ($locked->isNotEmpty()) {
                                HelperController::sendNotification(
                                    isSuccess: false,
                                    title: $locked->count() . ' Surat Jalan dilewati',
                                    message: 'Surat Jalan yang sudah terbit/dibatalkan tidak dapat dihapus. Gunakan aksi Batalkan.'
                                );
                            }

                            $action->success();
                        }),
                ]),
            ])
            ->recordClasses(fn (SuratJalan $record) => match ($record->status) {
                SuratJalan::STATUS_ISSUED => 'bg-blue-100',
                SuratJalan::STATUS_CANCELLED => 'bg-red-100',
                default => 'bg-gray-100',
            })
            ->description(new \Illuminate\Support\HtmlString(
                '<style>
                    .fi-ta-header:has(.sj-legend){display:block!important;width:100%}
                    .fi-ta-description:has(.sj-legend){display:block!important;width:100%;margin-bottom:16px}
                    .sj-legend{width:100%;min-width:100%;max-width:none;box-sizing:border-box;display:block}
                    .sj-legend+.fi-ta-header,.fi-ta-description+.fi-ta-header{margin-top:16px!important}
                    .fi-ta-description .sj-legend{margin-bottom:0}
                </style>' .
                '<div class="sj-legend space-y-4 mb-4" style="width:100%;min-width:100%;max-width:none;box-sizing:border-box;margin-bottom:16px;">' .
                '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;color:#374151;">' .
                        '<span class="flex items-center gap-2" style="display:flex;align-items:center;gap:8px;">' .
                        '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#3b82f6;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                        '</svg>' .
                        'Panduan Surat Jalan' .
                        '</span>' .
                        '<span class="transition group-open:rotate-180">' .
                        '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                        '</span>' .
                    '</summary>' .
                    '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top:12px;font-size:14px;color:#4b5563;padding-left:28px;border-left:2px solid rgba(59,130,246,0.3);">' .
                    '<ul class="list-disc pl-0" style="list-style:none;padding-left:0;">' .
                    '<li><strong>Apa ini:</strong> Surat Jalan adalah dokumen resmi pengiriman barang yang mengelompokkan beberapa Delivery Order.</li>' .
                    '<li><strong>Status:</strong> <em>Terbit</em> saat dibuat. Surat Jalan yang terbit <strong>terkunci</strong> (tidak dapat diubah/dihapus); koreksi lewat <em>Batalkan</em> (alasan wajib) lalu <em>Terbitkan Ulang</em>. Hanya dokumen bertanda tangan yang dapat diunggah.</li>' .
                    '<li><strong>PDF:</strong> Download PDF tersedia untuk keperluan pengiriman.</li>' .
                    '</ul>' .
                    '</div>' .
                '</details>' .
                '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:8px;">' .
                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                    '</svg>' .
                    'Legenda Warna Status Baris Data' .
                    '</h4>' .
                    '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: #f9fafb; border: 1px solid #e5e7eb;">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid #9ca3af; background-color: #ffffff; box-shadow: 0 1px 3px rgba(156, 163, 175, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Abu (Draft)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Belum terbit</span>' .
                    '</div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Terbit)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">SJ sudah terbit</span>' .
                    '</div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Dibatalkan)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">SJ tidak berlaku</span>' .
                    '</div>' .
                    '</div>' .
                    '</div>' .
                '</div>' .
                '</div>'
            ));
    }

    /**
     * Aksi siklus hidup dipakai di tabel DAN halaman Lihat (satu definisi).
     * $page = true menghasilkan Filament\Actions\Action (header halaman), bila tidak Tables\Actions\Action.
     */
    protected static function makeAction(string $name, bool $page): \Filament\Actions\Action|Action
    {
        return $page ? \Filament\Actions\Action::make($name) : Action::make($name);
    }

    protected static function notifyValidation(string $title, ValidationException $e): void
    {
        HelperController::sendNotification(isSuccess: false, title: $title, message: collect($e->errors())->flatten()->implode(' '));
    }

    public static function issueAction(bool $page = false): \Filament\Actions\Action|Action
    {
        return static::makeAction('issue', $page)
            ->label('Terbitkan')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn ($record) => (bool) Auth::user()?->can('issue', $record))
            ->requiresConfirmation()
            ->modalHeading('Terbitkan Surat Jalan')
            ->modalDescription('Setelah terbit, Surat Jalan tidak dapat diubah atau dihapus (hanya dapat dibatalkan). Lanjutkan?')
            ->action(function ($record) {
                try {
                    app(SuratJalanService::class)->issue($record);
                } catch (ValidationException $e) {
                    static::notifyValidation('Surat Jalan Tidak Dapat Diterbitkan', $e);

                    return;
                }

                HelperController::sendNotification(isSuccess: true, title: 'Surat Jalan Terbit', message: "Surat Jalan {$record->sj_number} berhasil diterbitkan.");
            });
    }

    public static function cancelAction(bool $page = false): \Filament\Actions\Action|Action
    {
        return static::makeAction('cancel', $page)
            ->label('Batalkan')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn ($record) => (bool) Auth::user()?->can('cancel', $record))
            ->modalHeading('Batalkan Surat Jalan')
            ->modalDescription('Surat Jalan yang dibatalkan tidak berlaku lagi dan tidak dapat dipulihkan. Delivery Order-nya dapat dibuatkan Surat Jalan baru (Terbitkan Ulang).')
            ->modalSubmitActionLabel('Ya, Batalkan')
            ->form([
                Textarea::make('cancel_reason')
                    ->label('Alasan Pembatalan')
                    ->required()
                    ->minLength(SuratJalanService::MIN_CANCEL_REASON_LENGTH)
                    ->maxLength(500)
                    ->rows(3)
                    ->validationMessages([
                        'required' => 'Alasan pembatalan wajib diisi',
                        'min' => 'Alasan pembatalan minimal ' . SuratJalanService::MIN_CANCEL_REASON_LENGTH . ' karakter',
                    ]),
            ])
            ->action(function ($record, array $data) {
                try {
                    app(SuratJalanService::class)->cancel($record, (string) ($data['cancel_reason'] ?? ''));
                } catch (ValidationException $e) {
                    static::notifyValidation('Surat Jalan Tidak Dapat Dibatalkan', $e);

                    return;
                }

                HelperController::sendNotification(isSuccess: true, title: 'Surat Jalan Dibatalkan', message: "Surat Jalan {$record->sj_number} dibatalkan.");
            });
    }

    public static function reissueAction(bool $page = false): \Filament\Actions\Action|Action
    {
        return static::makeAction('reissue', $page)
            ->label('Terbitkan Ulang')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn ($record) => (bool) Auth::user()?->can('reissue', $record))
            ->requiresConfirmation()
            ->modalHeading('Terbitkan Ulang Surat Jalan')
            ->modalDescription(fn ($record) => "Membuat Surat Jalan baru (nomor baru) untuk Delivery Order yang sama dengan {$record->sj_number}. Delivery Order harus masih Approved dan belum tercantum di Surat Jalan lain.")
            ->action(function ($record) {
                try {
                    $new = app(SuratJalanService::class)->reissue($record);
                } catch (ValidationException $e) {
                    static::notifyValidation('Surat Jalan Tidak Dapat Diterbitkan Ulang', $e);

                    return;
                }

                HelperController::sendNotification(isSuccess: true, title: 'Surat Jalan Diterbitkan Ulang', message: "Surat Jalan baru {$new->sj_number} berhasil diterbitkan.");

                return redirect(static::getUrl('view', ['record' => $new]));
            });
    }

    /** Satu-satunya perubahan yang diizinkan pada Surat Jalan terbit: unggah dokumen bertanda tangan. */
    public static function uploadDocumentAction(bool $page = false): \Filament\Actions\Action|Action
    {
        return static::makeAction('upload_document', $page)
            ->label('Unggah Dokumen Bertanda Tangan')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->visible(fn ($record) => (bool) Auth::user()?->can('uploadDocument', $record))
            ->modalHeading('Unggah Bukti Serah-Terima')
            ->form([
                FileUpload::make('document_path')
                    ->label('Dokumen bertanda tangan')
                    ->directory('surat-jalan-documents')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->required()
                    ->helperText('PDF, JPG, atau PNG, maksimal 5MB. Mengganti dokumen sebelumnya.')
                    ->validationMessages([
                        'required' => 'Pilih berkas yang akan diunggah',
                        'acceptedFileTypes' => 'File harus berupa PDF, JPG, atau PNG',
                        'maxSize' => 'Ukuran file maksimal 5MB',
                    ]),
            ])
            ->action(function ($record, array $data) {
                $record->update(['document_path' => $data['document_path']]);

                HelperController::sendNotification(isSuccess: true, title: 'Dokumen Diunggah', message: "Dokumen Surat Jalan {$record->sj_number} tersimpan.");
            });
    }

    public static function getEloquentQuery(): Builder
    {
        // Muat relasi yang dipakai kolom tabel supaya daftar tidak N+1 (driver/kendaraan dari jadwal).
        $query = parent::getEloquentQuery()->with([
            'deliveryOrder.salesOrders.customer',
            'deliveryOrder.cabang',
            'deliverySchedules.driver',
            'deliverySchedules.vehicle',
            'cabang',
            'createdBy',
            'signedBy',
        ]);

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('deliveryOrder', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuratJalans::route('/'),
            'create' => Pages\CreateSuratJalan::route('/create'),
            'edit' => Pages\EditSuratJalan::route('/{record}/edit'),
            'view' => Pages\ViewSuratJalan::route('/{record}'),
        ];
    }
}
