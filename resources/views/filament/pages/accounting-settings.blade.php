<x-filament-panels::page>
    <div class="space-y-6">
        @unless ($this->flagEnabled)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Pengaturan ini <b>belum berlaku</b> pada jurnal: flag <code>SALES_CONTROLS_ACCOUNTING_SETTINGS</code> masih mati.
                Anda tetap dapat menyiapkan akunnya di sini; jurnal memakai akun bawaan sampai flag dihidupkan.
            </div>
        @endunless

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button wire:click="save" color="primary">
                    Simpan Pengaturan Akuntansi
                </x-filament::button>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-base font-semibold mb-3">Akun yang berlaku saat ini</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1 pr-4">Kunci</th>
                        <th class="py-1 pr-4">Akun efektif</th>
                        <th class="py-1">Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->statusRows() as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1 pr-4">{{ $row['label'] }}</td>
                            <td class="py-1 pr-4">{{ $row['effective'] ? $row['effective']->code . ' — ' . $row['effective']->name : '—' }}</td>
                            <td class="py-1">{{ $row['source'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
