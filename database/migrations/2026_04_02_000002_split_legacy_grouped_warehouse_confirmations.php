<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyConfirmations = DB::table('warehouse_confirmations')
            ->select('warehouse_confirmations.*')
            ->join('warehouse_confirmation_items', function ($join) {
                $join->on('warehouse_confirmation_items.warehouse_confirmation_id', '=', 'warehouse_confirmations.id')
                    ->whereNull('warehouse_confirmation_items.deleted_at');
            })
            ->whereNull('warehouse_confirmations.deleted_at')
            ->whereIn('warehouse_confirmations.confirmation_type', ['delivery_order', 'material_issue'])
            ->groupBy('warehouse_confirmations.id')
            ->havingRaw('COUNT(warehouse_confirmation_items.id) > 1')
            ->orderBy('warehouse_confirmations.id')
            ->get();

        foreach ($legacyConfirmations as $confirmation) {
            $items = DB::table('warehouse_confirmation_items')
                ->where('warehouse_confirmation_id', $confirmation->id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            if ($items->count() <= 1) {
                continue;
            }

            $itemIdsToMove = $items->slice(1)->pluck('id');

            foreach ($itemIdsToMove as $itemId) {
                $newConfirmationId = DB::table('warehouse_confirmations')->insertGetId([
                    'confirmable_type' => $confirmation->confirmable_type,
                    'confirmable_id' => $confirmation->confirmable_id,
                    'confirmation_type' => $confirmation->confirmation_type,
                    'note' => $confirmation->note,
                    'rejection_reason' => $confirmation->rejection_reason,
                    'status' => $confirmation->status,
                    'confirmed_by' => $confirmation->confirmed_by,
                    'confirmed_at' => $confirmation->confirmed_at,
                    'created_at' => $confirmation->created_at,
                    'updated_at' => $confirmation->updated_at,
                    'deleted_at' => null,
                ]);

                DB::table('warehouse_confirmation_items')
                    ->where('id', $itemId)
                    ->update([
                        'warehouse_confirmation_id' => $newConfirmationId,
                        'updated_at' => $confirmation->updated_at,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};