<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\QuotationService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected $quotationService;
    public function boot()
    {
        $this->quotationService = new QuotationService();
    }
    protected function afterCreate()
    {
        $this->quotationService->updateTotalAmount($this->getRecord());
    }
}
