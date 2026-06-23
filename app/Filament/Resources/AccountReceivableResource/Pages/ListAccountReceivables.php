<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Widgets\AccountReceivableStatsWidget;
use App\Helpers\MoneyHelper;
use App\Support\AccountReceivableQuery;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountReceivables extends ListRecords
{
    protected static string $resource = AccountReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    // public function getTitle(): string
    // {
    //     $totalAmount = $this->getFilteredQuery()->sum('remaining');
    //     return 'Account Receivable - ' . MoneyHelper::rupiah($totalAmount);
    // }

    protected function getFilteredQuery(): Builder
    {
        return AccountReceivableQuery::filtered($this->tableFilters ?? []);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccountReceivableStatsWidget::class,
        ];
    }
}
