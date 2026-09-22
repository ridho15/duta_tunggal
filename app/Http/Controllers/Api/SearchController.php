<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RemoteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** GET /api/v1/search/{type}?q= — pencarian ringan sisi-server (T7.2): maksimal 50 hasil + petunjuk bila terpotong. */
class SearchController extends Controller
{
    public function index(Request $request, string $type, RemoteSearch $search): JsonResponse
    {
        abort_unless(in_array($type, RemoteSearch::types(), true), 404, 'Jenis pencarian tidak dikenal.');
        Gate::authorize('viewAny', RemoteSearch::modelFor($type));

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        return response()->json($search->search($type, $data['q'] ?? null, array_filter([
            'cabang_id' => $data['cabang_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
        ])));
    }
}
