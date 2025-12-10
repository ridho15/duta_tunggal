<?php

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\JournalEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewDeposit extends ViewRecord
{
    protected static string $resource = DepositResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Load journal entries with COA relationship
        $this->record->load(['journalEntry.coa']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->color('warning'),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => 'App\Models\Deposit',
                    'tableFilters[source_id][value]' => $this->record->id
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Deposit')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('deposit_number')
                                    ->label('Nomor Deposit'),
                                Infolists\Components\TextEntry::make('fromModel.name')
                                    ->label('From')
                                    ->formatStateUsing(function ($record) {
                                        $model = $record->fromModel;
                                        if ($model instanceof \App\Models\Supplier) {
                                            return "Supplier: ({$model->code}) {$model->name}";
                                        } elseif ($model instanceof \App\Models\Customer) {
                                            return "Customer: ({$model->code}) {$model->name}";
                                        }
                                        return 'Unknown';
                                    }),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Total Deposit')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('used_amount')
                                    ->label('Used Amount')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('coa.name')
                                    ->label('Deposit COA')
                                    ->formatStateUsing(function ($state, $record) {
                                        $coa = $record->coa;
                                        return $coa ? "({$coa->code}) {$coa->name}" : 'Not set';
                                    }),
                                Infolists\Components\TextEntry::make('paymentCoa.name')
                                    ->label('Payment COA')
                                    ->formatStateUsing(function ($state, $record) {
                                        $coa = $record->paymentCoa;
                                        return $coa ? "({$coa->code}) {$coa->name}" : 'Not set';
                                    }),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'closed' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        Infolists\Components\TextEntry::make('note')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function () {
                                // Redirect to JournalEntryResource with filter for this deposit
                                $sourceType = urlencode(\App\Models\Deposit::class);
                                $sourceId = $this->record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}";
                            })
                            ->openUrlInNewTab()
                            ->visible(function () {
                                return $this->record->journalEntry()->exists();
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('journalEntry')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('date')->date()->label('Date'),
                                Infolists\Components\TextEntry::make('coa.code')->label('COA'),
                                Infolists\Components\TextEntry::make('coa.name')->label('Account Name'),
                                Infolists\Components\TextEntry::make('debit')->money('IDR')->label('Debit')->color('success'),
                                Infolists\Components\TextEntry::make('credit')->money('IDR')->label('Credit')->color('danger'),
                                Infolists\Components\TextEntry::make('description')->label('Description'),
                                Infolists\Components\TextEntry::make('journal_type')->badge()->label('Type'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function () {
                        return $this->record->journalEntry()->exists();
                    })
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }
}
