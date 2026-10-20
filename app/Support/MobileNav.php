<?php

namespace App\Support;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Hp\CekStokHpPage;
use App\Filament\Pages\Hp\KirimanPage;
use App\Filament\Resources\CashBankTransactionResource;
use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\StockTransferResource;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Navigasi bawah tampilan HP (Spesifikasi UI/UX 6.8):
 * Beranda · (menu utama peran) · [+] Buat · Stok · Menu.
 */
class MobileNav
{
    /**
     * Menu utama (slot ke-2) sesuai peran: menu pertama yang boleh dibuka dari daftar ini.
     *
     * @return array{label: string, icon: string, url: string, active: string}|null
     */
    public static function primary(): ?array
    {
        $candidates = [
            ['label' => 'Kirim', 'icon' => 'heroicon-o-truck', 'class' => KirimanPage::class, 'codes' => ['GDG']],
            ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-bag', 'class' => SaleOrderResource::class, 'codes' => ['SM', 'SLS', 'KSR', 'ADP']],
            ['label' => 'Pembelian', 'icon' => 'heroicon-o-shopping-cart', 'class' => PurchaseOrderResource::class, 'codes' => ['PUR']],
            ['label' => 'Kas', 'icon' => 'heroicon-o-banknotes', 'class' => CashBankTransactionResource::class, 'codes' => ['FIN']],
            ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-bag', 'class' => SaleOrderResource::class, 'codes' => ['OWN']],
        ];

        $codes = static::codes();

        foreach ($candidates as $candidate) {
            if (! array_intersect($codes, $candidate['codes'])) {
                continue;
            }

            $url = static::urlIfAllowed($candidate['class']);
            if ($url) {
                return [
                    'label' => $candidate['label'],
                    'icon' => $candidate['icon'],
                    'url' => $url,
                    'active' => parse_url($url, PHP_URL_PATH) ?: $url,
                ];
            }
        }

        return null;
    }

    /** @return array{url: string, active: string}|null */
    public static function stock(): ?array
    {
        $url = static::urlIfAllowed(CekStokHpPage::class);

        return $url ? ['url' => $url, 'active' => parse_url($url, PHP_URL_PATH) ?: $url] : null;
    }

    public static function home(): string
    {
        return Dashboard::getUrl();
    }

    /**
     * Pilihan di sheet "Buat baru", hanya yang boleh dibuat user.
     *
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    public static function createOptions(): array
    {
        $options = [
            [SaleOrderResource::class, 'Pesanan Penjualan (SO)', 'Catat pesanan customer', 'heroicon-o-shopping-bag'],
            [CustomerReceiptResource::class, 'Terima Pembayaran', 'Catat uang masuk dari customer', 'heroicon-o-credit-card'],
            [OrderRequestResource::class, 'Permintaan Pembelian', 'Minta barang dibelikan', 'heroicon-o-document-plus'],
            [StockTransferResource::class, 'Pindah Stok', 'Pindahkan barang antar gudang', 'heroicon-o-arrows-right-left'],
        ];

        $result = [];
        foreach ($options as [$resource, $label, $description, $icon]) {
            try {
                if (! MenuAccess::allows($resource) || ! $resource::canAccess() || ! $resource::canCreate()) {
                    continue;
                }
                $result[] = [
                    'label' => $label,
                    'description' => $description,
                    'icon' => $icon,
                    'url' => $resource::getUrl('create'),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $result;
    }

    /** @return array<int, string> */
    protected static function codes(): array
    {
        $user = Auth::user();

        return $user ? MenuAccess::codesFor($user) : [];
    }

    protected static function urlIfAllowed(string $class): ?string
    {
        try {
            if (! class_exists($class) || ! $class::canAccess()) {
                return null;
            }

            return method_exists($class, 'getUrl') && is_subclass_of($class, \Filament\Resources\Resource::class)
                ? $class::getUrl('index')
                : $class::getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
