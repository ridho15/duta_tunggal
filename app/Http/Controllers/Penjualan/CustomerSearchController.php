<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Support\CustomerPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian customer (AJAX) untuk dropdown form penjualan: SO, penawaran, penerimaan, retur. */
class CustomerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($q) < CustomerPicker::MIN_QUERY, 422, 'Ketik minimal '.CustomerPicker::MIN_QUERY.' huruf untuk mencari customer.');

        return response()->json([
            'success' => true,
            'results' => CustomerPicker::search($q, $request->boolean('debtors_first')),
        ]);
    }
}
