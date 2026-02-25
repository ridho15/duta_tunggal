<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Filament\Resources\SupplierResource\RelationManagers\PurchaseOrderRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\ProductsRelationManager;
use App\Models\Cabang;
use App\Models\Supplier;
use App\Services\SupplierService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Supplier')
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
                            ->label('Kode Supplier')
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Kode supplier tidak boleh kosong',
                                'unique' => 'Kode supplier sudah digunakan !'
                            ])->required()
                            ->suffixAction(Action::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Supplier')
                                ->action(function ($set, $get, $state) {
                                    $supplierService = app(SupplierService::class);
                                    $set('code', $supplierService->generateCode());
                                })),
                        TextInput::make('perusahaan')
                            ->label('Nama Perusahaan Supplier')
                            ->string()
                            ->validationMessages([
                                'required' => 'Nama perusahaan supplier tidak boleh kosong',
                                'max' => 'Nama perusahaan supplier terlalu panjang'
                            ])
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('kontak_person')
                            ->label('Nama Contact Person')
                            ->string()
                            ->nullable(),
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->required()
                            ->validationMessages([
                                'required' => 'NPWP tidak boleh kosong',
                                'max' => 'NPWP terlalu panjang'
                            ])->maxLength(255),
                        TextInput::make('address')
                            ->required()
                            ->label('Alamat')
                            ->validationMessages([
                                'required' => 'Alamat tidak boleh kosong',
                                'max' => 'Alamat terlalu panjang'
                            ])
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->label('Telepon')
                            ->validationMessages([
                                'required' => 'Nomor Telepon tidak boleh kosong',
                                'regex' => 'Nomor Telepon tidak valid !',
                                'max' => 'Nomor telepon terlalu panjang'
                            ])
                            ->helperText('Contoh : 07512345678')
                            ->rules(['regex:/^0[2-9][0-9]{7,10}$/'])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('handphone')
                            ->tel()
                            ->label('Handphone')
                            ->validationMessages([
                                'required' => 'Nomor Handphone tidak boleh kosong',
                                'regex' => 'Nomor handphone tidak valid !',
                                'max' => 'Nomor handphone terlalu panjang'
                            ])
                            ->helperText('Contoh : 081234567890')
                            ->rules(['regex:/^08[1-9][0-9]{7,10}$/'])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->validationMessages([
                                'required' => 'Email tidak boleh kosong',
                                'email' => 'Email tidak valid !',
                                'max' => 'Email terlalu panjang'
                            ])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('fax')
                            ->label('Fax')
                            ->rules(['regex:/^0[2-9][0-9]{7,10}$/'])
                            ->required()
                            ->tel()
                            ->helperText('Contoh : 0213456789')
                            ->validationMessages([
                                'required' => 'Fax tidak boleh kosong',
                                'regex' => 'Fax tidak valid !'
                            ]),
                        TextInput::make('tempo_hutang')
                            ->label('Tempo Hutang')
                            ->suffix("Hari")
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->validationMessages([
                                'required' => 'Tempo Hutang tidak boleh kosong',
                                'numeric' => 'Tempo Hutang tidak valid !'
                            ]),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->string()
                            ->nullable()
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Supplier')
                    ->searchable(),
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
                TextColumn::make('perusahaan')
                    ->label('Nama Perusahaan Supplier')
                    ->searchable(),
                TextColumn::make('kontak_person')
                    ->label('Nama Contact Person')
                    ->searchable(),
                TextColumn::make('npwp')
                    ->label('NPWP')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable(),
                TextColumn::make('tempo_hutang')
                    ->label('Tempo Hutang')
                    ->suffix(" Hari")
                    ->searchable(),
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
                        ->color('success'),
                    DeleteAction::make(),
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
            PurchaseOrderRelationManager::class,
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
