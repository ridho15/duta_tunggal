<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">Laporan Inventori</h1>
            <div class="flex space-x-2">
                <x-filament::button wire:click="exportExcel" color="success">Export Excel</x-filament::button>
            </div>
        </div>

        <!-- Report Type Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-4">Pilih Jenis Laporan</h3>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center">
                    <input wire:model.live="show_movement_history" wire:click="$set('show_movement_history', false); $set('show_aging_stock', false)" type="radio" name="report_type" value="0" class="mr-2" checked>
                    <span>Stok per Gudang</span>
                </label>
                <label class="flex items-center">
                    <input wire:model.live="show_movement_history" wire:click="$set('show_movement_history', true); $set('show_aging_stock', false)" type="radio" name="report_type" value="1" class="mr-2">
                    <span>History Movement</span>
                </label>
                <label class="flex items-center">
                    <input wire:model.live="show_aging_stock" wire:click="$set('show_aging_stock', true); $set('show_movement_history', false)" type="radio" name="report_type" value="1" class="mr-2">
                    <span>Aging Stock</span>
                </label>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="filament-forms-field-wrapper" x-show="show_movement_history || show_aging_stock">
                <label class="filament-forms-field-wrapper-label">Tanggal Mulai</label>
                <input wire:model.live="start_date" type="date" class="filament-forms-input">
            </div>
            <div class="filament-forms-field-wrapper" x-show="show_movement_history || show_aging_stock">
                <label class="filament-forms-field-wrapper-label">Tanggal Akhir</label>
                <input wire:model.live="end_date" type="date" class="filament-forms-input">
            </div>
            <div class="filament-forms-field-wrapper">
                <label class="filament-forms-field-wrapper-label">Gudang</label>
                <select wire:model.live="warehouse_id" class="filament-forms-select">
                    <option value="">Semua Gudang</option>
                    @foreach(\App\Models\Warehouse::all() as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filament-forms-field-wrapper">
                <label class="filament-forms-field-wrapper-label">Produk</label>
                <select wire:model.live="product_id" class="filament-forms-select">
                    <option value="">Semua Produk</option>
                    @foreach(\App\Models\Product::all() as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Report Content -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            @if($show_movement_history)
                <div class="p-4 border-b">
                    <h3 class="text-lg font-semibold">History Movement Stok</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Riwayat pergerakan stok dari {{ $start_date }} sampai {{ $end_date }}</p>
                </div>
            @elseif($show_aging_stock)
                <div class="p-4 border-b">
                    <h3 class="text-lg font-semibold">Aging Stock Analysis</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Analisis umur stok untuk deteksi slow-moving items</p>
                </div>
            @else
                <div class="p-4 border-b">
                    <h3 class="text-lg font-semibold">Stok Barang per Gudang</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Informasi stok terkini di setiap gudang</p>
                </div>
            @endif

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>