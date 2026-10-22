<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\PosShift;
use App\Services\PosShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PosShiftController extends Controller
{
    protected PosShiftService $posShiftService;

    public function __construct(PosShiftService $posShiftService)
    {
        $this->posShiftService = $posShiftService;
    }

    /**
     * Buka shift kasir dengan modal awal.
     */
    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'starting_cash' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $shift = $this->posShiftService->openShift(
            $user,
            (float) $validated['starting_cash'],
            $validated['warehouse_id'] ?? null,
            $user->cabang_id,
            $validated['notes'] ?? null
        );

        return redirect()->route('kasir.pos.index')
            ->with('success', "Shift kasir {$shift->shift_number} berhasil dibuka dengan modal awal Rp " . number_format($shift->starting_cash, 0, ',', '.') . ".");
    }

    /**
     * Tutup shift kasir dan rekap fisik uang laci.
     */
    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_actual' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $shift = $this->posShiftService->getActiveShift($user, $user->cabang_id);

        if (!$shift) {
            return redirect()->route('kasir.pos.index')
                ->with('error', 'Tidak ada shift kasir yang sedang aktif.');
        }

        $closedShift = $this->posShiftService->closeShift(
            $shift,
            (float) $validated['cash_actual'],
            $validated['notes'] ?? null
        );

        return redirect()->route('kasir.shift.summary', $closedShift)
            ->with('success', "Shift kasir {$closedShift->shift_number} berhasil ditutup.");
    }

    /**
     * Tampilan Z-Report / Ringkasan penutupan shift kasir.
     */
    public function summary(PosShift $posShift): View
    {
        $posShift->loadMissing(['user', 'cabang', 'warehouse']);
        $summary = $this->posShiftService->getShiftSummary($posShift);

        return view('kasir.shift.summary', [
            'shift' => $posShift,
            'summary' => $summary,
        ]);
    }
}
