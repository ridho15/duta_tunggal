<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegacyTransactionArchiveResource\Pages;
use App\Models\LegacyTransactionArchive;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LegacyTransactionArchiveResource extends Resource
{
    private const TRANSACTION_TYPES = [
        'sale' => 'sale',
        'purchase' => 'purchase',
        'mutation' => 'mutation',
        'stock_adjustment' => 'stock_adjustment',
        'stockflow' => 'stockflow',
        'stock_modification' => 'stock_modification',
        'cashflow' => 'cashflow',
        'fund_mutation' => 'fund_mutation',
    ];

    private const TABLES = [
        'sales' => 'sales',
        'sales_detail' => 'sales_detail',
        'sales_payment' => 'sales_payment',
        'sales_cost' => 'sales_cost',
        'sales_delivery_info' => 'sales_delivery_info',
        'sales_inventory' => 'sales_inventory',
        'sales_photo' => 'sales_photo',
        'sales_retur_payment' => 'sales_retur_payment',
        'purchases' => 'purchases',
        'purchases_detail' => 'purchases_detail',
        'purchases_payment' => 'purchases_payment',
        'purchases_cost' => 'purchases_cost',
        'purchases_photo' => 'purchases_photo',
        'purchases_retur_payment' => 'purchases_retur_payment',
        'mutations' => 'mutations',
        'mutations_detail' => 'mutations_detail',
        'mutations_photo' => 'mutations_photo',
        'stock_adjustment' => 'stock_adjustment',
        'stockflows' => 'stockflows',
        'stock_modification' => 'stock_modification',
        'stock_modification_detail' => 'stock_modification_detail',
        'stock_opname' => 'stock_opname',
        'stock_opname_results' => 'stock_opname_results',
        'cashflows' => 'cashflows',
        'fund_mutations' => 'fund_mutations',
    ];

    private const ROW_KINDS = [
        'document' => 'document',
        'detail' => 'detail',
        'payment' => 'payment',
        'cost' => 'cost',
        'delivery_info' => 'delivery_info',
        'inventory_link' => 'inventory_link',
        'photo' => 'photo',
        'retur_payment' => 'retur_payment',
        'adjustment' => 'adjustment',
        'movement' => 'movement',
    ];

    protected static ?string $model = LegacyTransactionArchive::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy Migration';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Legacy Transaction Archive';

    protected static ?string $pluralLabel = 'Legacy Transaction Archives';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('document_date', 'desc')
            ->columns([
                TextColumn::make('document_date')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('source_name')
                    ->label('Source')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('transaction_type')
                    ->label('Tipe')
                    ->badge()
                    ->sortable(),
                TextColumn::make('table_name')
                    ->label('Table')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('row_kind')
                    ->label('Kind')
                    ->badge()
                    ->sortable(),
                TextColumn::make('document_number')
                    ->label('Document')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('party_name')
                    ->label('Party')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('party_code')
                    ->label('Party Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_code')
                    ->label('Product')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_name')
                    ->label('Location')
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_name')
                    ->label('Source')
                    ->options([
                        'inventory' => 'inventory',
                        'inventory_cab' => 'inventory_cab',
                    ]),
                SelectFilter::make('transaction_type')
                    ->label('Transaction Type')
                    ->options(self::TRANSACTION_TYPES),
                SelectFilter::make('table_name')
                    ->label('Table')
                    ->options(self::TABLES),
                SelectFilter::make('row_kind')
                    ->label('Row Kind')
                    ->options(self::ROW_KINDS),
                Filter::make('document_date')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('to')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('document_date', '>=', $date))
                            ->when($data['to'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('document_date', '<=', $date));
                    }),
                Filter::make('search_document')
                    ->form([
                        TextInput::make('needle')->label('Document / Party / Product'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $needle = trim((string) ($data['needle'] ?? ''));

                        if ($needle === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $builder) use ($needle) {
                            $builder
                                ->where('document_number', 'like', '%' . $needle . '%')
                                ->orWhere('reference_number', 'like', '%' . $needle . '%')
                                ->orWhere('party_name', 'like', '%' . $needle . '%')
                                ->orWhere('party_code', 'like', '%' . $needle . '%')
                                ->orWhere('product_code', 'like', '%' . $needle . '%');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('inspect')
                    ->label('Inspect')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (LegacyTransactionArchive $record) => 'Legacy Archive ' . ($record->document_number ?: $record->legacy_id))
                    ->form([
                        TextInput::make('source_name')->label('Source')->disabled(),
                        TextInput::make('transaction_type')->label('Transaction Type')->disabled(),
                        TextInput::make('table_name')->label('Table')->disabled(),
                        TextInput::make('row_kind')->label('Row Kind')->disabled(),
                        TextInput::make('document_number')->label('Document Number')->disabled(),
                        TextInput::make('reference_number')->label('Reference Number')->disabled(),
                        TextInput::make('party_name')->label('Party')->disabled(),
                        TextInput::make('product_code')->label('Product Code')->disabled(),
                        Textarea::make('notes')->label('Notes')->rows(3)->disabled(),
                        Textarea::make('payload_json')->label('Payload JSON')->rows(18)->disabled()->dehydrated(false),
                    ])
                    ->fillForm(fn (LegacyTransactionArchive $record) => [
                        'source_name' => $record->source_name,
                        'transaction_type' => $record->transaction_type,
                        'table_name' => $record->table_name,
                        'row_kind' => $record->row_kind,
                        'document_number' => $record->document_number,
                        'reference_number' => $record->reference_number,
                        'party_name' => $record->party_name,
                        'product_code' => $record->product_code,
                        'notes' => $record->notes,
                        'payload_json' => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegacyTransactionArchives::route('/'),
        ];
    }
}