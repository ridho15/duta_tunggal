<x-filament::page>
    <x-filament-panels::form wire:submit="create">

        {{ $this->form }}
        <div class="text-center" style="margin-top: 10px">
            {{-- Spinner saat loading --}}
            <div wire:loading>
                <svg class="w-6 h-6 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span class="ml-2">Loading ...</span>
            </div>
        </div>
        <div class="flex items-center gap-4 mt-4">
            {{-- Tombol Simpan --}}
            <x-filament::button type="submit">
                <div
                    style="display: flex; align-items: center; margin-top: 5px; margin-bottom: 5px; margin-left: 10px; margin-right: 10px">
                    <x-heroicon-o-check class="w-5 h-5 mr-1" />
                    Simpan
                </div>
            </x-filament::button>

            {{-- Tombol Cancel --}}
            <x-filament::button color="gray" tag="a" href="{{ static::getResource()::getUrl() }}">
                <div
                    style="display: flex; align-items: center; margin-top: 5px; margin-bottom: 5px; margin-left: 10px; margin-right: 10px">
                    <x-heroicon-o-x-circle class="w-5 h-5 mr-1" />
                    Batal
                </div>
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament::page>