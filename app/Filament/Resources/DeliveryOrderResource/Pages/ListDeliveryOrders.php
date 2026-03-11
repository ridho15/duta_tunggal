<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryOrders extends ListRecords
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),

            Action::make('driver_recap_pdf')
                ->label('Rekap Driver')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->form([
                    Select::make('driver_id')
                        ->label('Driver')
                        ->options(Driver::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    DatePicker::make('delivery_date')
                        ->label('Tanggal Pengiriman')
                        ->required()
                        ->default(now()->toDateString()),
                ])
                ->action(function (array $data) {
                    $driver = Driver::findOrFail($data['driver_id']);
                    $deliveryOrders = DeliveryOrder::with([
                        'deliveryOrderItem.product',
                        'salesOrders.customer',
                    ])
                        ->where('driver_id', $driver->id)
                        ->whereDate('delivery_date', $data['delivery_date'])
                        ->orderBy('do_number')
                        ->get();

                    $date = \Carbon\Carbon::parse($data['delivery_date'])->format('d M Y');

                    $pdf = Pdf::loadView('pdf.driver-recap', [
                        'driver'         => $driver,
                        'deliveryOrders' => $deliveryOrders,
                        'date'           => $date,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'rekap-driver-' . \Illuminate\Support\Str::slug($driver->name) . '-' . $data['delivery_date'] . '.pdf';

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }
}
