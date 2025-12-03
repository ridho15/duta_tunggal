<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Filament\Resources\QuotationResource\RelationManagers\QuotationItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\Warehouse;
use App\Services\CustomerService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    // Keep resource label, but group renamed to include english hint
    protected static ?string $navigationGroup = 'Penjualan (Sales Order)';

    protected static ?int $navigationSort = 2;
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
                                            ->validationMessages([
                                                'required' => 'Email tidak boleh kosong',
                                                'email' => 'Format email tidak valid'
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('fax')
                                            ->label('Fax')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Fax tidak boleh kosong'
                                            ]),
                                        TextInput::make('tempo_kredit')
                                            ->numeric()
                                            ->label('Tempo Kredit (Hari)')
                                            ->helperText('Hari')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tempo kredit tidak boleh kosong',
                                                'numeric' => 'Tempo kredit harus berupa angka'
                                            ])
                                            ->default(0),
                                        TextInput::make('kredit_limit')
                                            ->label('Kredit Limit (Rp.)')
                                            ->default(0)
                                            ->required()
                                            ->numeric()
                                            ->validationMessages([
                                                'required' => 'Kredit limit tidak boleh kosong',
                                                'numeric' => 'Kredit limit harus berupa angka'
                                            ])
                                            ->indonesianMoney(),
                                        Radio::make('tipe_pembayaran')
                                            ->label('Tipe Bayar Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'Bebas' => 'Bebas',
                                                'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                                'Kredit' => 'Kredit (Bayar Kredit)'
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tipe pembayaran wajib dipilih'
                                            ]),
                                        Radio::make('tipe')
                                            ->label('Tipe Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'PKP' => 'PKP',
                                                'PRI' => 'PRI'
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tipe customer wajib dipilih'
                                            ]),
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
                            ->label('Notes'),
                        Repeater::make('quotationItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->minItems(1)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
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

                TextColumn::make('notes')
                    ->label('Notes')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),

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
                SelectFilter::make('customer')
                    ->label('Customer')
                    ->searchable()
                    ->preload()
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                        return "({$customer->code}) {$customer->name}";
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'request_approve' => 'Request Approve',
                        'approve' => 'Approved',
                        'reject' => 'Rejected',
                    ])
                    ->default(null),
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Tanggal Dari'),
                        DatePicker::make('date_until')
                            ->label('Tanggal Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'Tanggal dari ' . Carbon::parse($data['date_from'])->toFormattedDateString();
                        }

                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Tanggal sampai ' . Carbon::parse($data['date_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
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
                            return Auth::user()->hasPermissionTo('approve quotation') && ($record->status == 'request_approve');
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
                        }),
                    Action::make('create_sale_order')
                        ->label('Buat Sales Order')
                        ->icon('heroicon-o-plus')
                        ->color('success')
                        ->visible(function ($record) {
                            $user = Auth::user();
                            $hasPermission = $user && $user->hasPermissionTo('create sales order');
                            $isApproved = $record->status == 'approve';

                            Log::debug('QuotationResource: create_sale_order visibility check', [
                                'quotation_id' => $record->id,
                                'quotation_number' => $record->quotation_number,
                                'status' => $record->status,
                                'is_approved' => $isApproved,
                                'user_id' => $user ? $user->id : null,
                                'user_name' => $user ? $user->name : null,
                                'has_permission' => $hasPermission,
                                'visible' => $isApproved && $hasPermission
                            ]);

                            return $isApproved && $hasPermission;
                        })
                        ->form([
                            Section::make('Informasi Quotation')
                                ->schema([
                                    Placeholder::make('quotation_number')
                                        ->label('Nomor Quotation')
                                        ->content(fn($record) => $record->quotation_number),
                                    Placeholder::make('customer_name')
                                        ->label('Customer')
                                        ->content(fn($record) => $record->customer->name ?? '-'),
                                    Placeholder::make('total_amount')
                                        ->label('Total Amount')
                                        ->content(fn($record) => 'Rp ' . number_format($record->total_amount, 0, ',', '.')),
                                    Placeholder::make('item_count')
                                        ->label('Jumlah Item')
                                        ->content(fn($record) => $record->quotationItem->count() . ' item(s)'),
                                ])->columns(2),
                            Section::make('Sales Order Baru')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('so_number')
                                                ->label('Nomor Sales Order')
                                                ->default(fn() => app(SalesOrderService::class)->generateSoNumber())
                                                ->required()
                                                ->unique(table: 'sale_orders', column: 'so_number')
                                                ->validationMessages([
                                                    'required' => 'Nomor Sales Order wajib diisi',
                                                    'unique' => 'Nomor Sales Order sudah digunakan'
                                                ])
                                                ->suffixAction(
                                                    \Filament\Forms\Components\Actions\Action::make('generateSoNumber')
                                                        ->icon('heroicon-o-arrow-path')
                                                        ->tooltip('Generate Nomor Sales Order Baru')
                                                        ->action(function ($set) {
                                                            $set('so_number', app(SalesOrderService::class)->generateSoNumber());
                                                        })
                                                ),
                                            DatePicker::make('order_date')
                                                ->label('Tanggal Order')
                                                ->default(now())
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tanggal order wajib dipilih'
                                                ]),
                                            DatePicker::make('delivery_date')
                                                ->label('Tanggal Pengiriman')
                                                ->validationMessages([
                                                    'required' => 'Tanggal pengiriman wajib dipilih'
                                                ]),
                                            Select::make('tipe_pengiriman')
                                                ->label('Tipe Pengiriman')
                                                ->options([
                                                    'Ambil Sendiri' => 'Ambil Sendiri',
                                                    'Kirim Langsung' => 'Kirim Langsung'
                                                ])
                                                ->default('Kirim Langsung')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tipe pengiriman wajib dipilih'
                                                ]),
                                        ]),
                                    Repeater::make('saleOrderItems')
                                        ->label('Item Sales Order')
                                        ->schema([
                                            Hidden::make('product_id'),
                                            Placeholder::make('product_info')
                                                ->label('Produk')
                                                ->content(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    if ($quotationItem) {
                                                        return "({$quotationItem->product->sku}) {$quotationItem->product->name}";
                                                    }
                                                    return '-';
                                                })
                                                ->columnSpan(2),
                                            TextInput::make('quantity')
                                                ->label('Quantity')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->quantity : 0;
                                                })
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Quantity wajib diisi',
                                                    'numeric' => 'Quantity harus berupa angka'
                                                ])
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $state ?? 0;
                                                    $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                    $discount = $get('discount') ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                    $set('subtotal', $subtotal);
                                                }),
                                            TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->unit_price : 0;
                                                })
                                                ->required()
                                                ->indonesianMoney()
                                                ->validationMessages([
                                                    'required' => 'Unit Price wajib diisi',
                                                    'numeric' => 'Unit Price harus berupa angka'
                                                ])
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $get('quantity') ?? 0;
                                                    $unitPrice = HelperController::parseIndonesianMoney($state ?? 0);
                                                    $discount = $get('discount') ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                    $set('subtotal', $subtotal);
                                                }),
                                            Select::make('warehouse_id')
                                                ->label('Gudang')
                                                ->searchable()
                                                ->preload()
                                                ->options(function () {
                                                    return Warehouse::where('status', 1)->pluck('name', 'id')->map(function ($name, $id) {
                                                        $warehouse = Warehouse::find($id);
                                                        return "({$warehouse->kode}) {$name}";
                                                    });
                                                })
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Gudang wajib dipilih'
                                                ])
                                                ->default(function () {
                                                    return Warehouse::where('status', 1)->first()?->id;
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($set) {
                                                    $set('rak_id', null); // Reset rak when warehouse changes
                                                }),
                                            Select::make('rak_id')
                                                ->label('Rak')
                                                ->searchable(['code', 'name'])
                                                ->preload()
                                                ->options(function ($get) {
                                                    $warehouseId = $get('warehouse_id');
                                                    if ($warehouseId) {
                                                        return \App\Models\Rak::where('warehouse_id', $warehouseId)->pluck('name', 'id')->map(function ($name, $id) {
                                                            $rak = \App\Models\Rak::find($id);
                                                            return "({$rak->code}) {$name}";
                                                        });
                                                    }
                                                    return [];
                                                })
                                                ->nullable(),
                                            TextInput::make('discount')
                                                ->label('Discount (%)')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->discount : 0;
                                                })
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $get('quantity') ?? 0;
                                                    $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                    $discount = $state ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                    $set('subtotal', $subtotal);
                                                }),
                                            TextInput::make('tax')
                                                ->label('Tax (%)')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->tax : 0;
                                                })
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $get('quantity') ?? 0;
                                                    $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                    $discount = $get('discount') ?? 0;
                                                    $tax = $state ?? 0;
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                    $set('subtotal', $subtotal);
                                                }),
                                            TextInput::make('subtotal')
                                                ->label('Subtotal')
                                                ->numeric()
                                                ->readOnly()
                                                ->default(0),
                                        ])
                                        ->columns(3)
                                        ->defaultItems(function ($record) {
                                            return $record && $record->quotationItem ? $record->quotationItem->count() : 0;
                                        })
                                        ->minItems(1)
                                        ->validationMessages([
                                            'minItems' => 'Minimal harus ada 1 item sales order'
                                        ])
                                        ->default(function ($record) {
                                            if ($record && $record->quotationItem) {
                                                $items = [];
                                                foreach ($record->quotationItem as $quotationItem) {
                                                    $items[] = [
                                                        'product_id' => $quotationItem->product_id,
                                                        'quantity' => $quotationItem->quantity,
                                                        'unit_price' => $quotationItem->unit_price,
                                                        'discount' => $quotationItem->discount,
                                                        'tax' => $quotationItem->tax,
                                                        'warehouse_id' => null,
                                                        'rak_id' => null,
                                                        'subtotal' => $quotationItem->quantity * ($quotationItem->unit_price + $quotationItem->tax - $quotationItem->discount)
                                                    ];
                                                }
                                                return $items;
                                            }
                                            return [];
                                        })
                                        ->columnSpanFull(),
                                    Textarea::make('notes')
                                        ->label('Catatan')
                                        ->placeholder('Catatan tambahan untuk sales order (opsional)')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                        ])
                        ->action(function ($data, $record) {
                            $salesOrderService = app(SalesOrderService::class);

                            // Create sale order
                            $saleOrder = SaleOrder::create([
                                'customer_id' => $record->customer_id,
                                'quotation_id' => $record->id,
                                'so_number' => $data['so_number'],
                                'order_date' => $data['order_date'],
                                'delivery_date' => $data['delivery_date'],
                                'tipe_pengiriman' => $data['tipe_pengiriman'],
                                'status' => 'draft',
                                'total_amount' => $record->total_amount,
                                'created_by' => Auth::id(),
                                'reference_type' => 2, // Refer Quotation
                                'notes' => $data['notes'] ?? null,
                            ]);

                            // Create sale order items from form data
                            if (isset($data['saleOrderItems']) && is_array($data['saleOrderItems'])) {
                                foreach ($data['saleOrderItems'] as $item) {
                                    $saleOrder->saleOrderItem()->create([
                                        'product_id' => $item['product_id'],
                                        'quantity' => $item['quantity'],
                                        'unit_price' => HelperController::parseIndonesianMoney($item['unit_price']),
                                        'discount' => $item['discount'] ?? 0,
                                        'tax' => $item['tax'] ?? 0,
                                        'warehouse_id' => $item['warehouse_id'],
                                        'rak_id' => $item['rak_id'] ?? null,
                                    ]);
                                }
                            } else {
                                // Fallback to quotation items if repeater data is not available
                                foreach ($record->quotationItem as $quotationItem) {
                                    $saleOrder->saleOrderItem()->create([
                                        'product_id' => $quotationItem->product_id,
                                        'quantity' => $quotationItem->quantity,
                                        'unit_price' => $quotationItem->unit_price,
                                        'discount' => $quotationItem->discount,
                                        'tax' => $quotationItem->tax,
                                        'warehouse_id' => 1, // Default warehouse
                                        'rak_id' => null,
                                    ]);
                                }
                            }

                            // Update total amount
                            $salesOrderService->updateTotalAmount($saleOrder);

                            HelperController::sendNotification(isSuccess: true, title: "Success", message: "Sale Order {$data['so_number']} berhasil dibuat");

                            // Redirect to edit page
                            return redirect()->route('filament.admin.resources.sale-orders.edit', $saleOrder);
                        })
                        ->modalHeading('Buat Sales Order dari Quotation')
                        ->modalDescription('Buat sales order baru berdasarkan quotation ini. Periksa informasi dan isi nomor sales order.')
                        ->modalSubmitActionLabel('Buat Sales Order')
                        ->modalCancelActionLabel('Batal')
                        ->slideOver()
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
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Quotation</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Quotation adalah penawaran harga kepada customer yang perlu disetujui sebelum menjadi Sales Order.</li>' .
                            '<li><strong>Status Flow:</strong> Draft → Request Approve → Approved/Rejected. Hanya quotation approved yang bisa dijadikan Sales Order.</li>' .
                            '<li><strong>Validitas:</strong> Perhatikan tanggal <em>Valid Until</em> - quotation expired tidak bisa digunakan.</li>' .
                            '<li><strong>Actions:</strong> <em>Request Approve</em> (draft), <em>Approve/Reject</em> (request_approve), <em>Sync Total</em> (update amount), <em>Create Sale Order</em> (approved only).</li>' .
                            '<li><strong>PO File:</strong> Upload file Purchase Order customer sebagai referensi (opsional).</li>' .
                            '<li><strong>Integration:</strong> Quotation approved otomatis bisa dikonversi menjadi Sales Order dengan semua detail item.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));

        return $table;
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
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
