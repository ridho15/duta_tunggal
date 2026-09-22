<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button wire:click="save" color="primary">
                    Simpan Pengaturan
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
