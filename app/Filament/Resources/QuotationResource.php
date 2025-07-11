<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Filament\Resources\QuotationResource\RelationManagers\QuotationItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Penjualan';

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
                                'required' => 'Customer waji dipilih'
                            ])
                            ->relationship('customer', 'name')
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
                            ->prefix('Rp.')
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
                                        $set('unit_price', $product->sell_price);
                                        $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Unit price wajib diisi'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('total_price', HelperController::hitungSubtotal($get('quantity'), $state, $get('discount'), $get('tax')));
                                    })
                                    ->default(0)
                                    ->prefix('Rp.'),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('total_price', HelperController::hitungSubtotal($state, $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->reactive()
                                    ->default(0),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax')));
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
                                        $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state));
                                    })
                                    ->default(0)
                                    ->suffix('%'),
                                TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->numeric()
                                    ->reactive()
                                    ->required()
                                    ->default(0)
                                    ->prefix('Rp.'),
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
                    ->money('idr')
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
                TextColumn::make('status_payment')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'Belum Bayar' => 'gray',
                            'Sudah Bayar' => 'success'
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
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
