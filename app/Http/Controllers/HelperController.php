<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

    public static function generateRandomColors(int $count): array
    {
        $colors = [];

        for ($i = 0; $i < $count; $i++) {
            $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
        }

        return $colors;
    }

    /**
     * Returns a mapping between resource names and the set of actions
     * that can be performed on them.  This is used by the permission
     * seeder, the audit tests and various authorization helpers.
     *
     * The format must stay backwards compatible (resource => [action1,
     * action2, …]) because the test-suite and seeder iterate over the
     * values directly.  Do not change it to include descriptions here.
     */
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
                'response',
                'request',
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
            'asset' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'asset depreciation' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'asset disposal' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'asset transfer' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'cash bank account' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'cash bank transaction detail' => [
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
            'delivery order' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'request',
                'response'
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
            'delivery order approval log' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'delivery order log' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'delivery sales order' => [
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
                'force-delete',
                'request',
                'response'
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
                'force-delete',
                'request',
                'response',
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
                'force-delete',
                'approve'
            ],
            'stock transfer' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'request',
                'response'
            ],
            'stock transfer item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'quotation' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'request-approve',
                'reject',
                'approve'
            ],
            'quotation item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'request-approve',
                'approve'
            ],
            'surat jalan' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'request',
                'response'
            ],
            'return product' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'approve',
            ],
            'return product item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'order request' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'approve'
            ],
            'order request item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'inventory stock' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'stock movement' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'cabang' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'product unit conversion' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'production' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'vendor payment' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'vendor payment detail' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'account payable' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'ageing schedule' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'chart of account' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'purchase order currency' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'deposit' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'deposit log' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'purchase order biaya' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'tax setting' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'bill of material' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'bill of material item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'account receivable' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'customer receipt' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'customer receipt item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'purchase return' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'purchase return item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'invoice' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
            ],
            'invoice item' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'journal entry' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete'
            ],
            'voucher request' => [
                'view any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force-delete',
                'submit',
                'approve',
                'reject',
                'cancel',
            ],
        ];

        return $listPermissions;
    }

    /**
     * Generate a map of full permission names ("action resource") to an
     * Indonesian description explaining its purpose.  The descriptions are
     * generated programmatically for common CRUD/workflow actions, and the
     * method falls back to a generic template if a specific phrase is not
     * defined.  Seeders and UI components can call this helper to retrieve
     * human‑readable text.
     *
     * @return array<string,string>
     */
    public static function permissionDescriptions()
    {
        $descs = [];
        $templates = [
            'view any'    => 'Melihat daftar semua %s',
            'view'        => 'Melihat detail %s',
            'create'      => 'Membuat %s baru',
            'update'      => 'Memperbarui %s',
            'delete'      => 'Menghapus %s',
            'restore'     => 'Mengembalikan %s yang dihapus',
            'force-delete'=> 'Menghapus permanen %s',
            'request'     => 'Mengajukan permintaan untuk %s',
            'response'    => 'Menanggapi permintaan %s',
            'submit'      => 'Mengajukan %s',
            'approve'     => 'Menyetujui %s',
            'reject'      => 'Menolak %s',
            'cancel'      => 'Membatalkan %s',
            'request-approve' => 'Meminta persetujuan %s',
            'request'     => 'Meminta %s',
            'reject'      => 'Menolak %s',
            'approve'     => 'Menyetujui %s',
            'request-approve' => 'Meminta persetujuan %s',
            'submit'      => 'Mengirimkan %s',
            'approve'     => 'Menyetujui %s',
            'reject'      => 'Menolak %s',
        ];

        foreach (self::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                $name = $action . ' ' . $resource;
                $tpl  = $templates[$action] ?? 'Izin untuk ' . $action . ' ' . $resource;
                $descs[$name] = sprintf($tpl, $resource);
            }
        }
        // manual refinements if needed (special cases)
        $descs['request sales order'] = 'Meminta persetujuan atau tindakan lebih lanjut pada sales order';
        $descs['response sales order'] = 'Memberi jawaban (terima/tolak) atas permintaan sales order';
        $descs['request-approve quotation'] = 'Meminta persetujuan pada quotation';
        $descs['approve quotation'] = 'Menyetujui quotation';
        $descs['reject quotation'] = 'Menolak quotation';
        $descs['submit voucher request'] = 'Mengirimkan permintaan voucher';
        $descs['approve voucher request'] = 'Menyetujui permintaan voucher';
        $descs['reject voucher request'] = 'Menolak permintaan voucher';
        $descs['cancel voucher request'] = 'Membatalkan permintaan voucher';
        // ...any other hand-curated descriptions here

        return $descs;
    }

    /**
     * Return Indonesian descriptions for each role defined in the system.
     *
     * @return array<string,string>
     */
    public static function roleDescriptions()
    {
        return [
            'Owner'            => 'Pengguna dengan hak penuh; biasanya pemilik perusahaan atau administrator tingkat tertinggi.',
            'Super Admin'      => 'Administrator sistem dengan hampir semua izin, kecuali beberapa operasi ekstrim.',
            'Admin'            => 'Administrator umum yang mengelola pengguna, peran, dan beberapa konfigurasi dasar.',
            'Sales Manager'    => 'Mengawasi tim penjualan dan memiliki akses ke data sales order, quotation, dan invoice.',
            'Sales'            => 'Staf penjualan yang membuat dan melihat sales order serta quotation pelanggan.',
            'Kasir'            => 'Bertanggung jawab mencatat penerimaan pembayaran pelanggan dan membuat invoice.',
            'Inventory Manager'=> 'Mengelola persediaan, gudang, transfer stok, dan konfigurasi produk.',
            'Admin Inventory'  => 'Role pendukung di departemen inventaris dengan akses terbatas tetapi luas ke modul gudang.',
            'Checker'          => 'Memeriksa konfirmasi gudang, kontrol kualitas, dan stok.',
            'Finance Manager'  => 'Mengelola keuangan, akun payables/receivables, dan laporan keuangan.',
            'Admin Keuangan'   => 'Pendukung manajer keuangan dengan hak akses ke sebagian besar modul keuangan.',
            'Accounting'       => 'Bertanggung jawab atas buku besar, jurnal, dan catatan akuntansi lainnya.',
            'Purchasing'       => 'Staf pembelian yang membuat dan memantau purchase order dan penerimaan.',
            'Purchasing Manager'=> 'Mengawasi kegiatan pembelian dan dapat memproses pembayaran vendor.',
            'Warehouse Staff'  => 'Staf gudang yang mengelola pengiriman, transfer, dan stok fisik.',
            'Delivery Driver'  => 'Pengemudi yang melihat dan memperbarui informasi delivery order serta surat jalan.',
            'Customer Service' => 'Layanan pelanggan yang menangani customer, quotation, dan sales order.',
            'Auditor'          => 'Mempunyai hak baca (view any) di seluruh modul untuk keperluan audit.',
            'IT Support'       => 'Tim teknis yang mengelola akun, izin, dan konfigurasi sistem.',
        ];
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

    public static function parseIndonesianMoney($formattedValue)
    {
        if (!$formattedValue) {
            return 0;
        }

        // Convert to string to ensure we're working with formatted input
        $formattedValue = (string)$formattedValue;

        // Check if it contains formatting characters (dots or commas)
        if (!preg_match('/[.,]/', $formattedValue)) {
            // No formatting characters, treat as regular number
            return (float)$formattedValue;
        }

        // Remove any non-numeric characters except dots and commas
        $cleaned = preg_replace('/[^\d.,]/', '', $formattedValue);

        // Determine the format by analyzing the separators
        $hasComma = strpos($cleaned, ',') !== false;
        $hasDot = strpos($cleaned, '.') !== false;

        $integer = '';
        $decimal = '0';

        if ($hasComma && $hasDot) {
            // Both separators present - need to determine which is decimal separator
            $lastCommaPos = strrpos($cleaned, ',');
            $lastDotPos = strrpos($cleaned, '.');

            if ($lastDotPos > $lastCommaPos) {
                // Dot comes after comma - likely Western format (commas as thousand sep, dot as decimal)
                // Example: 125,000,000.50
                $parts = explode('.', $cleaned);
                if (count($parts) === 2) {
                    $integer = str_replace(',', '', $parts[0]);
                    $decimal = $parts[1];
                } else {
                    // Multiple dots - take last part as decimal
                    $decimal = array_pop($parts);
                    $integer = implode('', $parts);
                    $integer = str_replace(',', '', $integer);
                }
            } else {
                // Comma comes after dot - likely Indonesian format (dots as thousand sep, comma as decimal)
                // Example: 125.000.000,50
                $parts = explode(',', $cleaned);
                if (count($parts) === 2) {
                    $integer = str_replace('.', '', $parts[0]);
                    $decimal = $parts[1];
                } else {
                    // Multiple commas - take last part as decimal
                    $decimal = array_pop($parts);
                    $integer = implode('', $parts);
                    $integer = str_replace('.', '', $integer);
                }
            }
        } elseif ($hasComma) {
            // Only commas - could be Western thousand separators or Indonesian decimal
            $parts = explode(',', $cleaned);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Likely Western format: 125,000,000 (no decimal) or 125,000,000.50 (handled above)
                $integer = str_replace(',', '', $parts[0]);
                $decimal = $parts[1];
            } else {
                // Multiple commas - treat as thousand separators
                $integer = str_replace(',', '', $cleaned);
                $decimal = '0';
            }
        } elseif ($hasDot) {
            // Only dots - could be Indonesian thousand separators or decimal
            if (preg_match('/\.(\d{1,2})$/', $cleaned, $matches)) {
                // Ends with .digits (1-3 digits) - likely decimal part
                $decimal = $matches[1];
                $integer = preg_replace('/\.\d{1,3}$/', '', $cleaned);
                $integer = str_replace('.', '', $integer);
            } else {
                // All dots are thousand separators
                $integer = str_replace('.', '', $cleaned);
                $decimal = '0';
            }
        }

        // Ensure decimal part is not empty
        if (empty($decimal)) {
            $decimal = '0';
        }

        // Combine back and return as float
        $numeric = (float)($integer . '.' . $decimal);
        return $numeric;
    }

    public static function hitungSubtotal($quantity, $unit_price, $discount, $tax, $taxType = null)
    {
        // Normalize unit price if passed as formatted string (contains dot thousand separators)
        if (is_string($unit_price) && preg_match('/[.,]/', $unit_price)) {
            $parsed = self::parseIndonesianMoney($unit_price);
            $unit_price = $parsed;
        }
        // --- INPUT GUARDS ---
        $quantity  = max(0.0, (float) $quantity);
        $unit_price = max(0.0, (float) $unit_price);
        $discount  = max(0.0, min(100.0, (float) $discount));  // clamp 0-100%
        $tax       = max(0.0, min(100.0, (float) $tax));        // clamp 0-100%
        // Default to 'Inklusif' when taxType is null/empty — maintained for backward compatibility.
        // NOTE: TaxService::normalizeType(null) defaults to 'Eksklusif'. If you call TaxService
        // directly (not through this helper), ensure you pass an explicit type to avoid discrepancies.
        $taxType   = (is_string($taxType) && trim($taxType) !== '') ? $taxType : 'Inklusif';

        // Calculate base after discount
        $subtotal = $quantity * $unit_price;
        $discountAmount = $subtotal * ($discount / 100);
        $afterDiscount = $subtotal - $discountAmount;

        // Use centralized tax service
        $rate = $tax; // expecting percent, e.g., 12 for 12%
        try {
            // Lazy import to avoid circular deps in some contexts
            $service = \App\Services\TaxService::class;
            $result = $service::compute($afterDiscount, $rate, $taxType);
            $final = round($result['total'], 2);
            return $final;
        } catch (\Throwable $e) {
            // Log the error so it is not silently swallowed (guarded for unit-test contexts without app bootstrap)
            if (function_exists('app') && app()->bound('log')) {
                \Illuminate\Support\Facades\Log::error('hitungSubtotal: TaxService exception, falling back to exclusive', [
                    'quantity'   => $quantity,
                    'unit_price' => $unit_price,
                    'discount'   => $discount,
                    'tax'        => $tax,
                    'taxType'    => $taxType,
                    'error'      => $e->getMessage(),
                ]);
            }
            // Fallback: previous behavior (exclusive)
            $tax_amount = $afterDiscount * $rate / 100.0;
            $total = $afterDiscount + $tax_amount;
            $final = round($total, 2);
            return $final;
        }
    }

    public static function sendNotification($isSuccess = false, $title = "", $message = "")
    {
        if ($isSuccess) {
            return Notification::make()
                ->body($message)
                ->title($title)
                ->success()
                ->send();
        } else {
            return Notification::make()
                ->body($message)
                ->title($title)
                ->danger()
                ->send();
        }
    }

    public static function sendNotificationToUser() {}

    public static function terbilang($number)
    {
        $number = abs($number);
        $words = [
            "",
            "satu",
            "dua",
            "tiga",
            "empat",
            "lima",
            "enam",
            "tujuh",
            "delapan",
            "sembilan",
            "sepuluh",
            "sebelas"
        ];

        $temp = "";
        if ($number < 12) {
            $temp = " " . $words[$number];
        } else if ($number < 20) {
            $temp = static::terbilang($number - 10) . " belas ";
        } else if ($number < 100) {
            $temp = static::terbilang($number / 10) . " puluh " . static::terbilang($number % 10);
        } else if ($number < 200) {
            $temp = " seratus " . static::terbilang($number - 100);
        } else if ($number < 1000) {
            $temp = static::terbilang($number / 100) . " ratus " . static::terbilang($number % 100);
        } else if ($number < 2000) {
            $temp = " seribu" . static::terbilang($number - 1000);
        } else if ($number < 1000000) {
            $temp = static::terbilang($number / 1000) . " ribu " . static::terbilang($number % 1000);
        } else if ($number < 1000000000) {
            $temp = static::terbilang($number / 1000000) . " juta " . static::terbilang($number % 1000000);
        } else if ($number < 1000000000000) {
            $temp = static::terbilang($number / 1000000000) . " milyar " . static::terbilang(fmod($number, 1000000000));
        } else if ($number < 1000000000000000) {
            $temp = static::terbilang($number / 1000000000000) . " triliun " . static::terbilang(fmod($number, 1000000000000));
        }

        return trim($temp);
    }

    public static function setLog($message, $model)
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($model)
            ->log($message);
    }

    /**
     * Format amount dengan pemisah ribuan titik (.) dan desimal koma (,)
     * Format Indonesia: 1.234.567,89
     *
     * @param float|int|string $amount
     * @param int $decimals
     * @return string
     */
    public static function formatAmount($amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals, ',', '.');
    }

    /**
     * Format amount tanpa desimal
     * Format: 1.234.567
     *
     * @param float|int|string $amount
     * @return string
     */
    public static function formatAmountNoDecimal($amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Format amount dengan prefix Rp
     * Format: Rp 1.234.567,89
     *
     * @param float|int|string $amount
     * @param int $decimals
     * @return string
     */
    public static function formatCurrency($amount, int $decimals = 2): string
    {
        return 'Rp ' . self::formatAmount($amount, $decimals);
    }

    /**
     * Format currency tanpa desimal
     * Format: Rp 1.234.567
     *
     * @param float|int|string $amount
     * @return string
     */
    public static function formatCurrencyNoDecimal($amount): string
    {
        return 'Rp ' . self::formatAmountNoDecimal($amount);
    }

    /**
     * Generate request number for Order Request
     * Format: OR-YYYYMMDD-XXXX
     *
     * @return string
     */
    public static function generateUniqueCode(string $table, string $column, string $prefix, int $digits = 4): string
    {
        // produce random numeric suffix of specified length and loop until unique
        $max = (int) pow(10, $digits) - 1;

        do {
            $random = str_pad(rand(0, $max), $digits, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = DB::table($table)->where($column, $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public static function generateRequestNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'OR-' . $date . '-';

        // generate random 4-digit suffix and ensure uniqueness by checking database
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = \App\Models\OrderRequest::where('request_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Generate PO number for Purchase Order
     * Format: PO-YYYYMMDD-XXXX
     *
     * @return string
     */
    public static function generatePoNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'PO-' . $date . '-';

        // Find the latest PO number for today
        $latest = \App\Models\PurchaseOrder::where('po_number', 'like', $prefix . '%')
            ->orderBy('po_number', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->po_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
