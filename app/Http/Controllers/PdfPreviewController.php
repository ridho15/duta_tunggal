<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use Barryvdh\DomPDF\Facade\Pdf;

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
                'bladeVar'    => 'record',
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
                'bladeVar'    => 'record',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Quotation_{$r->quotation_number}.pdf",
                'relations'   => ['customer', 'quotationItem.product.uom', 'cabang'],
            ],
            'sale-order' => [
                'model'       => SaleOrder::class,
                'blade'       => 'pdf.sales-order',
                'bladeVar'    => 'record',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Sales_Order_{$r->so_number}.pdf",
                'relations'   => ['customer', 'saleOrderItem.product.uom', 'cabang'],
            ],
            'sales-invoice' => [
                'model'       => Invoice::class,
                'blade'       => 'pdf.sale-order-invoice',
                'bladeVar'    => 'record',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Invoice_Penjualan_{$r->invoice_number}.pdf",
                'relations'   => ['customer', 'invoiceItem.product.uom', 'cabang'],
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
            'surat-jalan' => [
                'model'       => \App\Models\SuratJalan::class,
                'blade'       => 'pdf.surat-jalan',
                'bladeVar'    => 'suratJalan',
                'paper'       => 'a4',
                'orientation' => 'portrait',
                'filename'    => fn($r) => "Surat_Jalan_{$r->sj_number}.pdf",
                'relations'   => ['deliveryOrder.customer', 'deliveryOrder.deliveryOrderItem.product'],
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

        $pdf = Pdf::loadView($config['blade'], [$config['bladeVar'] => $record])
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
