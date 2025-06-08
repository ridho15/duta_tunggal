<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HelperController extends Controller
{
    public static function saveSignatureImage($base64Image)
    {
        $image_parts = explode(";base64,", $base64Image);
        if (count($image_parts) <= 1) {
            return null;
        }
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1] ?? 'png';
        $image_base64 = base64_decode($image_parts[1]);

        $fileName = 'signature_' . uniqid() . '.' . $image_type;
        $filePath = 'signatures/' . $fileName;

        // Simpan ke public/storage/signatures/
        Storage::disk('public')->put($filePath, $image_base64);

        return '/' . $filePath;
    }

    public static function listPermission()
    {
        $listPermissions = [
            'user' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'role' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'permission' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'purchase order' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'approve'
            ],
            'purchase order item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'purchase receipt' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'purchase receipt item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'product' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'product category' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'currency' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'customer' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'driver' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'currency' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'delivery order' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'delivery order item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'manufacturing order' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'manufacturing order item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'quality control' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'rak' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'sales order' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'sales order item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'supplier' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'unit of measure' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'vehicle' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'warehouse confirmation' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'warehouse' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'stock transfer' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
        ];

        return $listPermissions;
    }

    public static function getPermissionOwner()
    {
        return [
            'purchase order',
            'purchase order item',
        ];
    }

    public static function getPermissionGudang()
    {
        return [
            'warehouse',
            'quality control',
            'purchase receipt'
        ];
    }

    public static function cetakPO($purchaseOrder)
    {
        $pdf = Pdf::loadView('pdf.purchase-order', [
            'purchaseOrder' => $purchaseOrder
        ]);
        return $pdf->stream('PO-' . $purchaseOrder->po_number . '.pdf');
    }
}
