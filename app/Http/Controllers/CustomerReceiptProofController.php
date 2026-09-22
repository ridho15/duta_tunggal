<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceipt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bukti penerimaan customer disimpan di disk PRIVAT (`local`) — memuat data rekening — sehingga hanya
 * dapat dibuka lewat rute ini oleh pengguna yang berhak melihat penerimaannya.
 */
class CustomerReceiptProofController extends Controller
{
    public function show(int $receipt): Response
    {
        $receipt = CustomerReceipt::findOrFail($receipt);

        Gate::authorize('view', $receipt);

        abort_if(blank($receipt->proof_path) || ! Storage::disk('local')->exists($receipt->proof_path), 404, 'Bukti tidak ditemukan.');

        return Storage::disk('local')->response($receipt->proof_path);
    }
}
