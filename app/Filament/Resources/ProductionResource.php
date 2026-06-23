<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\Production;
use App\Services\ProductionService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\ActionsPosition;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-pointing-in';

    protected static ?string $navigationGroup = 'Manufaktur';

    protected static ?string $navigationLabel = 'Produksi';

    protected static ?string $modelLabel = 'Produksi';

    protected static ?string $pluralModelLabel = 'Produksi';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Production')
                    ->schema([
                        TextInput::make('production_number')
                            ->label('Production Number')
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->validationMessages([
                                'required' => 'Nomor produksi tidak boleh kosong',
                                'unique' => 'Nomor produksi sudah digunakan',
                                'max' => 'Nomor produksi maksimal 255 karakter'
                            ])
                            ->suffixAction(Action::make('generateProductionNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Production Number')
                                ->action(function ($set, $get, $state) {
                                    $productionService = app(ProductionService::class);
                                    $set('production_number', $productionService->generateProductionNumber());
                                }))
                            ->maxLength(255),
                        Select::make('manufacturing_order_id')
                            ->label('From Manufacture')
                            ->preload()
                            ->disabled()
                            ->searchable()
                            ->relationship('manufacturingOrder', 'mo_number')
                            ->required()
                            ->validationMessages([
                                'required' => 'Manufacturing Order harus dipilih'
                            ]),
                        DatePicker::make('production_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal produksi tidak boleh kosong'
                            ]),
                    ]),

                Fieldset::make('Informasi BOM dan Kebutuhan Bahan Produksi')
                    ->schema([
                        Placeholder::make('production_plan_info')
                            ->label('Rencana Produksi')
                            ->content(function ($record) {
                                return $record?->resolveProductionPlanLabel() ?? '-';
                            })
                            ->visible(fn ($record) => (bool) $record),
                        Placeholder::make('bom_info')
                            ->label('BOM')
                            ->content(function ($record) {
                                return $record?->resolveBillOfMaterialLabel() ?? '-';
                            })
                            ->visible(fn ($record) => (bool) $record),
                        Placeholder::make('material_requirement_summary')
                            ->label('Ringkasan Kebutuhan')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '-';
                                }

                                $summary = $record->getFulfillmentSummary();

                                return sprintf(
                                    'Total bahan %d | Stok bebas cukup %d | Stok bebas sebagian %d | Stok bebas tidak cukup %d | Sudah di-issue %d | Siap %s',
                                    $summary['total_materials'] ?? 0,
                                    $summary['fully_available'] ?? 0,
                                    $summary['partially_available'] ?? 0,
                                    $summary['not_available'] ?? 0,
                                    $summary['fully_issued'] ?? 0,
                                    ($summary['can_start_production'] ?? false) ? 'Ya' : 'Tidak'
                                );
                            })
                            ->visible(fn ($record) => (bool) $record),
                        Placeholder::make('material_requirement_table')
                            ->label('Daftar Kebutuhan Bahan')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '-';
                                }

                                return new HtmlString(
                                    view('filament.infolists.production-plan-material-requirements-table', [
                                        'getRecord' => fn () => $record,
                                    ])->render()
                                );
                            })
                            ->columnSpanFull()
                            ->visible(fn ($record) => (bool) $record),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('production_number')
                    ->label('Production Number')
                    ->searchable(),
                TextColumn::make('manufacturingOrder.mo_number')
                    ->label('Manufacture Number')
                    ->searchable(),
                TextColumn::make('manufacturingOrder.productionPlan.product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('manufacturingOrder.productionPlan.product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('manufacturingOrder.productionPlan.quantity')
                    ->label('Qty Plan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity_produced')
                    ->label('Qty Produced')
                    ->formatStateUsing(function ($state, $record) {
                        return $state ?? $record->manufacturingOrder?->productionPlan?->quantity ?? '-';
                    })
                    ->sortable(),
                TextColumn::make('manufacturingOrder.cabang.nama')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('production_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'finished' => 'success',
                            default => '-'
                        };
                    })->formatStateUsing(function ($state) {
                        return Str::upper($state);
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
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'finished' => 'Finished',
                    ]),
                SelectFilter::make('manufacturing_order_id')
                    ->relationship('manufacturingOrder.productionPlan.product', 'name')
                    ->label('Product')
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->sku}) {$record->name}";
                    }),
                Filter::make('production_date')
                    ->form([
                        DatePicker::make('production_date_from')
                            ->label('Production Date From'),
                        DatePicker::make('production_date_until')
                            ->label('Production Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['production_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('production_date', '>=', $date),
                            )
                            ->when(
                                $data['production_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('production_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    ActionsAction::make('finished')
                        ->label('Finished')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(function ($record) {
                            return $record->status == 'draft';
                        })
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $plannedQuantity = $record->manufacturingOrder?->productionPlan?->quantity;

                            $record->update([
                                'status' => 'finished',
                                'quantity_produced' => $record->quantity_produced ?? $plannedQuantity,
                            ]);

                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Production Finished. Quality Control manufacture dibuat otomatis dan Manufacturing Order akan diselesaikan setelah QC diproses.");
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Produksi (Production)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Production adalah record aktual dari proses produksi yang telah selesai, mencatat hasil produksi dari Manufacturing Order.</li>' .
                            '<li><strong>Komponen Utama:</strong> <em>Production Number</em> (nomor produksi unik), <em>Manufacturing Order</em> (referensi MO), <em>Product</em> (produk yang diproduksi), <em>Production Date</em> (tanggal produksi).</li>' .
                            '<li><strong>Status:</strong> <em>Draft</em> (belum selesai) atau <em>Finished</em> (sudah selesai). Status otomatis diubah saat production selesai.</li>' .
                            '<li><strong>Auto-Generation:</strong> Production record dibuat otomatis saat Manufacturing Order diselesaikan. Nomor produksi otomatis dibuat dengan format yang unik.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Manufacturing Order</em> (sumber produksi), <em>Production Plan</em> (rencana produksi), dan <em>Inventory</em> (penambahan stock produk jadi).</li>' .
                            '<li><strong>Actions:</strong> <em>Finish Production</em> (menandai produksi selesai), <em>View Details</em> (lihat detail produksi), <em>Delete</em> (hapus record produksi).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any production</em>, <em>create production</em>, <em>update production</em>, <em>delete production</em>, <em>restore production</em>, <em>force-delete production</em>.</li>' .
                            '<li><strong>Stock Impact:</strong> Saat production finished, stock produk jadi otomatis bertambah di inventory sesuai dengan quantity yang diproduksi.</li>' .
                            '<li><strong>Reporting:</strong> Menyediakan data untuk tracking produksi, cost analysis, dan performance monitoring manufacturing.</li>' .
                            '<li><strong>Workflow:</strong> Production Plan → Manufacturing Order → Production (hasil akhir) → Inventory Update.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('manufacturingOrder', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductions::route('/'),
            'view' => Pages\ViewProduction::route('/{record}'),
            'edit' => Pages\EditProduction::route('/{record}/edit'),
        ];
    }
}
