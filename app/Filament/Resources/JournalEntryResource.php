<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use App\Services\JournalEntryAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    // Show Journal Entries in sidebar and expose the Profit & Loss page under it
    protected static ?string $navigationLabel = 'Journal Entry';
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $modelLabel = 'Journal Entry';

    protected static ?string $pluralModelLabel = 'Journal Entries';

    protected static ?string $navigationGroup = 'Finance - Akuntansi';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Journal Entry Details')
                    ->schema([
                        Forms\Components\Select::make('coa_id')
                            ->label('Chart of Account')
                            ->relationship('coa', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('debit')
                            ->label('Debit')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->indonesianMoney(),

                        Forms\Components\TextInput::make('credit')
                            ->label('Credit')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->indonesianMoney(),

                        Forms\Components\TextInput::make('journal_type')
                            ->label('Journal Type')
                            ->maxLength(255),

                        Forms\Components\Select::make('cabang_id')
                            ->label('Branch')
                            ->relationship('cabang', 'nama')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('coa.code')
                    ->label('COA Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coa.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('journal_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'sales' => 'success',
                        'purchase' => 'warning',
                        'depreciation' => 'info',
                        'manual' => 'gray',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('journal_type')
                    ->label('Journal Type')
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'depreciation' => 'Depreciation',
                        'manual' => 'Manual',
                        'transfer' => 'Transfer',
                        'payment' => 'Payment',
                        'receipt' => 'Receipt',
                    ]),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Branch')
                    ->relationship('cabang', 'nama'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
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
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'grouped' => Pages\GroupedJournalEntries::route('/grouped'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
            'profit-and-loss' => \App\Filament\Resources\Reports\ProfitAndLossResource\Pages\ViewProfitAndLoss::route('/profit-and-loss'),
        ];
    }
}
