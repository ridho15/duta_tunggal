<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Info List -->
        {{ $this->infolist }}

        <!-- Journal Entries Section -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5" />
                    Journal Entries
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Daftar journal entries yang terkait dengan transaksi penjualan lainnya ini
                </p>
            </div>

            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>