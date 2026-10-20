<?php

namespace App\Http\Controllers\Hp;

use App\Filament\Pages\Hp\KirimanPage;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\Hp\PickingList;
use App\Support\HpWarehouse;
use App\Support\MenuAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PickingListController extends Controller
{
    public function __invoke(Request $request, PickingList $pickingList)
    {
        $user = $request->user();
        $warehouseId = (int) $request->query('warehouse');

        abort_unless(KirimanPage::canAccess(), 403);
        abort_unless(HpWarehouse::allows($user, $warehouseId) || MenuAccess::isSuper($user), 403);

        $warehouse = Warehouse::findOrFail($warehouseId);
        abort_unless($user->canAccessCabang($warehouse->cabang_id), 403);

        $ids = array_values(array_filter(array_map('intval', (array) $request->query('do', []))));
        $data = $pickingList->build($warehouseId, $ids);

        $pdf = Pdf::loadView('pdf.picking-list', $data + [
            'warehouse' => $warehouse,
            'printedBy' => $user->name,
            'printedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Picking_List_'.($warehouse->kode ?: $warehouse->id).'_'.now()->format('Ymd_Hi').'.pdf');
    }
}
