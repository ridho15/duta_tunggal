<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = now()->startOfMonth()->format('Y-m-d');
$end = now()->format('Y-m-d');

$service = app(App\Services\Reports\PurchaseReportService::class);
$user = App\Models\User::query()
    ->where('manage_type', 'like', '%all%')
    ->first() ?? App\Models\User::query()->first();

if (! $user) {
    fwrite(STDERR, "No user found\n");
    exit(1);
}

$filters = [
    'start_date' => $start,
    'end_date' => $end,
    'supplier_id' => null,
    'status' => null,
    'sort_by_total' => null,
];

$serviceOrders = $service->query($filters, $user)->get();
$directOrders = App\Models\PurchaseOrder::query()
    ->with(['supplier', 'purchaseOrderItem.product'])
    ->whereDate('order_date', '>=', $start)
    ->whereDate('order_date', '<=', $end)
    ->when(! in_array('all', $user->manage_type ?? [], true), fn ($query) => $query->where('cabang_id', $user->cabang_id))
    ->get();

$serialize = function ($orders) {
    return $orders->map(function ($order) {
        return [
            'po_number' => $order->po_number,
            'order_date' => $order->order_date?->format('Y-m-d'),
            'supplier_code' => $order->supplier->code ?? '-',
            'supplier_name' => $order->supplier->perusahaan ?? '-',
            'status' => $order->status,
            'total_amount' => (float) ($order->total_amount ?? 0),
            'item_count' => $order->purchaseOrderItem->count(),
            'quantity' => (float) $order->purchaseOrderItem->sum('quantity'),
            'unique_products' => $order->purchaseOrderItem->pluck('product_id')->unique()->count(),
        ];
    })->sortBy('po_number')->values();
};

$serviceRows = $serialize($serviceOrders);
$directRows = $serialize($directOrders);

$hash = fn ($rows) => hash('sha256', json_encode($rows->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$servicePayload = $service->pdfPayload($filters, $user);
$directSummary = [
    'total_orders' => $directOrders->count(),
    'total_amount' => (float) $directOrders->sum('total_amount'),
    'average_amount' => $directOrders->count() > 0 ? (float) $directOrders->sum('total_amount') / $directOrders->count() : 0.0,
    'total_quantity' => (float) $directOrders->sum(fn ($order) => $order->purchaseOrderItem->sum('quantity')),
    'unique_products' => $directOrders->flatMap(fn ($order) => $order->purchaseOrderItem->pluck('product_id'))->unique()->count(),
    'status_counts' => [
        'draft' => $directOrders->where('status', 'draft')->count(),
        'approved' => $directOrders->where('status', 'approved')->count(),
        'partially_received' => $directOrders->where('status', 'partially_received')->count(),
        'completed' => $directOrders->where('status', 'completed')->count(),
        'closed' => $directOrders->where('status', 'closed')->count(),
        'processing' => $directOrders->where('status', 'processing')->count(),
        'confirmed' => $directOrders->where('status', 'confirmed')->count(),
        'cancelled' => $directOrders->whereIn('status', ['cancelled', 'canceled'])->count(),
    ],
];

$result = [
    'filters' => $filters,
    'service_count' => $serviceRows->count(),
    'direct_count' => $directRows->count(),
    'hash_match' => $hash($serviceRows) === $hash($directRows),
    'summary_match' => [
        'total_orders' => $servicePayload['summary']['total_orders'] === $directSummary['total_orders'],
        'total_amount' => abs($servicePayload['summary']['total_amount'] - $directSummary['total_amount']) < 0.01,
        'average_amount' => abs($servicePayload['summary']['average_amount'] - $directSummary['average_amount']) < 0.01,
        'total_quantity' => abs($servicePayload['summary']['total_quantity'] - $directSummary['total_quantity']) < 0.01,
        'unique_products' => $servicePayload['summary']['unique_products'] === $directSummary['unique_products'],
        'status_counts' => $servicePayload['summary']['status_counts'] === $directSummary['status_counts'],
    ],
    'sample_service_rows' => $serviceRows->take(3)->all(),
    'sample_direct_rows' => $directOrders->take(3)->map(fn ($order) => [
        'po_number' => $order->po_number,
        'order_date' => $order->order_date?->format('Y-m-d'),
        'supplier_code' => $order->supplier->code ?? '-',
        'supplier_name' => $order->supplier->perusahaan ?? '-',
        'status' => $order->status,
        'total_amount' => (float) ($order->total_amount ?? 0),
        'item_count' => $order->purchaseOrderItem->count(),
        'quantity' => (float) $order->purchaseOrderItem->sum('quantity'),
        'unique_products' => $order->purchaseOrderItem->pluck('product_id')->unique()->count(),
    ])->all(),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
