<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Http\Controllers\HelperController;
use App\Services\PurchaseReturnAutomationService;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseReturns extends ListRecords
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            Actions\Action::make('run_automation')
                ->label('Run Automation')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Run Purchase Return Automation')
                ->modalDescription('This will automatically create purchase returns for all pending quality control rejections. Do you want to continue?')
                ->action(function () {
                    $automationService = app(PurchaseReturnAutomationService::class);
                    $result = $automationService->automatePurchaseReturns(false);
                    
                    $message = "Processed: {$result['processed']} items, Created: {$result['created']} returns";
                    
                    if (!empty($result['errors'])) {
                        $message .= ". Errors: " . count($result['errors']);
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: "Automation Completed with Errors",
                            message: $message
                        );
                    } else {
                        HelperController::sendNotification(
                            isSuccess: true,
                            title: "Automation Completed Successfully",
                            message: $message
                        );
                    }
                }),
        ];
    }
}
