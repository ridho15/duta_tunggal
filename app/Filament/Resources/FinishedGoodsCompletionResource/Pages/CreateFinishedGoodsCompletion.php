<?php

namespace App\Filament\Resources\FinishedGoodsCompletionResource\Pages;

use App\Filament\Resources\FinishedGoodsCompletionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFinishedGoodsCompletion extends CreateRecord
{
    protected static string $resource = FinishedGoodsCompletionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Penyelesaian barang jadi berhasil dibuat';
    }
}
