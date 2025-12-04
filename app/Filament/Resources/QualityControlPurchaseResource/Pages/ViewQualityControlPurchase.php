<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Http\Controllers\HelperController;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use App\Services\ReturnProductService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewQualityControlPurchase extends ViewRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('Complete')
                ->color('success')
                ->label('Complete')
                ->requiresConfirmation(function ($record) {
                    return [
                        'title' => 'Konfirmasi Complete QC',
                        'description' => "Passed: {$record->passed_quantity}, Rejected: {$record->rejected_quantity}. Apakah Anda yakin ingin menyelesaikan QC ini?",
                        'submitLabel' => 'Ya, Selesaikan QC',
                    ];
                })
                ->hidden(function ($record) {
                    // Sembunyikan action jika status sudah complete atau passed_quantity = 0
                    return $record->status == 1 || $record->passed_quantity == 0;
                })
                ->icon('heroicon-o-check-badge')
                ->form(function ($record) {
                    if ($record->rejected_quantity > 0) {
                        return [
                            TextInput::make('return_number')
                                ->label('Return Number')
                                ->required()
                                ->reactive()
                                ->suffixAction(ActionsAction::make('generateReturnNumber')
                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                    ->tooltip('Generate Return Number')
                                    ->action(function ($set, $get, $state) {
                                        $returnProductService = app(ReturnProductService::class);
                                        $set('return_number', $returnProductService->generateReturnNumber());
                                    }))
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            Select::make('warehouse_id')
                                ->label('Gudang')
                                ->preload()
                                ->reactive()
                                ->searchable()
                                ->options(function () {
                                    return Warehouse::select(['id', 'name'])->get()->pluck('name', 'id');
                                })
                                ->required(),
                            Select::make('rak_id')
                                ->label('Rak')
                                ->preload()
                                ->reactive()
                                ->searchable()
                                ->options(function ($get) {
                                    return Rak::where('warehouse_id', $get('warehouse_id'))->select(['id', 'name'])->get()->pluck('name', 'id');
                                })
                                ->nullable(),
                            Textarea::make('reason')
                                ->label('Reason')
                                ->nullable()
                                ->string()
                                ->default($record->reason_reject)
                        ];
                    }

                    return null;
                })
                ->action(function (array $data, $record) {
                    $qualityControlService = app(QualityControlService::class);
                    $qualityControlService->completeQualityControl($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Purchase Completed");
                    
                    // Only check PO completion for QC from PurchaseReceiptItem, not PurchaseOrderItem
                    if ($record->from_model_type === 'App\Models\PurchaseReceiptItem') {
                        $qualityControlService->checkPenerimaanBarang($record);
                    }
                })
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate product information fields
        $data['product_name'] = $this->record->product->name ?? '';
        $data['sku'] = $this->record->product->sku ?? '';
        $data['quantity_received'] = $this->record->fromModel->qty_accepted ?? 0;
        $data['uom'] = $this->record->product->uom->name ?? '';
        return $data;
    }
}