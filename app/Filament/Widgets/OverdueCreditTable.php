<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Services\CreditValidationService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueCreditTable extends BaseWidget
{
    protected static ?string $heading = 'Customer dengan Kredit Jatuh Tempo';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->where('tipe_pembayaran', 'Kredit')
                    ->whereHas('invoices', function (Builder $query) {
                        $query->where('due_date', '<', now())
                            ->whereIn('status', ['sent', 'partially_paid']);
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('perusahaan')
                    ->label('Perusahaan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kredit_limit')
                    ->label('Kredit Limit')
                    ->money('idr'),
                Tables\Columns\TextColumn::make('current_usage')
                    ->label('Kredit Terpakai')
                    ->getStateUsing(function (Customer $record): float {
                        $creditService = app(CreditValidationService::class);
                        return $creditService->getCurrentCreditUsage($record);
                    })
                    ->money('idr'),
                Tables\Columns\TextColumn::make('overdue_count')
                    ->label('Jml Jatuh Tempo')
                    ->getStateUsing(function (Customer $record): int {
                        $creditService = app(CreditValidationService::class);
                        return $creditService->getOverdueInvoices($record)->count();
                    })
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('overdue_total')
                    ->label('Total Jatuh Tempo')
                    ->getStateUsing(function (Customer $record): float {
                        $creditService = app(CreditValidationService::class);
                        return $creditService->getOverdueInvoices($record)->sum('total');
                    })
                    ->money('idr')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('oldest_overdue')
                    ->label('Jatuh Tempo Tertua')
                    ->getStateUsing(function (Customer $record): string {
                        $creditService = app(CreditValidationService::class);
                        $overdueInvoices = $creditService->getOverdueInvoices($record);
                        if ($overdueInvoices->count() > 0) {
                            $oldest = $overdueInvoices->first();
                            $daysPastDue = now()->diffInDays($oldest->due_date);
                            return "{$daysPastDue} hari ({$oldest->invoice_number})";
                        }
                        return '-';
                    })
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Customer $record): string => route('filament.admin.resources.customers.view', $record)),
                Tables\Actions\Action::make('contact')
                    ->label('Kontak')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->action(function (Customer $record) {
                        $creditService = app(CreditValidationService::class);
                        $summary = $creditService->getCreditSummary($record);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Info Kontak Customer')
                            ->body("
                                <strong>{$record->name}</strong><br>
                                Telepon: {$record->telephone}<br>
                                HP: {$record->phone}<br>
                                Email: {$record->email}<br>
                                <hr>
                                Tagihan Jatuh Tempo: {$summary['overdue_count']}<br>
                                Total: Rp " . number_format($summary['overdue_total'], 0, ',', '.')
                            )
                            ->info()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->defaultSort('oldest_overdue', 'desc')
            ->poll('30s'); // Auto refresh every 30 seconds
    }
}