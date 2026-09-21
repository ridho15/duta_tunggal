<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Services\DocumentPrintBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class PdfPreviewController extends Controller
{
    private array $documentConfig;

    public function __construct()
    {
        $this->documentConfig = [
            'order-request' => [
                'model'       => OrderRequest::class,
                'blade'       => 'pdf.order-request',
                'bladeVar'    => 'orderRequest',
                'paper'       => 'a4',
                'orientation' => 'landscape',
                'filename'    => fn($r) => "Order_Request_{$r->id}.pdf",
                'relations'   => ['orderRequestItem.product.uom', 'orderRequestItem.supplier', 'createdBy', 'currency'],
            ],
            'purchase-order' => [
                'model'       => PurchaseOrder::class,
                'blade'       => 'pdf.purchase-order',
                'bladeVar'    => 'purchaseOrder',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Purchase_Order_{$r->po_number}.pdf",
                'relations'   => ['supplier', 'purchaseOrderItem.product.uom', 'cabang'],
            ],
            'purchase-invoice' => [
                'model'       => Invoice::class,
                'blade'       => 'pdf.purchase-order-invoice-2',
                'bladeVar'    => 'invoice',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Invoice_PO_{$r->invoice_number}.pdf",
                'relations'   => ['fromModel.supplier', 'fromModel.purchaseOrderBiaya', 'invoiceItem.product', 'cabang'],
            ],
            'quotation' => [
                'model'       => Quotation::class,
                'blade'       => 'pdf.quotation',
                'bladeVar'    => 'quotation',
                'paper'       => 'a4',
                'orientation' => 'landscape',
                'filename'    => fn($r) => "Quotation_{$r->quotation_number}.pdf",
                'relations'   => ['customer', 'quotationItem.product.uom', 'cabang', 'createdBy', 'approveBy'],
            ],
            'sale-order' => [
                'model'       => SaleOrder::class,
                'blade'       => 'pdf.sales-order',
                'bladeVar'    => 'saleOrder',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Sales_Order_{$r->so_number}.pdf",
                'relations'   => ['customer', 'saleOrderItem.product.uom', 'cabang'],
            ],
            'sales-invoice' => [
                'model'       => Invoice::class,
                'blade'       => 'pdf.sale-order-invoice',
                'bladeVar'    => 'invoice',
                'paper'       => 'a4',
                'orientation' => 'landscape',   // 12 kolom rincian baris (Fase 5B)
                'filename'    => fn($r) => "Invoice_Penjualan_{$r->invoice_number}.pdf",
                'relations'   => ['invoiceItem.product.uom', 'cabang'],
            ],
            'delivery-order' => [
                'model'       => DeliveryOrder::class,
                'blade'       => 'pdf.delivery-order',
                'bladeVar'    => 'deliveryOrder',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Delivery_Order_{$r->do_number}.pdf",
                'relations'   => ['cabang', 'deliveryOrderItem.product.uom', 'salesOrders.customer'],
            ],
            'delivery-schedule' => [
                'model'       => DeliverySchedule::class,
                'blade'       => 'pdf.delivery-schedule-work-order',
                'bladeVar'    => 'schedule',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Jadwal_Pengiriman_{$r->schedule_number}.pdf",
                'relations'   => ['driver', 'vehicle', 'suratJalan.deliveryOrder.deliveryOrderItem.product.uom', 'suratJalan.deliveryOrder.salesOrders.customer', 'cabang'],
            ],
            'credit-note' => [
                'model'       => CreditNote::class,
                'blade'       => 'pdf.credit-note',
                'bladeVar'    => 'creditNote',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Nota_Kredit_{$r->credit_note_number}.pdf",
                'relations'   => ['invoice', 'customer', 'items.product', 'items.invoiceItem.product', 'createdBy', 'issuedBy'],
            ],
            'customer-receipt' => [
                'model'       => CustomerReceipt::class,
                'blade'       => 'pdf.kwitansi',
                'bladeVar'    => 'receipt',
                'paper'       => 'a5',
                'orientation' => 'landscape',
                'filename'    => fn($r) => 'Kwitansi_KW-'.str_pad((string) $r->id, 6, '0', STR_PAD_LEFT).'.pdf',
                'relations'   => ['customer', 'customerReceiptItem.invoice', 'createdBy', 'cabang'],
            ],
            'surat-jalan' => [
                'model'       => \App\Models\SuratJalan::class,
                'blade'       => 'pdf.surat-jalan',
                'bladeVar'    => 'suratJalan',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Surat_Jalan_{$r->sj_number}.pdf",
                'relations'   => \App\Services\SuratJalanDocumentBuilder::RELATIONS,
            ],
        ];
    }

    /**
     * GET /pdf/{type}/{id}
     * Streams PDF directly to browser (opens in new tab).
     */
    public function stream(string $type, int $id)
    {
        $config = $this->resolveConfig($type);
        $record = $this->resolveRecord($config, $id);

        // Otorisasi per dokumen: policy `view` model terkait (izin `view <dokumen>`), bukan sekadar login.
        Gate::authorize('view', $record);

        $viewData = [$config['bladeVar'] => $record];

        // Bingkai cetak (kop, meta, tanda tangan, watermark) disusun satu tempat — Blade hanya menampilkan.
        $builder = app(DocumentPrintBuilder::class);
        match ($type) {
            'sales-invoice' => $viewData['doc'] = $builder->invoice($record),
            'delivery-order' => $viewData['doc'] = $builder->deliveryOrder($record),
            'credit-note' => $viewData['doc'] = $builder->creditNote($record),
            'customer-receipt' => $viewData['doc'] = $builder->receipt($record),
            default => null,
        };

        if ($type === 'delivery-schedule') {
            $viewData['deliveryOrders'] = $record->relatedDeliveryOrders();
        }

        if ($type === 'surat-jalan') {
            // Data cetak disusun satu tempat (tanpa harga; driver/kendaraan dari jadwal; barang per DO).
            $viewData['doc'] = app(\App\Services\SuratJalanDocumentBuilder::class)->build($record);
        }

        $pdf = Pdf::loadView($config['blade'], $viewData)
            ->setPaper($config['paper'], $config['orientation']);

        return $pdf->stream($config['filename']($record));
    }

    private function resolveConfig(string $type): array
    {
        if (!isset($this->documentConfig[$type])) {
            abort(404, "Document type '{$type}' not found.");
        }
        return $this->documentConfig[$type];
    }

    private function resolveRecord(array $config, int $id): mixed
    {
        $model = $config['model'];
        $relations = $config['relations'] ?? [];
        return $model::with($relations)->findOrFail($id);
    }
}
