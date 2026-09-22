<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\CustomerService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /** T4.2 (X10): satu pintu pembuatan customer — dedup (flag customer_dedup) dan kode terpusat (flag central_numbering). */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CustomerService::class)->create($data);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Customer tidak dapat dibuat')->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send();

            $this->halt();
        }
    }
}
