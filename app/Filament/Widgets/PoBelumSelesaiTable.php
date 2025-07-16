<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;

class PoBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "PO Belum Selesai";
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return PurchaseOrder::query()
                    ->where('status', '!=', 'completed');
            })
            ->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return PurchaseOrderResource::getUrl('view', ['record' => $record]);
                    })
            ], position: ActionsPosition::BeforeColumns)
            ->columns([
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('supplier', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status PO')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        switch ($state) {
                            case 'draft':
                                return 'gray';
                                break;
                            case 'partially_received':
                                return 'warning';
                                break;
                            case 'request_close':
                                return 'warning';
                                break;
                            case 'request_approval':
                                return 'info';
                                break;
                            case 'closed':
                                return 'danger';
                                break;
                            case 'completed':
                                return 'success';
                                break;
                        }
                    })
                    ->badge(),
            ]);
    }
}
