<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\Production;
use App\Services\ProductionService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\ActionsPosition;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-pointing-in';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?int $navigationSort = 4;

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
                            ->required(),
                        DatePicker::make('production_date')
                            ->required(),
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
                            $manufacturingOrder = $record->manufacturingOrder;
                            if ($manufacturingOrder) {
                                $manufacturingOrder->update([
                                    'status' => 'completed'
                                ]);
                            }
                            $record->update([
                                'status' => 'finished'
                            ]);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Production Finished");
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductions::route('/'),
            // 'create' => Pages\CreateProduction::route('/create'),
            // 'edit' => Pages\EditProduction::route('/{record}/edit'),
        ];
    }
}
