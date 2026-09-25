<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AgeingScheduleResource;
use App\Filament\Resources\AssetDisposalResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetTransferResource;
use App\Filament\Resources\BankReconciliationResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\Reports\AgeingReportResource;
use App\Filament\Resources\Reports\BalanceSheetResource;
use App\Filament\Resources\Reports\CashFlowResource;
use App\Filament\Resources\Reports\HppResource;
use App\Filament\Resources\Reports\ProfitAndLossResource;
use App\Filament\Resources\VoucherRequestResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class AccountingHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.accounting-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Akuntansi';

    protected static ?string $navigationLabel = 'Akuntansi';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'accounting-hub';

    protected static function getHubClasses(): array
    {
        return [
            JournalEntryResource::class,
            BankReconciliationResource::class,
            AgeingScheduleResource::class,
            VoucherRequestResource::class,
            BalanceSheetResource::class,
            ProfitAndLossResource::class,
            CashFlowResource::class,
            HppResource::class,
            AgeingReportResource::class,
            AssetResource::class,
            AssetTransferResource::class,
            AssetDisposalResource::class,
        ];
    }
}