<?php

namespace App\Filament\Resources\ChartOfAccountResource\Pages;

use App\Filament\Resources\ChartOfAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;

class ViewChartOfAccount extends ViewRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    public $start_date;
    public $end_date;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Set default dates to current month
        $reqStart = request()->query('start');
        $reqEnd = request()->query('end');
        $this->start_date = $reqStart ? \Illuminate\Support\Carbon::parse($reqStart)->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d');
        $this->end_date = $reqEnd ? \Illuminate\Support\Carbon::parse($reqEnd)->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedStartDate()
    {
        // Handle start date change
        $this->dispatch('refresh-ledger');
    }

    public function updatedEndDate()
    {
        // Handle end date change
        $this->dispatch('refresh-ledger');
    }

    public function filterLedger()
    {
        // Handle form submission
        $this->dispatch('refresh-ledger');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Informasi Akun')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Kode'),
                        TextEntry::make('name')
                            ->label('Nama'),
                        TextEntry::make('type')
                            ->label('Tipe')
                            ->badge(),
                        TextEntry::make('normal_balance')
                            ->label('Sifat Normal')
                            ->formatStateUsing(fn ($state) => ucfirst($state)),
                        TextEntry::make('balance_formula')
                            ->label('Rumus Saldo'),
                        TextEntry::make('coaParent.code')
                            ->label('Induk Akun')
                            ->formatStateUsing(function ($state, $record) {
                                if ($record->coaParent) {
                                    return $record->coaParent->code . ' - ' . $record->coaParent->name;
                                }
                                return '-';
                            }),
                        TextEntry::make('opening_balance')
                            ->label('Saldo Awal')
                            ->money('IDR'),
                        TextEntry::make('debit')
                            ->label('Total Debit')
                            ->money('IDR'),
                        TextEntry::make('credit')
                            ->label('Total Kredit')
                            ->money('IDR'),
                        TextEntry::make('ending_balance')
                            ->label('Saldo Akhir')
                            ->money('IDR'),
                    ])
                    ->columns(2),
                
                InfolistSection::make('Buku Besar (General Ledger)')
                    ->schema([
                        ViewEntry::make('ledger')
                            ->view('filament.components.chart-of-account-ledger')
                            ->viewData([
                                'record' => $this->record,
                                'start_date' => $this->start_date,
                                'end_date' => $this->end_date,
                            ])
                    ])
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
