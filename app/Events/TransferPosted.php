<?php

namespace App\Events;

use App\Models\CashBankTransfer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferPosted
{
    use Dispatchable, SerializesModels;

    public CashBankTransfer $transfer;

    public function __construct(CashBankTransfer $transfer)
    {
        $this->transfer = $transfer;
    }
}