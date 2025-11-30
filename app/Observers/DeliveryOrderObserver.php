<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use App\Models\StockReservation;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;

class DeliveryOrderObserver
{
    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = app(ProductService::class);
    }

    /**
     * Handle the DeliveryOrder "updated" event.
     */
    public function updated(DeliveryOrder $deliveryOrder): void
    {
        $originalStatus = $deliveryOrder->getOriginal('status');
        $newStatus = $deliveryOrder->status;

        // Jika status berubah ke 'approved', buat stock reservations
        if ($originalStatus !== 'approved' && $newStatus === 'approved') {
            $this->handleApprovedStatus($deliveryOrder);
        }

        // Jika status berubah ke 'sent', hapus stock reservations
        if ($originalStatus !== 'sent' && $newStatus === 'sent') {
            $this->handleSentStatus($deliveryOrder);
        }
    }

    /**
     * Handle when Delivery Order status becomes 'approved'
     * Move qty_available to qty_reserved by creating stock reservations
     */
    protected function handleApprovedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling approved status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0, $item->quantity ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Buat stock reservation untuk memindahkan available ke reserved
            StockReservation::create([
                'sale_order_id' => $item->saleOrderItem->sale_order_id ?? null,
                'product_id' => $item->product_id,
                'warehouse_id' => $deliveryOrder->warehouse_id,
                'rak_id' => $item->rak_id,
                'quantity' => $quantity,
                'delivery_order_id' => $deliveryOrder->id,
            ]);

            // Buat stock movement untuk log
            $this->productService->createStockMovement(
                product_id: $item->product_id,
                warehouse_id: $deliveryOrder->warehouse_id,
                quantity: $quantity,
                type: 'delivery_approved',
                date: $deliveryOrder->delivery_date ?? now()->toDateString(),
                notes: "Delivery Order {$deliveryOrder->do_number} approved - stock reserved",
                rak_id: $item->rak_id,
                fromModel: $deliveryOrder,
                value: 0 // Tidak ada nilai karena hanya reservasi
            );
        }
    }

    /**
     * Handle when Delivery Order status becomes 'sent'
     * Release qty_reserved by deleting stock reservations
     */
    protected function handleSentStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling sent status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Hapus stock reservations yang terkait dengan delivery order ini
        $reservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();

        foreach ($reservations as $reservation) {
            // Buat stock movement untuk log sebelum hapus
            $this->productService->createStockMovement(
                product_id: $reservation->product_id,
                warehouse_id: $reservation->warehouse_id,
                quantity: $reservation->quantity,
                type: 'delivery_sent',
                date: $deliveryOrder->delivery_date ?? now()->toDateString(),
                notes: "Delivery Order {$deliveryOrder->do_number} sent - stock reservation released",
                rak_id: $reservation->rak_id,
                fromModel: $deliveryOrder,
                value: 0
            );

            // Hapus reservation, yang akan trigger observer untuk mengembalikan qty_available
            $reservation->delete();
        }
    }
}