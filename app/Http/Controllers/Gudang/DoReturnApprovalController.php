<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\ReturnProduct;
use App\Services\ReturnProductService;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Persetujuan Retur by DO (DO…R) yang dicatat checker dari Siapkan & Kirim. Pengganti aksi "Approve" panel admin lama (dihapus 6 Nov 2026):
 * tanpa layar ini retur tertahan di draft dan DO asal tidak bisa diproses lagi (temuan UAT 13 Nov 2026).
 */
class DoReturnApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $tab = $request->query('tab') === 'riwayat' ? 'riwayat' : 'menunggu';
        $base = ReturnProduct::query()->where('from_model_type', DeliveryOrder::class);

        $returns = (clone $base)
            ->when($tab === 'menunggu', fn ($q) => $q->where('status', 'draft'), fn ($q) => $q->where('status', '!=', 'draft'))
            ->with(['fromModel.salesOrders.customer', 'warehouse', 'returnProductItem.product'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('gudang.retur-do.index', [
            'returns' => $returns,
            'tab' => $tab,
            'pendingCount' => (clone $base)->where('status', 'draft')->count(),
            'canApprove' => self::userMayApprove(Auth::user()),
        ]);
    }

    public function approve(ReturnProduct $returnProduct, ReturnProductService $service): RedirectResponse
    {
        $this->authorizeApprove();

        try {
            DB::transaction(function () use ($returnProduct, $service) {
                $locked = ReturnProduct::lockForUpdate()->findOrFail($returnProduct->getKey());
                $this->assertPendingDoReturn($locked);
                if ($locked->returnProductItem()->count() === 0) {
                    throw ValidationException::withMessages(['retur' => "Retur {$locked->return_number} tidak punya baris barang, jadi tidak ada yang bisa disetujui. Tolak retur ini lalu catat ulang dari Siapkan & Kirim."]);
                }
                $service->updateQuantityFromModel($locked);
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "Retur {$returnProduct->return_number} gagal disetujui: {$e->getMessage()}. Tidak ada perubahan stok yang tersimpan; coba lagi atau hubungi admin.");
        }

        return redirect()->route('gudang.retur-do.index')->with('success',
            "Retur {$returnProduct->return_number} disetujui. Barang kurang kembali ke stok gudang asal, barang rusak ke karantina. ".ReturnProductService::approvalSummaryText());
    }

    public function reject(ReturnProduct $returnProduct, Request $request): RedirectResponse
    {
        $this->authorizeApprove();

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:500']],
            ['reason.required' => 'Alasan penolakan wajib diisi.', 'reason.min' => 'Alasan penolakan minimal 5 karakter.']
        );

        try {
            DB::transaction(function () use ($returnProduct, $validated) {
                $locked = ReturnProduct::lockForUpdate()->findOrFail($returnProduct->getKey());
                $this->assertPendingDoReturn($locked);
                $locked->update([
                    'status' => 'rejected',
                    'reason' => mb_substr(trim($locked->reason."\n[Ditolak ".now()->format('d/m/Y H:i').' oleh '.(Auth::user()?->name ?? '-').'] '.$validated['reason']), 0, 65000),
                ]);
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('gudang.retur-do.index')->with('success',
            "Retur {$returnProduct->return_number} ditolak. Stok dan DO tidak berubah; DO bisa dicek ulang dan diretur lagi dari Siapkan & Kirim bila perlu.");
    }

    /** Kepala gudang / atasan: Super Admin & Owner, pemegang izin "approve return product", atau Inventory Manager. */
    public static function userMayApprove($user): bool
    {
        return $user && (MenuAccess::isSuper($user) || $user->can('approve return product') || $user->hasRole('Inventory Manager'));
    }

    private function assertPendingDoReturn(ReturnProduct $returnProduct): void
    {
        if ($returnProduct->from_model_type !== DeliveryOrder::class) {
            throw ValidationException::withMessages(['retur' => "{$returnProduct->return_number} bukan Retur by DO."]);
        }
        if ($returnProduct->status !== 'draft') {
            throw ValidationException::withMessages(['retur' => "Retur {$returnProduct->return_number} sudah diproses (status: {$returnProduct->status}). Muat ulang halaman."]);
        }
    }

    private function authorizeView(): void
    {
        $user = Auth::user();
        abort_unless($user && (self::userMayApprove($user) || in_array('GDG', MenuAccess::codesFor($user), true)), 403, 'Anda tidak memiliki akses ke Persetujuan Retur DO.');
    }

    private function authorizeApprove(): void
    {
        abort_unless(self::userMayApprove(Auth::user()), 403, 'Hanya kepala gudang / atasan (izin "approve return product") yang dapat menyetujui atau menolak Retur DO.');
    }
}
