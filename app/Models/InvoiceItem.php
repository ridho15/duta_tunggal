<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'invoice_items';
    protected $fillable = [
        'invoice_id',
        'product_id',
        'quantity',
        'price',
        'discount',
        'gross_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'coa_id',
        'po_price',
        'cost_price',
        'cogs_amount',
        'cogs_source',
    ];

    public const COGS_SOURCE_JOURNAL = 'jurnal';
    public const COGS_SOURCE_STOCK = 'stok';
    public const COGS_SOURCE_ESTIMATE = 'estimasi';
    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'po_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_price' => 'decimal:4',
        'cogs_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    /** Toleransi (Rp) saat menebak apakah `price` invoice lama bersifat gross atau net. */
    private const BASIS_TOLERANCE = 1.0;

    /**
     * Rincian baris invoice PENJUALAN untuk ditampilkan (form, infolist, PDF) — satu sumber.
     *
     *   Harga Satuan × Qty = Jumlah · Diskon (% dan Rp) · DPP · PPN (% dan Rp) · Total
     *
     * `price` baku = harga satuan GROSS. Invoice yang dibuat sebelum Fase 5B belum punya gross_amount/discount_amount
     * dan `price`-nya bisa NET (jalur Delivery Order / form lama): basis ditebak dengan mencocokkan nilai bersih
     * baris, sama seperti `invoices:backfill-line-breakdown`. Nilai yang tersimpan tidak pernah diubah.
     *
     * @return array{unit_price: float, quantity: float, gross: float, discount_pct: float, discount_amount: float, dpp: float, tax_rate: float, ppn: float, total: float, basis: string}
     */
    public function breakdown(): array
    {
        $qty = (float) $this->quantity;
        $price = (float) $this->price;
        $discountPct = max(0.0, min(100.0, (float) $this->discount));
        $total = (float) $this->total;
        $dpp = (float) $this->subtotal;
        // Data lama (mis. seeder/impor) kadang tidak mengisi subtotal (DPP): turunkan dari total − PPN.
        if ($dpp <= 0 && $total > 0) {
            $dpp = round($total - (float) $this->tax_amount, 2);
        }
        $inclusive = \App\Services\TaxService::normalizeType($this->invoice->tipe_pajak ?? null) === 'Inklusif'
            && (float) $this->tax_rate > 0;

        // Nilai bersih baris setelah diskon: total (Inklusif) atau DPP (Eksklusif / Non Pajak)
        $net = $inclusive ? $total : $dpp;

        if ($this->gross_amount !== null) {
            $gross = (float) $this->gross_amount;
            $discountAmount = $this->discount_amount !== null ? (float) $this->discount_amount : max(0.0, round($gross - $net, 2));
            $basis = 'tersimpan';
        } else {
            $ifGross = $qty * $price * (1 - $discountPct / 100);
            $ifNet = $qty * $price;

            if (abs($net - $ifGross) <= self::BASIS_TOLERANCE) {
                $gross = round($qty * $price, 2);
                $basis = 'gross';
            } elseif ($discountPct > 0 && $discountPct < 100 && abs($net - $ifNet) <= self::BASIS_TOLERANCE) {
                // price sudah net (setelah diskon): kembalikan ke gross untuk tampilan
                $gross = round($net / (1 - $discountPct / 100), 2);
                $basis = 'net';
            } else {
                $gross = round($qty * $price, 2);
                $basis = 'tidak-cocok';
            }

            $discountAmount = max(0.0, round($gross - $net, 2));
        }

        return [
            'unit_price' => $qty > 0 ? round($gross / $qty, 4) : $price,
            'quantity' => $qty,
            'gross' => $gross,
            'discount_pct' => $discountPct,
            'discount_amount' => $discountAmount,
            'dpp' => $dpp,
            'tax_rate' => (float) $this->tax_rate,
            'ppn' => (float) $this->tax_amount,
            'total' => $total,
            'basis' => $basis,
        ];
    }

    /** Harga satuan setelah diskon (dipakai retur/kredit): tidak bergantung pada apakah `price` gross atau net. */
    public function getNetUnitPriceAttribute(): float
    {
        $breakdown = $this->breakdown();

        // Data lama yang nilainya tidak cocok dengan price × qty: jangan menebak, pakai price apa adanya (perilaku lama).
        if ($breakdown['basis'] === 'tidak-cocok') {
            return (float) $this->price;
        }

        return $breakdown['quantity'] > 0
            ? round(($breakdown['gross'] - $breakdown['discount_amount']) / $breakdown['quantity'], 4)
            : (float) $this->price;
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
}
