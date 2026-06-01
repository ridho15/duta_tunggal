<?php

namespace Tests\Unit;

use App\Http\Controllers\PdfPreviewController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create base data using factories
    $this->cabang = \App\Models\Cabang::factory()->create();
    $this->currency = \App\Models\Currency::factory()->create(['code' => 'IDR']);
    $this->supplier = \App\Models\Supplier::factory()->create();
    $this->customer = \App\Models\Customer::factory()->create();
    $this->uom = \App\Models\UnitOfMeasure::factory()->create();

    // Mock PDF facade
    PDF::shouldReceive('loadView')
        ->andReturnSelf();
    PDF::shouldReceive('setPaper')
        ->andReturnSelf();
    PDF::shouldReceive('stream')
        ->andReturn(response('', 200));
    PDF::shouldReceive('download')
        ->andReturn(response('', 200));
});

describe('PdfPreviewController', function () {
    describe('Document Types Configuration', function () {
        it('has correct configuration for all document types', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            $expectedTypes = [
                'order-request',
                'purchase-order',
                'purchase-invoice',
                'quotation',
                'sale-order',
                'sales-invoice',
            ];

            foreach ($expectedTypes as $type) {
                expect($config)->toHaveKey($type);
                expect($config[$type])->toHaveKeys(['model', 'blade', 'bladeVar', 'paper', 'orientation', 'filename', 'relations']);
            }
        });

        it('maps order-request to OrderRequest model with correct bladeVar', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['order-request']['model'])->toBe(OrderRequest::class);
            expect($config['order-request']['blade'])->toBe('pdf.order-request');
            expect($config['order-request']['bladeVar'])->toBe('orderRequest');
            expect($config['order-request']['paper'])->toBe('a4');
            expect($config['order-request']['orientation'])->toBe('landscape');
        });

        it('maps purchase-order to PurchaseOrder model with correct bladeVar', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['purchase-order']['model'])->toBe(PurchaseOrder::class);
            expect($config['purchase-order']['blade'])->toBe('pdf.purchase-order');
            expect($config['purchase-order']['bladeVar'])->toBe('purchaseOrder');
        });

        it('maps purchase-invoice to Invoice model with correct bladeVar', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['purchase-invoice']['model'])->toBe(Invoice::class);
            expect($config['purchase-invoice']['blade'])->toBe('pdf.purchase-order-invoice-2');
            expect($config['purchase-invoice']['bladeVar'])->toBe('invoice');
        });

        it('maps quotation to Quotation model with correct bladeVar', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['quotation']['model'])->toBe(Quotation::class);
            expect($config['quotation']['blade'])->toBe('pdf.quotation');
            expect($config['quotation']['bladeVar'])->toBe('quotation');
        });

        it('maps sale-order to SaleOrder model with correct bladeVar', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['sale-order']['model'])->toBe(SaleOrder::class);
            expect($config['sale-order']['blade'])->toBe('pdf.sales-order');
            expect($config['sale-order']['bladeVar'])->toBe('saleOrder');
        });

        it('maps sales-invoice to Invoice model with sale-order-invoice blade', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['sales-invoice']['model'])->toBe(Invoice::class);
            expect($config['sales-invoice']['blade'])->toBe('pdf.sale-order-invoice');
            expect($config['sales-invoice']['bladeVar'])->toBe('invoice');
        });
    });

    describe('resolveConfig method', function () {
        it('returns config for valid document type', function () {
            $controller = new PdfPreviewController();

            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('resolveConfig');
            $method->setAccessible(true);

            $config = $method->invoke($controller, 'order-request');

            expect($config)->toHaveKey('model');
            expect($config['model'])->toBe(OrderRequest::class);
        });

        it('throws 404 for invalid document type', function () {
            $controller = new PdfPreviewController();

            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('resolveConfig');
            $method->setAccessible(true);

            expect(fn () => $method->invoke($controller, 'invalid-type'))
                ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        });
    });

    describe('resolveRecord method', function () {
        it('loads record with required relations', function () {
            $orderRequest = \App\Models\OrderRequest::factory()->create([
                'cabang_id' => $this->cabang->id,
                'currency_id' => $this->currency->id,
            ]);

            $controller = new PdfPreviewController();

            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('resolveRecord');
            $method->setAccessible(true);

            // Use valid relations from OrderRequest model
            $config = [
                'model' => OrderRequest::class,
                'relations' => ['createdBy'],
            ];

            $record = $method->invoke($controller, $config, $orderRequest->id);

            expect($record->id)->toBe($orderRequest->id);
            expect($record->relationLoaded('createdBy'))->toBeTrue();
        });

        it('throws 404 for non-existent record', function () {
            $controller = new PdfPreviewController();

            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('resolveRecord');
            $method->setAccessible(true);

            $config = [
                'model' => OrderRequest::class,
                'relations' => [],
            ];

            expect(fn () => $method->invoke($controller, $config, 99999))
                ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        });
    });

    describe('filename generation', function () {
        it('generates correct filename for order-request', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            $orderRequest = \App\Models\OrderRequest::factory()->create([
                'cabang_id' => $this->cabang->id,
                'currency_id' => $this->currency->id,
            ]);

            $filename = $config['order-request']['filename']($orderRequest);

            expect($filename)->toBe("Order_Request_{$orderRequest->id}.pdf");
        });

        it('generates correct filename for purchase-order using po_number', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            $purchaseOrder = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'po_number' => 'PO-TEST001',
            ]);

            $filename = $config['purchase-order']['filename']($purchaseOrder);

            expect($filename)->toBe('Purchase_Order_PO-TEST001.pdf');
        });

        it('generates correct filename for quotation using quotation_number', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            $quotation = Quotation::factory()->create([
                'customer_id' => $this->customer->id,
                'quotation_number' => 'QT-2024-001',
            ]);

            $filename = $config['quotation']['filename']($quotation);

            expect($filename)->toBe('Quotation_QT-2024-001.pdf');
        });

        it('generates correct filename for sale-order using so_number', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            $saleOrder = SaleOrder::factory()->create([
                'customer_id' => $this->customer->id,
                'cabang_id' => $this->cabang->id,
                'so_number' => 'SO-2024-001',
            ]);

            $filename = $config['sale-order']['filename']($saleOrder);

            expect($filename)->toBe('Sales_Order_SO-2024-001.pdf');
        });

        it('generates correct filename for invoices using invoice_number', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            // Create a simple mock just to verify filename generation
            $mockInvoice = new Invoice();
            $mockInvoice->invoice_number = 'INV-2024-001';

            // Purchase invoice
            $purchaseInvoiceFilename = $config['purchase-invoice']['filename']($mockInvoice);
            expect($purchaseInvoiceFilename)->toBe('Invoice_PO_INV-2024-001.pdf');

            // Sales invoice
            $salesInvoiceFilename = $config['sales-invoice']['filename']($mockInvoice);
            expect($salesInvoiceFilename)->toBe('Invoice_Penjualan_INV-2024-001.pdf');
        });
    });

    describe('paper size and orientation', function () {
        it('sets correct paper and orientation for each document type', function () {
            $controller = new PdfPreviewController();
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('documentConfig');
            $property->setAccessible(true);
            $config = $property->getValue($controller);

            expect($config['order-request']['paper'])->toBe('a4');
            expect($config['order-request']['orientation'])->toBe('landscape');

            expect($config['purchase-order']['paper'])->toBe('a4');
            expect($config['purchase-order']['orientation'])->toBe('portrait');

            expect($config['quotation']['paper'])->toBe('a4');
            expect($config['quotation']['orientation'])->toBe('landscape');

            expect($config['sale-order']['paper'])->toBe('a4');
            expect($config['sale-order']['orientation'])->toBe('portrait');
        });
    });
});