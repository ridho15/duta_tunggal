@php
    $record = $getRecord();
    $permissionDescriptions = \App\Http\Controllers\HelperController::permissionDescriptions();
    $permissionModules = collect(\App\Http\Controllers\HelperController::listPermission())
        ->flatMap(function (array $actions, string $module): array {
            return collect($actions)
                ->mapWithKeys(fn (string $action): array => [trim($action . ' ' . $module) => $module])
                ->all();
        })
        ->all();
    $directPermissionNames = $record ? $record->permissions->pluck('name')->all() : [];
    $rows = $record
        ? $record->getAllPermissions()
            ->sortBy('name')
            ->values()
            ->map(function ($permission) use ($permissionDescriptions, $permissionModules, $directPermissionNames) {
                $name = $permission->name;

                return [
                    'name' => $name,
                    'module' => $permissionModules[$name] ?? '-',
                    'description' => $permission->description ?: ($permissionDescriptions[$name] ?? \Illuminate\Support\Str::headline($name)),
                    'source' => in_array($name, $directPermissionNames, true) ? 'Langsung' : 'Dari Role',
                ];
            })
        : collect();
    $directCount = $rows->where('source', 'Langsung')->count();
    $inheritedCount = $rows->where('source', 'Dari Role')->count();
    $rows = $rows->values()->all();
@endphp

<div
    x-data="{
        items: @js($rows),
        search: '',
        page: 1,
        pageSize: 8,
        get normalizedSearch() {
            return this.search.trim().toLowerCase();
        },
        get filteredRows() {
            if (!this.normalizedSearch) {
                return this.items;
            }

            return this.items.filter((row) => {
                return [row.name, row.module, row.description, row.source]
                    .join(' ')
                    .toLowerCase()
                    .includes(this.normalizedSearch);
            });
        },
        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredRows.length / this.pageSize));
        },
        get safePage() {
            return Math.min(this.page, this.totalPages);
        },
        get paginatedRows() {
            const start = (this.safePage - 1) * this.pageSize;
            return this.filteredRows.slice(start, start + this.pageSize);
        },
        get rangeStart() {
            return this.filteredRows.length === 0 ? 0 : ((this.safePage - 1) * this.pageSize) + 1;
        },
        get rangeEnd() {
            return Math.min(this.safePage * this.pageSize, this.filteredRows.length);
        },
        goToPage(nextPage) {
            this.page = Math.min(Math.max(nextPage, 1), this.totalPages);
        }
    }"
    x-cloak
    class="space-y-4"
>
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
        <p class="text-sm font-semibold text-gray-800">Ringkasan Akses</p>
        @if(count($rows) === 0)
            <p class="mt-1 text-sm text-gray-600">User ini belum memiliki permission aktif.</p>
        @else
            <p class="mt-1 text-sm text-gray-600">
                User ini memiliki {{ count($rows) }} permission aktif: {{ $directCount }} langsung dan {{ $inheritedCount }} dari role.
            </p>
        @endif
    </div>

    @if(count($rows) === 0)
        <p class="text-sm italic text-gray-500">Tidak ada permission yang dapat ditampilkan.</p>
    @else
        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="w-full lg:max-w-md">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="user-permission-search">Cari permission</label>
                    <input
                        id="user-permission-search"
                        type="search"
                        x-model.debounce.250ms="search"
                        @input="page = 1"
                        placeholder="Cari nama, modul, atau deskripsi"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                    />
                </div>
                <p class="text-sm text-gray-600" x-show="filteredRows.length > 0" x-cloak>
                    Menampilkan <span x-text="rangeStart"></span>-<span x-text="rangeEnd"></span> dari <span x-text="filteredRows.length"></span> permission.
                </p>
            </div>

            <p class="text-sm italic text-gray-500" x-show="filteredRows.length === 0 && items.length > 0" x-cloak>
                Tidak ada permission yang cocok dengan pencarian ini.
            </p>

            <div class="overflow-x-auto rounded-lg border border-gray-200" x-show="filteredRows.length > 0" x-cloak>
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="border-b border-gray-200 px-4 py-3">Permission</th>
                            <th class="border-b border-gray-200 px-4 py-3">Modul</th>
                            <th class="border-b border-gray-200 px-4 py-3">Deskripsi</th>
                            <th class="border-b border-gray-200 px-4 py-3">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-for="row in paginatedRows" :key="row.name">
                            <tr class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700" x-text="row.name"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-700" x-text="row.module"></td>
                                <td class="px-4 py-3 text-gray-700" x-text="row.description"></td>
                                <td class="px-4 py-3 text-gray-700" x-text="row.source"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 pt-3 sm:flex-row sm:items-center sm:justify-between" x-show="filteredRows.length > 0" x-cloak>
                <p class="text-sm text-gray-600">
                    Halaman <span x-text="safePage"></span> dari <span x-text="totalPages"></span>
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="goToPage(page - 1)"
                        :disabled="safePage === 1"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="goToPage(page + 1)"
                        :disabled="safePage === totalPages"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>