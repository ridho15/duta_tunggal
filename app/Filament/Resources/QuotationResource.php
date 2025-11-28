<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Filament\Resources\QuotationResource\RelationManagers\QuotationItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\InventoryStock;
use App\Services\CustomerService;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    // Keep resource label, but group renamed to include english hint
    protected static ?string $navigationGroup = 'Penjualan (Sales Order)';

    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quotation')
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }
                                return match ($record->status) {
                                    'draft' => 'Draft',
                                    'request_approve' => 'Request Approve',
                                    'approve' => 'Approved',
                                    'reject' => 'Rejected',
                                    default => '-'
                                };
                            }),
                        TextInput::make('quotation_number')
                            ->required()
                            ->label('Quotation Number')
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Quotation number tidak boleh kosong',
                                'unique' => 'Quotation number sudah digunakan'
                            ])
                            ->unique(ignoreRecord: true)
                            ->suffixAction(ActionsAction::make('generateQuotationNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Quotation Number')
                                ->action(function ($set, $get, $state) {
                                    $quotationService = app(QuotationService::class);
                                    $set('quotation_number', $quotationService->generateCode());
                                }))
                            ->maxLength(255),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Customer wajib dipilih'
                            ])
                            ->relationship('customer', 'name')
                            ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                return "({$customer->code}) {$customer->name}";
                            })
                            ->createOptionForm([
                                Fieldset::make('Form Customer')
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Kode Customer')
                                            ->required()
                                            ->reactive()
                                            ->suffixAction(ActionsAction::make('generateCode')
                                                ->icon('heroicon-m-arrow-path') // ikon reload
                                                ->tooltip('Generate Kode Customer')
                                                ->action(function ($set, $get, $state) {
                                                    $customerService = app(CustomerService::class);
                                                    $set('code', $customerService->generateCode());
                                                }))
                                            ->validationMessages([
                                                'unique' => 'Kode customer sudah digunakan',
                                                'required' => 'Kode customer tidak boleh kosong',
                                            ])
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('name')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Nama customer tidak boleh kosong',
                                            ])
                                            ->label('Nama Customer')
                                            ->maxLength(255),
                                        TextInput::make('perusahaan')
                                            ->label('Perusahaan')
                                            ->validationMessages([
                                                'required' => 'Perusahaan tidak boleh kosong',
                                            ])
                                            ->required(),
                                        TextInput::make('nik_npwp')
                                            ->label('NIK / NPWP')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'NIK / NPWP tidak boleh kosong',
                                                'numeric' => 'NIK / NPWP tidak valid !'
                                            ])
                                            ->numeric(),
                                        TextInput::make('address')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Alamat tidak boleh kosong',
                                            ])
                                            ->label('Alamat')
                                            ->maxLength(255),
                                        TextInput::make('telephone')
                                            ->label('Telepon')
                                            ->tel()
                                            ->validationMessages([
                                                'regex' => 'Telepon tidak valid !'
                                            ])
                                            ->placeholder('Contoh: 0211234567')
                                            ->regex('/^0[2-9][0-9]{1,3}[0-9]{5,8}$/')
                                            ->helperText('Hanya nomor telepon rumah/kantor, bukan nomor HP.')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label('Handphone')
                                            ->tel()
                                            ->validationMessages([
                                                'required' => 'Nomor handphone tidak boleh kosong',
                                                'regex' => 'Nomor handphone tidak valid !'
                                            ])
                                            ->maxLength(15)
                                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('fax')
                                            ->label('Fax')
                                            ->required(),
                                        TextInput::make('tempo_kredit')
                                            ->numeric()
                                            ->label('Tempo Kredit (Hari)')
                                            ->helperText('Hari')
                                            ->required()
                                            ->default(0),
                                        TextInput::make('kredit_limit')
                                            ->label('Kredit Limit (Rp.)')
                                            ->default(0)
                                            ->required()
                                            ->numeric()
                                            ->indonesianMoney(),
                                        Radio::make('tipe_pembayaran')
                                            ->label('Tipe Bayar Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'Bebas' => 'Bebas',
                                                'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                                'Kredit' => 'Kredit (Bayar Kredit)'
                                            ])->required(),
                                        Radio::make('tipe')
                                            ->label('Tipe Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'PKP' => 'PKP',
                                                'PRI' => 'PRI'
                                            ])
                                            ->required(),
                                        Checkbox::make('isSpecial')
                                            ->label('Spesial (Ya / Tidak)'),
                                        Textarea::make('keterangan')
                                            ->label('Keterangan')
                                            ->nullable(),
                                    ]),
                            ])
                            ->required(),
                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->validationMessages([
                                'required' => 'Tanggal wajib dipilih'
                            ])
                            ->required(),
                        DatePicker::make('valid_until'),
                        TextInput::make('total_amount')
                            ->numeric()
                            ->readOnly()
                            ->indonesianMoney()
                            ->default(0),
                        FileUpload::make('po_file_path')
                            ->label('File')
                            ->directory('quotation')
                            ->downloadable()
                            ->acceptedFileTypes([
                                'application/pdf',           // PDF
                                'application/msword',        // Word (.doc)
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word (.docx)
                                'image/*',                   // Semua jenis gambar (jpeg, png, webp, dll)
                            ])
                            ->maxSize('5120'),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                        Repeater::make('quotationItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->minItems(1)
                            ->mutateRelationshipDataBeforeCreateUsing(function(array $data){
                                return $data;
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Produk wajib dipilih'
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            // Set raw numeric price first
                                            $set('unit_price', (int) $product->sell_price);
                                            // Recalculate total using numeric price
                                            $numericUnit = HelperController::parseIndonesianMoney($get('unit_price'));

                                            $set('total_price', HelperController::hitungSubtotal(
                                                (int)$get('quantity'),
                                                $numericUnit,
                                                (int)$get('discount'),
                                                (int)$get('tax'),
                                                $get('tipe_pajak') ?? null
                                            ));

                                            // Set stock info for this product across warehouses
                                            try {
                                                Log::debug('QuotationResource: fetching stocks for product', ['product_id' => $product->id]);
                                                $stocks = InventoryStock::with('warehouse')
                                                    ->where('product_id', $product->id)
                                                    ->get();

                                                $info = $stocks->map(function ($s) {
                                                    $warehouse = $s->warehouse->name ?? '—';
                                                    // Use qty_available as the quantity that can be ordered for sales
                                                    $qty = (int) ($s->qty_available ?? ($s->qty_on_hand ?? 0));
                                                    return "{$warehouse}: {$qty}";
                                                })->filter()->all();

                                                $final = count($info) ? implode("\n", $info) : 'Tidak ada informasi stok';
                                                Log::debug('QuotationResource: stock_info resolved', ['product_id' => $product->id, 'stock_info' => $final]);
                                                $set('stock_info', $final);
                                            } catch (\Throwable $e) {
                                                Log::error('QuotationResource: error reading stock', ['error' => $e->getMessage()]);
                                                $set('stock_info', 'Error membaca data stok');
                                            }
                                        }
                                    })
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        // Return array of id => label to satisfy Filament Select expectations
                                        return Product::query()
                                            ->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function (Product $p) {
                                                $label = "({$p->sku}) {$p->name}";
                                                return [$p->id => $label];
                                            })->toArray();
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Unit price wajib diisi'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $numericUnit = HelperController::parseIndonesianMoney($state);
                                        $set('total_price', HelperController::hitungSubtotal(
                                            (int)$get('quantity'),
                                            $numericUnit,
                                            (int)$get('discount'),
                                            (int)$get('tax'),
                                            $get('tipe_pajak') ?? null
                                        ));
                                    })
                                    ->default(0)
                                    ->indonesianMoney(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $numericUnit = HelperController::parseIndonesianMoney($get('unit_price'));
                                        $set('total_price', HelperController::hitungSubtotal(
                                            (int)$state,
                                            $numericUnit,
                                            (int)$get('discount'),
                                            (int)$get('tax'),
                                            $get('tipe_pajak') ?? null
                                        ));
                                    })
                                    ->reactive()
                                    ->default(1),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $numericUnit = HelperController::parseIndonesianMoney($get('unit_price'));
                                        $set('total_price', HelperController::hitungSubtotal(
                                            (int)$get('quantity'),
                                            $numericUnit,
                                            (int)$state,
                                            (int)$get('tax'),
                                            $get('tipe_pajak') ?? null
                                        ));
                                    })
                                    ->reactive()
                                    ->maxValue(100)
                                    ->default(0)
                                    ->suffix('%'),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tax tidak boleh kosong'
                                    ])
                                    ->maxValue(100)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $numericUnit = HelperController::parseIndonesianMoney($get('unit_price'));
                                        $set('total_price', HelperController::hitungSubtotal(
                                            (int)$get('quantity'),
                                            $numericUnit,
                                            (int)$get('discount'),
                                            (int)$state,
                                            $get('tipe_pajak') ?? null
                                        ));
                                    })
                                    ->default(0)
                                    ->suffix('%'),
                                TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->reactive()
                                    ->required()
                                    ->default(0)
                                    ->indonesianMoney(),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->nullable(),
                                Textarea::make('stock_info')
                                    ->label('Stock per Warehouse')
                                    ->rows(3)
                                    ->disabled()
                                    ->default(function ($get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return 'Tidak ada informasi stok';
                                        }
                                        try {
                                            Log::debug('QuotationResource: default stock_info for product', ['product_id' => $productId]);
                                            $stocks = InventoryStock::with('warehouse')
                                                ->where('product_id', $productId)
                                                ->get();
                                            $info = $stocks->map(function ($s) {
                                                $warehouse = $s->warehouse->name ?? '—';
                                                // Use qty_available as the quantity that can be ordered for sales
                                                $qty = (int) ($s->qty_available ?? ($s->qty_on_hand ?? 0));
                                                return "{$warehouse}: {$qty}";
                                            })->filter()->all();

                                            return count($info) ? implode("\n", $info) : 'Tidak ada informasi stok';
                                        } catch (\Throwable $e) {
                                            Log::error('QuotationResource: error computing default stock_info', ['error' => $e->getMessage()]);
                                            return 'Error membaca data stok';
                                        }
                                    })
                                    ->columnSpan(3)
                                    ->helperText('Menampilkan stok tersedia per warehouse (read-only).'),

                            ])
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => Str::upper('draft'),
                            'request_approve' => Str::upper('request approve'),
                            'approve' => Str::upper('Approved'),
                            'reject' => Str::upper('Rejected'),
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'request_approve' => 'primary',
                            'approve' => 'success',
                            'reject' => 'danger',
                        };
                    }),
                
                TextColumn::make('po_file_path')
                    ->searchable(),

                TextColumn::make('quotationItem.product.name')
                    ->label('Product')
                    ->searchable()
                    ->badge(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('requestApproveBy.name')
                    ->label('Request Approve By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('rejectBy.name')
                    ->label('Reject')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reject_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('approveBy.name')
                    ->label('Approve By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
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
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('primary'),
                    DeleteAction::make(),
                    Action::make('pdf_quotation')
                        ->label('PDF Quotation')
                        ->color('danger')
                        ->icon('heroicon-o-document')
                        ->hidden(function ($record) {
                            return $record->status != 'approve';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.quotation', [
                                'quotation' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Quotation_' . $record->quotation_number . '.pdf');
                        }),
                    Action::make('download_file')
                        ->label('Download File')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-on-square')
                        ->openUrlInNewTab()
                        ->hidden(function ($record) {
                            return !$record->po_file_path;
                        })
                        ->url(function ($record) {
                            return asset('storage' . $record->po_file_path);
                        }),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->color('success')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request-approve quotation') && $record->status == 'draft';
                        })
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->requestApprove($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Mengajukan Approve Berhasil");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('reject quotation') && ($record->status == 'request_approve');
                        })
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->approve($record);

                            HelperController::sendNotification(isSuccess: true, title: "Success", message: "Berhasil melakukan approve quotation");
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('reject quotation') && ($record->status == 'request_approve');
                        })
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->reject($record);
                            HelperController::sendNotification(isSuccess: true, title: "Danger", message: "Quotation di reject");
                        }),
                    Action::make('sync_total_amount')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->updateTotalAmount($record);

                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                        })
                ])->button()
                    ->label('Action')
                    ->color('primary'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('sync_total_amounts')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $quotationService = app(QuotationService::class);
                            foreach ($records as $record) {
                                $quotationService->updateTotalAmount($record);
                            }
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil diupdate");
                        })
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Quotation Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('quotation_number'),
                        Infolists\Components\TextEntry::make('customer.name'),
                        Infolists\Components\TextEntry::make('date')
                            ->date(),
                        Infolists\Components\TextEntry::make('valid_until')
                            ->date(),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->money('IDR', 0),
                        Infolists\Components\TextEntry::make('notes'),
                    ]),
                Infolists\Components\Section::make('Quotation Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('quotationItem')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name'),
                                Infolists\Components\TextEntry::make('quantity'),
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('IDR', 0),
                                Infolists\Components\TextEntry::make('discount'),
                                Infolists\Components\TextEntry::make('tax'),
                                Infolists\Components\TextEntry::make('total_price')
                                    ->money('IDR', 0),
                                Infolists\Components\TextEntry::make('notes'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuotationItemRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
