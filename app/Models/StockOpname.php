<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class StockOpname extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;

    protected $table = 'stock_opnames';

    protected $fillable = [
        'opname_number',
        'opname_date',
        'warehouse_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'opname_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'fromModel', 'from_model_type', 'from_model_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source', 'source_type', 'source_id');
    }

    /**
     * Generate a unique opname number
     */
    public static function generateOpnameNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'OPN-' . $date . '-';

        // Find the latest opname number for today
        $latest = self::where('opname_number', 'like', $prefix . '%')
            ->orderBy('opname_number', 'desc')
            ->first();

        if ($latest) {
            // Extract the sequential number and increment
            $lastNumber = (int) substr($latest->opname_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format as 3-digit number with leading zeros
        $number = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // In testing environment, add microseconds to ensure uniqueness only for auto-generated numbers
        if (app()->environment('testing') && !str_contains($number, 'TEST')) {
            $number .= '-' . now()->format('Hisu');
        }

        return $number;
    }

    protected static function booted()
    {
        // Tabel stock_opnames tidak punya kolom cabang_id (CabangScope bawaan memicu galat SQL untuk user
        // yang dibatasi cabang). Batasi lewat cabang gudang opname — hasilnya sama dengan maksud CabangScope.
        static::addGlobalScope('cabang', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (! $user || ! $user->cabang_id) {
                return;
            }

            $manageType = $user->manage_type ?? [];
            if (is_array($manageType) && in_array('all', $manageType)) {
                return;
            }

            $builder->whereHas('warehouse', fn ($q) => $q->where('warehouses.cabang_id', $user->cabang_id));
        });

        // Sync journal entries when relevant data changes
        static::updated(function ($stockOpname) {
            // Only sync if stock opname is approved and has journal entries
            if ($stockOpname->status === 'approved' && $stockOpname->journalEntries()->exists()) {
                // Check if opname_number or opname_date changed
                if ($stockOpname->wasChanged(['opname_number', 'opname_date'])) {
                    $stockOpname->syncJournalEntries();
                }
            }
        });
    }

    /**
     * Create journal entries for inventory adjustments when stock opname is approved
     */
    public function createAdjustmentJournalEntries()
    {
        $this->loadMissing('items.product', 'warehouse');

        $this->journalEntries()->where('journal_type', 'stock_opname')->delete();

        $reference = 'ADJ-' . $this->opname_number;
        $date = $this->opname_date;
        $description = 'Penyesuaian inventory hasil stock opname ' . $this->opname_number;

        // Ko Handy 10 Nov 2026 (#4B): hasil stock opname berdampak ke COA Persediaan dan COA HPP produk (seperti Penyesuaian Stok).
        //   Lebih : Dr Persediaan / Cr HPP   ·   Kurang : Dr HPP / Cr Persediaan
        // Saldo bersih per akun: tiap item menambah +nilai ke akun persediaannya dan -nilai ke akun HPP-nya, sehingga tetap seimbang.
        $net = [];
        foreach ($this->items as $item) {
            $value = round((float) $item->difference_value, 2);
            if ($value == 0.0) {
                continue;
            }

            $productName = $item->product?->name ?? ('Produk #' . $item->product_id);
            $inventoryCoa = $item->product?->resolveInventoryCoaOrDefault();
            $cogsCoa = $item->product?->resolveCogsCoaOrDefault();
            // Akun harus ada DAN berjenis benar (Persediaan = Asset, HPP = Expense); akun yang berganti jenis tidak boleh dipakai diam-diam.
            $inventoryOk = $inventoryCoa && strtolower((string) $inventoryCoa->type) === 'asset';
            $cogsOk = $cogsCoa && strtolower((string) $cogsCoa->type) === 'expense';
            if (! $inventoryOk || ! $cogsOk) {
                throw ValidationException::withMessages([
                    'accounting' => 'Akun ' . (! $inventoryOk ? 'Persediaan (jenis Asset)' : 'HPP (jenis Expense)') . " untuk produk '{$productName}' tidak ditemukan atau jenisnya tidak sesuai. Atur akun tersebut di Master Produk atau Pengaturan Akuntansi, lalu setujui stock opname lagi.",
                ]);
            }

            $net[$inventoryCoa->id] = ($net[$inventoryCoa->id] ?? 0.0) + $value;
            $net[$cogsCoa->id] = ($net[$cogsCoa->id] ?? 0.0) - $value;
        }

        foreach ($net as $coaId => $amount) {
            $amount = round($amount, 2);
            if ($amount == 0.0) {
                continue;
            }

            JournalEntry::create([
                'coa_id' => $coaId,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => $amount > 0 ? $amount : 0,
                'credit' => $amount < 0 ? abs($amount) : 0,
                'journal_type' => 'stock_opname',
                'cabang_id' => $this->warehouse->cabang_id ?? null,
                'source_type' => self::class,
                'source_id' => $this->id,
            ]);
        }
    }

    /**
     * Sync journal entries when stock opname data changes
     */
    public function syncJournalEntries()
    {
        $journalEntries = $this->journalEntries()->where('journal_type', 'stock_opname')->get();

        if ($journalEntries->isEmpty()) {
            return;
        }

        $reference = 'ADJ-' . $this->opname_number;
        $description = 'Penyesuaian inventory hasil stock opname ' . $this->opname_number;

        foreach ($journalEntries as $entry) {
            $entry->update([
                'reference' => $reference,
                'description' => $description,
                'date' => $this->opname_date,
            ]);
        }
    }
}
