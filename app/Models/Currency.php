<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'currencies';
    protected $fillable = [
        'name',
        'symbol',
        'code',
        'to_rupiah'
    ];

    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if (strtoupper($this->code) === 'IDR') {
            $blockers[] = 'merupakan mata uang dasar sistem (Indonesian Rupiah / IDR)';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'currency_id') && \App\Models\Invoice::where('currency_id', $this->id)->exists()) {
            $blockers[] = 'memiliki riwayat transaksi faktur (Invoice)';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sale_orders', 'currency_id') && \App\Models\SaleOrder::where('currency_id', $this->id)->exists()) {
            $blockers[] = 'memiliki riwayat transaksi pesanan penjualan (Sales Order)';
        }

        return $blockers;
    }
}
