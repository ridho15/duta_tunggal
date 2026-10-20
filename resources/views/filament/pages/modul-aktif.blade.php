<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Modul opsional</x-slot>
        <x-slot name="description">Nyalakan hanya modul yang dipakai perusahaan.</x-slot>

        <div class="divide-y divide-gray-100">
            @foreach ($this->moduleDefinitions as $key => $module)
                <label for="modul-{{ $key }}" class="flex items-start justify-between gap-6 py-4 cursor-pointer">
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">{{ $module['label'] }}</span>
                        <span class="block mt-1 text-sm text-gray-500">{{ $module['description'] }}</span>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-semibold {{ ($modules[$key] ?? false) ? 'text-primary-600' : 'text-gray-400' }}">
                            {{ ($modules[$key] ?? false) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        <input
                            id="modul-{{ $key }}"
                            type="checkbox"
                            wire:model.live="modules.{{ $key }}"
                            class="h-6 w-6 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                    </span>
                </label>
            @endforeach
        </div>

        <div class="mt-6">
            <x-filament::button wire:click="save" color="primary">
                Simpan
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
