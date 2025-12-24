<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\SalesRelationManager;
use App\Models\Cabang;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\CreditValidationService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    // Position Master Data as the 7th group
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Customer')
                    ->schema([
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->validationMessages([
                                'required' => 'Cabang harus dipilih',
                            ]),
                        TextInput::make('code')
                            ->label('Kode Customer')
                            ->required()
                            ->reactive()
                            ->suffixAction(Action::make('generateCode')
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
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Email tidak boleh kosong',
                                'email' => 'Format email tidak valid',
                                'max' => 'Email terlalu panjang'
                            ]),
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
                            ->default(0)
                            ->validationMessages([
                                'required' => 'Tempo kredit tidak boleh kosong',
                                'numeric' => 'Tempo kredit harus berupa angka'
                            ]),
                        TextInput::make('kredit_limit')
                            ->label('Kredit Limit (Rp.)')
                            ->default(0)
                            ->required()
                            ->indonesianMoney()
                            ->validationMessages([
                                'required' => 'Kredit limit tidak boleh kosong',
                                'numeric' => 'Kredit limit harus berupa angka'
                            ]),
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
                                'required' => 'Tipe pembayaran harus dipilih'
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
                                'required' => 'Tipe customer harus dipilih'
                            ]),
                        Checkbox::make('isSpecial')
                            ->label('Spesial (Ya / Tidak)'),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->nullable()
                            ->validationMessages([
                                'max' => 'Keterangan terlalu panjang'
                            ])
                            ->maxLength(1000),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->label('Kode Customer')
                    ->label('Code'),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable(),
                TextColumn::make('perusahaan')
                    ->label('Nama Perusahaan')
                    ->searchable(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable(),
                TextColumn::make('telephone')
                    ->label('Telepon')
                    ->searchable(),
                IconColumn::make('isSpecial')
                    ->label('Spesial')
                    ->boolean(),
                TextColumn::make('tempo_kredit')
                    ->label('Tempo Kredit')
                    ->formatStateUsing(function ($state) {
                        return "{$state} hari";
                    })
                    ->sortable(),
                TextColumn::make('tipe_pembayaran')
                    ->label('Tipe Bayar')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kredit' => 'warning',
                        'COD (Bayar Lunas)' => 'success',
                        'Bebas' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('kredit_limit')
                    ->label('Kredit Limit')
                    ->money('IDR')
                    ->sortable()
                    ->visible(fn () => true),
                TextColumn::make('current_credit_usage')
                    ->label('Kredit Terpakai')
                    ->getStateUsing(function (Customer $record): float {
                        if ($record->tipe_pembayaran !== 'Kredit') return 0;
                        $creditService = app(CreditValidationService::class);
                        return $creditService->getCurrentCreditUsage($record);
                    })
                    ->money('IDR')
                    ->color(function (Customer $record): string {
                        if ($record->tipe_pembayaran !== 'Kredit') return 'gray';
                        $creditService = app(CreditValidationService::class);
                        $percentage = $creditService->getCreditUsagePercentage($record);
                        return match (true) {
                            $percentage >= 90 => 'danger',
                            $percentage >= 80 => 'warning',
                            default => 'success',
                        };
                    }),
                TextColumn::make('credit_usage_percentage')
                    ->label('% Kredit')
                    ->getStateUsing(function (Customer $record): string {
                        if ($record->tipe_pembayaran !== 'Kredit') return '-';
                        $creditService = app(CreditValidationService::class);
                        return $creditService->getCreditUsagePercentage($record) . '%';
                    })
                    ->badge()
                    ->color(function (Customer $record): string {
                        if ($record->tipe_pembayaran !== 'Kredit') return 'gray';
                        $creditService = app(CreditValidationService::class);
                        $percentage = $creditService->getCreditUsagePercentage($record);
                        return match (true) {
                            $percentage >= 90 => 'danger',
                            $percentage >= 80 => 'warning',
                            default => 'success',
                        };
                    }),
                TextColumn::make('overdue_status')
                    ->label('Status Jatuh Tempo')
                    ->getStateUsing(function (Customer $record): string {
                        if ($record->tipe_pembayaran !== 'Kredit') return '-';
                        $creditService = app(CreditValidationService::class);
                        $overdueCount = $creditService->getOverdueInvoices($record)->count();
                        return $overdueCount > 0 ? "{$overdueCount} Tagihan" : 'Normal';
                    })
                    ->badge()
                    ->color(function (Customer $record): string {
                        if ($record->tipe_pembayaran !== 'Kredit') return 'gray';
                        $creditService = app(CreditValidationService::class);
                        $overdueCount = $creditService->getOverdueInvoices($record)->count();
                        return $overdueCount > 0 ? 'danger' : 'success';
                    }),
                TextColumn::make('nik_npwp')
                    ->label('NIK / NPWP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Handphone')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->kode}) {$record->nama}";
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('tipe_pembayaran')
                    ->label('Tipe Pembayaran')
                    ->options([
                        'Bebas' => 'Bebas',
                        'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                        'Kredit' => 'Kredit (Bayar Kredit)',
                    ])
                    ->searchable(),
                SelectFilter::make('tipe')
                    ->label('Tipe Customer')
                    ->options([
                        'PKP' => 'PKP',
                        'PRI' => 'PRI',
                    ])
                    ->searchable(),
                SelectFilter::make('isSpecial')
                    ->label('Spesial')
                    ->options([
                        '1' => 'Ya',
                        '0' => 'Tidak',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
                ])
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
            SalesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
