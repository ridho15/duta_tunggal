<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;
}
