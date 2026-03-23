<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratJalanResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Models\Customer;
use App\Models\Cabang;

class SuratJalanResource extends Resource
{
    protected static ?string $model = SuratJalan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Delivery Order';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
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
                            ->relationship('deliveryOrder', 'do_number', function (Builder $query, $get) {
                                // J1: create => only approved DOs, edit => allow linked sent/received for compatibility.
                                $isCreatePage = str_ends_with((string) request()->path(), 'surat-jalans/create');
                                if ($isCreatePage) {
                                    $query->where('status', 'approved');
                                } else {
                                    $query->whereIn('status', ['approved', 'sent', 'received']);
                                }
                            })
                            ->multiple()
                            ->afterStateUpdated(function ($set, $state) {
                                $deliveryOrders = DeliveryOrder::whereIn('id', $state ?? [])->get();
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
                    ->label('Driver')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $drivers = $record->deliveryOrder->pluck('driver.name')->filter()->unique();
                        return $drivers->implode(', ') ?: '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vehicle_info')
                    ->label('Kendaraan')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $vehicles = $record->deliveryOrder->map(function ($deliveryOrder) {
                            if ($deliveryOrder->vehicle) {
                                return "{$deliveryOrder->vehicle->license_plate} ({$deliveryOrder->vehicle->vehicle_type})";
                            }
                            return null;
                        })->filter()->unique();
                        return $vehicles->implode(', ') ?: '-';
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
                IconColumn::make('status')
                    ->label('Terbit')
                    ->boolean(),
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
                    ->options([
                        '1' => 'Terbit'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->where('status', $data['value']);
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
                    EditAction::make()
                        ->modal()
                        ->color('success'),
                    DeleteAction::make(),
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
                        ->label('Download Surat')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status == 1;
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.surat-jalan', [
                                'suratJalan' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Surat_Jalan_' . $record->sj_number . '.pdf');
                        }),
                    Action::make('mark_as_sent')
                        ->label('Mark as Sent')
                        ->color('primary')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai DO sebagai Terkirim')
                        ->modalDescription('Semua Delivery Order dalam Surat Jalan ini akan ditandai sebagai Terkirim. Lakukan ini setelah barang benar-benar dikirim ke customer.')
                        ->modalSubmitActionLabel('Ya, Tandai Terkirim')
                        ->visible(function ($record) {
                            return $record->status >= 1; // J2: available for all published SJs
                        })
                        ->action(function ($record) {
                            $record->loadMissing('deliveryOrder');
                            $marked = 0;
                            foreach ($record->deliveryOrder as $do) {
                                if (in_array($do->status, ['approved', 'request_stock', 'partial'])) {
                                    try {
                                        app(\App\Services\DeliveryOrderService::class)->updateStatus(deliveryOrder: $do, status: 'sent');
                                        $marked++;
                                    } catch (\Throwable $e) {
                                        \Illuminate\Support\Facades\Log::error('SuratJalan: Failed to mark DO as sent', [
                                            'surat_jalan_id' => $record->id,
                                            'do_id' => $do->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                        HelperController::sendNotification(
                                            isSuccess: false,
                                            title: 'Gagal Update DO',
                                            message: 'DO #' . $do->do_number . ' gagal ditandai terkirim: ' . $e->getMessage()
                                        );
                                    }
                                }
                            }

                            HelperController::sendNotification(isSuccess: true, title: 'Terkirim', message: $marked > 0 ? "{$marked} Delivery Order berhasil ditandai sebagai Terkirim. Proses selanjutnya: Tim Finance perlu menerbitkan Invoice untuk setiap Delivery Order yang telah terkirim." : 'Semua DO sudah berstatus Terkirim. Proses selanjutnya: Tim Finance perlu menerbitkan Invoice untuk Delivery Order tersebut.');
                        }),
                ])
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Surat Jalan</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Surat Jalan adalah dokumen resmi pengiriman barang yang mengelompokkan beberapa Delivery Order dalam satu perjalanan.</li>' .
                            '<li><strong>Status:</strong> <em>Terbit</em> (otomatis saat dibuat). Surat Jalan langsung aktif dan siap digunakan untuk pengiriman.</li>' .
                            '<li><strong>Actions:</strong> <em>Edit</em> (draft only), <em>Delete</em> (draft only), <em>Download Document</em> (jika ada file), <em>Download Surat</em> (PDF terbit), <em>Tandai Terkirim</em> (konfirmasi pengiriman berhasil).</li>' .
                            '<li><strong>Grouping:</strong> Satu Surat Jalan dapat mencakup multiple Delivery Order untuk efisiensi pengiriman ke customer yang sama.</li>' .
                            '<li><strong>PDF:</strong> Download PDF Surat Jalan tersedia untuk keperluan pengiriman.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

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
        ];
    }
}
