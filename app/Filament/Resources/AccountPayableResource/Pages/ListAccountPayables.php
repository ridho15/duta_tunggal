<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use App\Filament\Widgets\AccountPayableStatsWidget;
use App\Helpers\MoneyHelper;
use App\Support\AccountPayableQuery;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountPayables extends ListRecords
{
    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
        ];
    }

    public function getTitle(): string
    {
        $totalAmount = $this->getFilteredQuery()->sum('account_payables.remaining');

        return 'Account Payable - ' . MoneyHelper::rupiah($totalAmount);
    }

    protected function getFilteredQuery(): Builder
    {
        return AccountPayableQuery::applyTableFilters(
            AccountPayableResource::getEloquentQuery(),
            $this->tableFilters ?? []
        );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccountPayableStatsWidget::class,
        ];
    }
}
