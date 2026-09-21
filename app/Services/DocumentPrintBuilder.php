<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Cabang;
use App\Models\CreditNote;
use App\Models\CustomerReceipt;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Menyusun BINGKAI cetak dokumen penjualan (T6, D12/D41): kop perusahaan/cabang, judul, nomor, meta, pihak, syarat, tanda tangan,
 * watermark — dari SATU sumber, sehingga template Blade hanya menampilkan dan dapat diuji tanpa merender PDF.
 *
 * Aturan kop (D41): data Cabang → pengaturan global (app_settings: company_*) → data lama. Kolom yang kosong DIKOSONGKAN
 * (tidak pernah diisi alamat/telepon contoh) agar dokumen resmi tidak memuat data palsu.
 */
class DocumentPrintBuilder
{
    public const DEFAULT_COMPANY_NAME = 'PT DUTA TUNGGAL';

    public const DEFAULT_COMPANY_EMAIL = 'admin@dutatunggal.co.id';

    public const WATERMARK_DRAFT = 'DRAFT';

    public const WATERMARK_CANCELLED = 'DIBATALKAN';

    /**
     * @return array{name: string, branch: ?string, address: ?string, npwp: ?string, phone: ?string, email: ?string, banks: array<int, array{bank: string, number: string, holder: ?string, branch: ?string}>, lines: array<int, string>, logo: ?string}
     */
    public function company(?Cabang $cabang = null, bool $taxDocument = true): array
    {
        $cabang = $cabang && $cabang->exists ? $cabang : null;

        $name = self::clean($cabang?->nama_legal) ?? self::global('company_legal_name') ?? self::DEFAULT_COMPANY_NAME;
        $address = $taxDocument
            ? (self::clean($cabang?->alamat_pajak) ?? self::clean($cabang?->alamat) ?? self::global('company_address'))
            : (self::clean($cabang?->alamat) ?? self::clean($cabang?->alamat_pajak) ?? self::global('company_address'));
        $npwp = self::clean($cabang?->npwp) ?? self::global('company_npwp');
        $phone = self::clean($cabang?->telepon) ?? self::global('company_phone');
        $email = self::global('company_email') ?? self::DEFAULT_COMPANY_EMAIL;
        $banks = $this->banks($cabang?->rekening) ?: $this->banks(json_decode((string) self::global('company_bank_accounts'), true));

        $logo = public_path('logo_duta_tunggal.png');

        return [
            'name' => $name,
            'branch' => self::clean($cabang?->nama),
            'address' => $address,
            'npwp' => $npwp,
            'phone' => $phone,
            'email' => $email,
            'banks' => $banks,
            'lines' => array_values(array_filter([
                $address,
                $phone ? "Telp: {$phone}" : null,
                $email ? "Email: {$email}" : null,
                $npwp ? "NPWP: {$npwp}" : null,
            ])),
            'logo' => is_file($logo) ? $logo : null,
        ];
    }

    /** DRAFT / DIBATALKAN / null menurut status dokumen. */
    public function watermark(?string $status): ?string
    {
        return match (strtolower(trim((string) $status))) {
            'draft' => self::WATERMARK_DRAFT,
            'cancelled', 'canceled' => self::WATERMARK_CANCELLED,
            default => null,
        };
    }

    public function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['cabang']);
        $customer = $invoice->customer;
        $creditNotes = $this->issuedCreditNotes($invoice);
        $creditTotal = round((float) $creditNotes->sum('total'), 2);
        $tempo = $invoice->due_date && $invoice->invoice_date
            ? (int) Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($invoice->due_date), false)
            : null;

        return [
            'company' => $company = $this->company($invoice->cabang, true),
            'title' => 'INVOICE',
            'number' => (string) $invoice->invoice_number,
            'watermark' => $this->watermark($invoice->status),
            'status_label' => $this->invoiceStatus($invoice->status),
            'meta' => $this->meta([
                'No. Invoice' => $invoice->invoice_number,
                'No. Faktur Pajak' => $invoice->tax_invoice_number,
                'Tanggal' => $this->date($invoice->invoice_date),
                'Jatuh Tempo' => $this->date($invoice->due_date),
                'Status' => $this->invoiceStatus($invoice->status),
            ]),
            'party_title' => 'Pelanggan',
            'party' => $this->party($customer, $invoice->customer_name),
            'source' => $invoice->fromModel?->so_number ?? null,
            'credit_notes' => $creditNotes->map(fn (CreditNote $cn) => ['number' => $cn->credit_note_number, 'date' => $this->date($cn->credit_date), 'total' => (float) $cn->total])->all(),
            'credit_total' => $creditTotal,
            'terms' => array_values(array_filter([
                'Pembayaran paling lambat pada tanggal jatuh tempo'.($tempo !== null && $tempo > 0 ? " (tempo {$tempo} hari)" : '').'.',
                $company['banks'] !== [] ? 'Pembayaran melalui transfer ke rekening yang tertera di bawah ini.' : null,
                'Koreksi atas invoice ini dilakukan melalui Nota Kredit; keluhan atau retur barang hubungi kontak pada kop dokumen.',
            ])),
            'signatures' => [
                ['role' => 'Hormat kami', 'name' => $company['name'], 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Diterima oleh', 'name' => null, 'caption' => 'Nama jelas, tanggal, cap'],
            ],
            'printed_at' => $this->now(),
            'printed_by' => auth()->user()?->name,
        ];
    }

    public function deliveryOrder(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing(['cabang', 'salesOrders.customer', 'warehouse']);
        $customers = $deliveryOrder->salesOrders->map(fn ($so) => $so->customer?->name)->filter()->unique()->values();
        $addresses = $deliveryOrder->salesOrders->map(fn ($so) => self::clean($so->shipped_to))->filter()->unique()->values();

        return [
            'company' => $company = $this->company($deliveryOrder->cabang, false),
            'title' => 'DELIVERY ORDER',
            'number' => (string) $deliveryOrder->do_number,
            'watermark' => $this->watermark($deliveryOrder->status),
            'status_label' => \App\Support\StatusLabels::label('delivery_order', $deliveryOrder->status),
            'meta' => $this->meta([
                'No. Delivery Order' => $deliveryOrder->do_number,
                'Tanggal' => $this->date($deliveryOrder->delivery_date),
                'Customer' => $customers->implode(', '),
                'Alamat Pengiriman' => $addresses->implode(' | '),
                'Driver' => $deliveryOrder->driver?->name,
                'Kendaraan' => $deliveryOrder->vehicle ? trim(($deliveryOrder->vehicle->plate ?? '').' '.($deliveryOrder->vehicle->type ?? '')) : null,
                'Gudang' => $deliveryOrder->warehouse?->name,
                'Biaya Tambahan' => (float) $deliveryOrder->additional_cost > 0 ? \App\Support\LineAmounts::money((float) $deliveryOrder->additional_cost) : null,
                'Deskripsi Biaya Tambahan' => (float) $deliveryOrder->additional_cost > 0 ? $deliveryOrder->additional_cost_description : null,
                'Catatan' => $deliveryOrder->notes,
            ]),
            'signatures' => [
                ['role' => 'Dibuat / Gudang', 'name' => null, 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Pengirim', 'name' => $deliveryOrder->driver?->name, 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Penerima', 'name' => null, 'caption' => 'Nama jelas, tanggal, cap'],
            ],
            'printed_at' => $this->now(),
            'printed_by' => auth()->user()?->name,
        ];
    }

    public function receipt(CustomerReceipt $receipt): array
    {
        $receipt->loadMissing(['cabang', 'customer', 'customerReceiptItem.invoice', 'createdBy']);
        $cancelled = strtolower((string) $receipt->status) === 'cancelled';
        $invoices = $receipt->customerReceiptItem->map(fn ($item) => $item->invoice?->invoice_number)->filter()->unique()->values();
        $amount = (float) $receipt->total_payment;
        $number = 'KW-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT);

        return [
            'company' => $company = $this->company($receipt->cabang, true),
            'title' => 'KWITANSI',
            'number' => $number,
            'watermark' => $this->watermark($receipt->status),
            'status_label' => $cancelled ? 'Dibatalkan' : (string) $receipt->status,
            'meta' => $this->meta([
                'No. Kwitansi' => $number,
                'Tanggal' => $this->date($receipt->payment_date),
                'Telah terima dari' => $receipt->customer?->name,
                'Untuk pembayaran' => $invoices->isNotEmpty() ? 'Invoice '.$invoices->implode(', ') : self::clean($receipt->notes),
                'Metode' => self::clean($receipt->payment_method),
                'Bank' => self::clean($receipt->bank_name),
                'Referensi' => self::clean($receipt->payment_reference),
                'Keterangan' => $invoices->isNotEmpty() ? self::clean($receipt->notes) : null,
            ]),
            'amount' => $amount,
            'amount_words' => self::terbilang($amount),
            'signatures' => [
                ['role' => 'Penerima', 'name' => $receipt->createdBy?->name, 'caption' => $company['name']],
            ],
            'printed_at' => $this->now(),
            'printed_by' => auth()->user()?->name,
        ];
    }

    public function creditNote(CreditNote $creditNote): array
    {
        $creditNote->loadMissing(['invoice', 'customer', 'items.product', 'items.invoiceItem.product', 'createdBy', 'issuedBy']);
        $company = $this->company(Cabang::query()->find($creditNote->cabang_id), true);

        return [
            'company' => $company,
            'title' => 'NOTA KREDIT',
            'number' => (string) $creditNote->credit_note_number,
            'watermark' => $creditNote->isDraft() ? self::WATERMARK_DRAFT : null,
            'status_label' => CreditNote::STATUS_LABELS[$creditNote->status] ?? (string) $creditNote->status,
            'meta' => $this->meta([
                'No. Nota Kredit' => $creditNote->credit_note_number,
                'Tanggal' => $this->date($creditNote->credit_date),
                'Jenis' => CreditNote::TYPE_LABELS[$creditNote->type] ?? $creditNote->type,
                'Atas Invoice' => $creditNote->invoice?->invoice_number,
                'No. Nota Retur Pajak' => $creditNote->tax_document_number,
                'Status' => CreditNote::STATUS_LABELS[$creditNote->status] ?? (string) $creditNote->status,
            ]),
            'party_title' => 'Pelanggan',
            'party' => $this->party($creditNote->customer, null),
            'reason' => $creditNote->reason,
            'lines' => $creditNote->items->map(fn ($item) => [
                'description' => (string) ($item->description ?: ($item->product?->name ?: ($item->invoiceItem?->product?->name ?: '-'))),
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'tax_amount' => (float) $item->tax_amount,
                'total' => (float) $item->total,
            ])->all(),
            'totals' => [
                'subtotal' => (float) $creditNote->subtotal, 'tax' => (float) $creditNote->tax_amount,
                'other_fee' => (float) $creditNote->other_fee_amount, 'total' => (float) $creditNote->total,
                'to_receivable' => (float) $creditNote->applied_to_ar, 'to_deposit' => (float) $creditNote->applied_to_deposit,
            ],
            'signatures' => [
                ['role' => 'Dibuat oleh', 'name' => $creditNote->createdBy?->name, 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Disetujui / Diterbitkan', 'name' => $creditNote->isIssued() ? $creditNote->issuedBy?->name : null, 'caption' => $company['name']],
                ['role' => 'Diterima oleh', 'name' => null, 'caption' => 'Nama jelas, tanggal, cap'],
            ],
            'printed_at' => $this->now(),
            'printed_by' => auth()->user()?->name,
        ];
    }

    public function customerReturn(CustomerReturn $return): array
    {
        $return->loadMissing(['cabang', 'invoice', 'customer', 'receivedBy', 'approvedBy']);
        $cancelled = $return->status === CustomerReturn::STATUS_REJECTED;

        return [
            'company' => $company = $this->company($return->cabang, false),
            'title' => 'RETUR CUSTOMER',
            'number' => (string) $return->return_number,
            'watermark' => $cancelled ? self::WATERMARK_CANCELLED : null,
            'status_label' => CustomerReturn::STATUS_LABELS[$return->status] ?? (string) $return->status,
            'meta' => $this->meta([
                'No. Retur' => $return->return_number,
                'Tanggal' => $this->date($return->return_date),
                'Atas Invoice' => $return->invoice?->invoice_number,
                'Status' => CustomerReturn::STATUS_LABELS[$return->status] ?? (string) $return->status,
                'Alasan' => $return->reason,
            ]),
            'party_title' => 'Pelanggan',
            'party' => $this->party($return->customer, null),
            'signatures' => [
                ['role' => 'Dibuat oleh', 'name' => null, 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Diterima / QC', 'name' => $return->receivedBy?->name, 'caption' => 'Nama jelas / tanda tangan'],
                ['role' => 'Disetujui', 'name' => $return->approvedBy?->name, 'caption' => $company['name']],
            ],
            'printed_at' => $this->now(),
            'printed_by' => auth()->user()?->name,
        ];
    }

    /** Terbilang rupiah ("Satu juta dua ratus ribu rupiah") untuk kwitansi. */
    public static function terbilang(float $amount): string
    {
        $amount = round($amount, 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);
        $words = $whole === 0 ? 'nol' : trim(self::say($whole));
        $text = ucfirst($words).' rupiah';
        if ($cents > 0) {
            $text .= ' '.trim(self::say($cents)).' sen';
        }

        return preg_replace('/\s+/', ' ', $text);
    }

    // ------------------------------------------------------------------ internal

    /** Label status invoice; `unpaid` (belum ada di STATUS_LABELS) = Belum Dibayar. */
    private function invoiceStatus(?string $status): string
    {
        return Invoice::STATUS_LABELS[$status] ?? (['unpaid' => 'Belum Dibayar', 'canceled' => 'Dibatalkan'][$status] ?? ucfirst(str_replace('_', ' ', (string) $status)));
    }

    /** @return array<int, array{label: string, value: string}> baris meta; nilai kosong dilewati. */
    private function meta(array $rows): array
    {
        $out = [];
        foreach ($rows as $label => $value) {
            $value = self::clean($value === null ? null : (string) $value);
            if ($value !== null) {
                $out[] = ['label' => (string) $label, 'value' => $value];
            }
        }

        return $out;
    }

    /** @return array{name: string, company: ?string, address: ?string, phone: ?string, email: ?string, npwp: ?string} */
    private function party(?object $customer, ?string $fallbackName): array
    {
        return [
            'name' => self::clean($customer?->name) ?? self::clean($fallbackName) ?? '-',
            'company' => self::clean($customer?->perusahaan ?? null),
            'address' => self::clean($customer?->address ?? null),
            'phone' => self::clean($customer?->phone ?? null) ?? self::clean($customer?->telephone ?? null),
            'email' => self::clean($customer?->email ?? null),
            'npwp' => self::clean($customer?->nik_npwp ?? null),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, CreditNote> */
    private function issuedCreditNotes(Invoice $invoice)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('credit_notes')) {
            return collect();
        }

        return CreditNote::query()->where('invoice_id', $invoice->id)->where('status', CreditNote::STATUS_ISSUED)->orderBy('id')->get();
    }

    /** @return array<int, array{bank: string, number: string, holder: ?string, branch: ?string}> */
    private function banks(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $banks = [];
        foreach ($raw as $row) {
            $bank = self::clean($row['bank'] ?? null);
            $number = self::clean($row['number'] ?? null);
            if ($bank && $number) {
                $banks[] = ['bank' => $bank, 'number' => $number, 'holder' => self::clean($row['holder'] ?? null), 'branch' => self::clean($row['branch'] ?? null)];
            }
        }

        return $banks;
    }

    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->locale('id')->translatedFormat('d F Y') : null;
    }

    private function now(): string
    {
        return now()->locale('id')->translatedFormat('d F Y H:i');
    }

    private static function global(string $key): ?string
    {
        $value = AppSetting::get($key);

        return is_bool($value) ? null : self::clean($value === null ? null : (string) $value);
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function say(int $n): string
    {
        $units = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        return match (true) {
            $n < 12 => $units[$n],
            $n < 20 => self::say($n - 10).' belas',
            $n < 100 => self::say(intdiv($n, 10)).' puluh '.self::say($n % 10),
            $n < 200 => 'seratus '.self::say($n - 100),
            $n < 1000 => self::say(intdiv($n, 100)).' ratus '.self::say($n % 100),
            $n < 2000 => 'seribu '.self::say($n - 1000),
            $n < 1000000 => self::say(intdiv($n, 1000)).' ribu '.self::say($n % 1000),
            $n < 1000000000 => self::say(intdiv($n, 1000000)).' juta '.self::say($n % 1000000),
            $n < 1000000000000 => self::say(intdiv($n, 1000000000)).' miliar '.self::say($n % 1000000000),
            default => self::say(intdiv($n, 1000000000000)).' triliun '.self::say($n % 1000000000000),
        };
    }
}
