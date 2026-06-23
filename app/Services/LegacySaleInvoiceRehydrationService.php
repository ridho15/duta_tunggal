<?php

namespace App\Services;

use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacySaleInvoiceRehydrationService
{
    // Preloaded maps for fast in-memory lookup
    private array $saleOrderMap = [];   // (sourceName:legacyId) => {id, customer_id, total_amount, status, cabang_id, created_at}
    private array $customerMap  = [];   // customer_id => {id, name, phone}

    public function rehydrate(array $options = []): array
    {
        $sourceNames  = $this->normalizeSources($options['source'] ?? []);
        $execute      = (bool) ($options['execute'] ?? false);
        $limit        = max(0, (int) ($options['limit'] ?? 0));
        $chunkSize    = max(50, (int) ($options['chunk_size'] ?? 500));
        $dateFrom     = $this->normalizeDate($options['from'] ?? null, false);
        $dateTo       = $this->normalizeDate($options['to'] ?? null, true);
        $createdBy    = $this->resolveCreatedBy($options['created_by'] ?? null);
        $onProgress   = $options['on_progress'] ?? null;
        $inventoryCabangId    = (int) ($options['inventory_cabang_id'] ?? 2);
        $inventoryCabCabangId = (int) ($options['inventory_cab_cabang_id'] ?? 3);

        $summary = [
            'mode'      => $execute ? 'execute' : 'dry-run',
            'sources'   => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to'   => $dateTo?->toDateString(),
            'limit'     => $limit,
            'created_by' => $createdBy,
            'rows'      => [],
            'notes'     => [
                'Rehidrasi invoice penjualan membuat invoice + account_receivable + customer_receipt dari arsip legacy.',
                'Idempotensi: skip jika invoice_number LEGACY-SO-{source}-{legacy_id} sudah ada.',
                'Invoice items dikosongkan (header-only); total diambil dari sale_order.total_amount.',
                'Customer receipts dibuat satu per baris payment dari arsip legacy.',
            ],
        ];

        // Pre-load sale_orders map once (all sources)
        $this->preloadSaleOrderMap();
        $this->preloadCustomerMap();

        foreach ($sourceNames as $sourceName) {
            $cabangId = $sourceName === 'inventory_cab' ? $inventoryCabCabangId : $inventoryCabangId;

            $totalDocuments = $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)->count();
            if ($limit > 0) {
                $totalDocuments = min($totalDocuments, $limit);
            }

            $processed = 0;
            $invoicesCreated = 0;
            $skippedNoSaleOrder = 0;
            $skippedDuplicate = 0;
            $receiptsCreated = 0;
            $arCreated = 0;

            $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)
                ->chunkById($chunkSize, function ($documents) use (
                    $sourceName, $cabangId, $execute, $createdBy, $limit, $onProgress,
                    &$processed, &$invoicesCreated, &$skippedNoSaleOrder,
                    &$skippedDuplicate, &$receiptsCreated, &$arCreated
                ) {
                    $stopRequested = false;

                    // Batch-load payment rows for this chunk's documents
                    $docIds = $documents->pluck('legacy_id')->all();
                    $paymentsByParent = LegacyTransactionArchive::query()
                        ->where('source_name', $sourceName)
                        ->where('transaction_type', 'sale')
                        ->where('row_kind', 'payment')
                        ->whereIn('parent_legacy_id', $docIds)
                        ->orderBy('id')
                        ->get()
                        ->groupBy('parent_legacy_id');

                    foreach ($documents as $document) {
                        if ($limit > 0 && $processed >= $limit) {
                            $stopRequested = true;
                            break;
                        }

                        $processed++;

                        // Map key: "sourceName:legacyId"
                        $mapKey = $sourceName . ':' . $document->legacy_id;
                        $saleOrder = $this->saleOrderMap[$mapKey] ?? null;

                        if (! $saleOrder) {
                            $skippedNoSaleOrder++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $invoiceNumber = 'LEGACY-INV-SO-' . $sourceName . '-' . (int) $document->legacy_id;

                        // Idempotency: skip if already exists
                        $existingInvoiceId = DB::table('invoices')
                            ->where('invoice_number', $invoiceNumber)
                            ->value('id');

                        if ($existingInvoiceId) {
                            $skippedDuplicate++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $payload = is_array($document->payload) ? $document->payload : [];
                        $invoiceDate = $document->document_date
                            ? Carbon::parse($document->document_date)
                            : now();
                        $dueDate = $this->parseDate($payload['payment_due_date'] ?? null) ?? $invoiceDate->copy()->addDays(30);

                        $paymentRows = $paymentsByParent->get((string) $document->legacy_id, collect());
                        $totalPaid   = $paymentRows->sum(function ($row) {
                            $p = is_array($row->payload) ? $row->payload : [];
                            return (float) ($p['payment_value'] ?? 0);
                        });
                        $totalAmount = (float) ($saleOrder->total_amount ?? $document->amount ?? 0);
                        $remaining   = max(0, $totalAmount - $totalPaid);
                        $isPaid      = $remaining <= 0;
                        $invoiceStatus = $isPaid ? 'paid' : 'unpaid';

                        $customerInfo = $this->customerMap[$saleOrder->customer_id] ?? null;
                        $customerName  = $customerInfo->name ?? ($payload['customer_invname'] ?? '-');
                        $customerPhone = $customerInfo->phone ?? ($payload['customer_invphone'] ?? null);

                        if ($execute) {
                            $now = now();

                            $invoiceId = (int) DB::table('invoices')->insertGetId([
                                'invoice_number'  => $invoiceNumber,
                                'from_model_type' => 'App\\Models\\SaleOrder',
                                'from_model_id'   => (int) $saleOrder->id,
                                'invoice_date'    => $invoiceDate->toDateString(),
                                'due_date'        => $dueDate->toDateString(),
                                'subtotal'        => round($totalAmount, 2),
                                'tax'             => 0,
                                'total'           => round($totalAmount, 2),
                                'dpp'             => round($totalAmount, 2),
                                'ppn_rate'        => 0,
                                'tipe_pajak'      => 'None',
                                'status'          => $invoiceStatus,
                                'customer_name'   => $customerName,
                                'customer_phone'  => $customerPhone,
                                'cabang_id'       => $cabangId,
                                'created_at'      => $invoiceDate,
                                'updated_at'      => $now,
                            ]);

                            // Create account_receivable
                            DB::table('account_receivables')->insert([
                                'invoice_id'  => $invoiceId,
                                'customer_id' => (int) $saleOrder->customer_id,
                                'total'       => round($totalAmount, 2),
                                'paid'        => round($totalPaid, 2),
                                'remaining'   => round($remaining, 2),
                                'status'      => $isPaid ? 'Lunas' : 'Belum Lunas',
                                'cabang_id'   => $cabangId,
                                'created_by'  => $createdBy,
                                'created_at'  => $invoiceDate,
                                'updated_at'  => $now,
                            ]);
                            $arCreated++;

                            // Create customer_receipts (one per payment row)
                            foreach ($paymentRows as $paymentRow) {
                                $p = is_array($paymentRow->payload) ? $paymentRow->payload : [];
                                $pDate  = $this->parseDate($p['payment_date'] ?? null) ?? $invoiceDate;
                                $pValue = round((float) ($p['payment_value'] ?? 0), 2);
                                $pMethod = $this->mapPaymentMethod($p['payment_type'] ?? null);

                                if ($pValue <= 0) {
                                    continue;
                                }

                                $receiptId = (int) DB::table('customer_receipts')->insertGetId([
                                    'invoice_id'       => $invoiceId,
                                    'customer_id'      => (int) $saleOrder->customer_id,
                                    'selected_invoices' => json_encode([$invoiceId]),
                                    'invoice_receipts' => json_encode([['invoice_id' => $invoiceId, 'amount' => $pValue]]),
                                    'payment_date'     => $pDate->toDateString(),
                                    'total_payment'    => $pValue,
                                    'payment_method'   => $pMethod,
                                    'notes'            => ($p['payment_bank'] ?? '') . ' ' . ($p['payment_detail'] ?? ''),
                                    'status'           => 'Paid',
                                    'diskon'           => 0,
                                    'payment_adjustment' => 0,
                                    'cabang_id'        => $cabangId,
                                    'created_by'       => $createdBy,
                                    'created_at'       => $pDate,
                                    'updated_at'       => $now,
                                ]);

                                // Create customer_receipt_item
                                DB::table('customer_receipt_items')->insert([
                                    'customer_receipt_id' => $receiptId,
                                    'invoice_id'          => $invoiceId,
                                    'method'              => $pMethod,
                                    'amount'              => $pValue,
                                    'payment_date'        => $pDate->toDateString(),
                                    'created_at'          => $pDate,
                                    'updated_at'          => $now,
                                ]);

                                $receiptsCreated++;
                            }

                            $invoicesCreated++;
                        } else {
                            // Dry-run: just count
                            $invoicesCreated++;
                            $receiptsCreated += $paymentRows->count();
                            $arCreated++;
                        }

                        if ($onProgress) {
                            ($onProgress)($processed);
                        }
                    }

                    return ! $stopRequested;
                });

            $summary['rows'][] = [
                'source'              => $sourceName,
                'cabang_id'           => $cabangId,
                'documents'           => $totalDocuments,
                'processed'           => $processed,
                'invoices_created'    => $invoicesCreated,
                'ar_created'          => $arCreated,
                'receipts_created'    => $receiptsCreated,
                'skipped_no_so'       => $skippedNoSaleOrder,
                'skipped_duplicate'   => $skippedDuplicate,
            ];
        }

        return $summary;
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function preloadSaleOrderMap(): void
    {
        $this->saleOrderMap = [];
        $rows = DB::table('sale_orders')
            ->whereNotNull('legacy_source_name')
            ->whereNotNull('legacy_legacy_id')
            ->whereNull('deleted_at')
            ->get(['id', 'customer_id', 'total_amount', 'status', 'cabang_id', 'created_at', 'legacy_source_name', 'legacy_legacy_id']);

        foreach ($rows as $row) {
            $key = $row->legacy_source_name . ':' . $row->legacy_legacy_id;
            $this->saleOrderMap[$key] = $row;
        }
    }

    private function preloadCustomerMap(): void
    {
        $this->customerMap = [];
        $rows = DB::table('customers')
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'phone']);

        foreach ($rows as $row) {
            $this->customerMap[(int) $row->id] = $row;
        }
    }

    private function documentsQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = LegacyTransactionArchive::query()
            ->where('source_name', $sourceName)
            ->where('table_name', 'sales')
            ->where('row_kind', 'document')
            ->orderBy('id');

        if ($dateFrom) {
            $query->whereDate('document_date', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('document_date', '<=', $dateTo->toDateString());
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    private function mapPaymentMethod(?string $legacyType): string
    {
        return match (strtolower(trim((string) $legacyType))) {
            'cash'                  => 'Tunai',
            'giro'                  => 'Giro',
            'transfer', 'debit'     => 'Transfer',
            'kredit', 'credit card' => 'Kartu Kredit',
            default                 => 'Tunai',
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $date = Carbon::parse((string) $value);
        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private function normalizeSources(array $sourceNames): array
    {
        $sourceNames = array_values(array_filter(array_map('trim', $sourceNames)));

        if ($sourceNames === []) {
            return ['inventory', 'inventory_cab'];
        }

        foreach ($sourceNames as $sourceName) {
            if (! in_array($sourceName, ['inventory', 'inventory_cab'], true)) {
                throw new InvalidArgumentException('Source harus inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sourceNames));
    }

    private function resolveCreatedBy(mixed $createdBy): int
    {
        if ($createdBy !== null && $createdBy !== '') {
            return (int) $createdBy;
        }

        return (int) DB::table('users')->orderBy('id')->value('id');
    }
}
