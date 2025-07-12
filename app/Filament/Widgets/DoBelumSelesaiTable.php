<?php

namespace App\Filament\Widgets;

use App\Models\DeliveryOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DoBelumSelesaiTable extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return DeliveryOrder::query();
            })
            ->columns([
                // ...
            ]);
    }
}
