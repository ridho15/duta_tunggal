{{--
    Checklist akses dipakai halaman Peran (mode "role") dan Akses User (mode "user").
    Variabel: $catalog, $mode, $readonly; role: $checked; user: $roleKeys, $overrides.
--}}
@php
    $mode = $mode ?? 'role';
    $readonly = $readonly ?? false;
    $crudActions = ['view' => 'Lihat (menu)', 'create' => 'Tambah', 'update' => 'Ubah', 'delete' => 'Hapus'];
@endphp

<div
    x-data="accessChecklist({
        catalog: @js($catalog),
        mode: @js($mode),
        readonly: @js($readonly),
        checked: @js(array_values($checked ?? [])),
        roleKeys: @js(array_values($roleKeys ?? [])),
        overrides: @js((object) ($overrides ?? [])),
    })"
    class="space-y-4"
>
    {{-- Alat bantu: cari halaman, ringkasan, centang massal --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <label class="flex-1 min-w-0">
            <span class="block text-xs font-semibold text-gray-600 mb-1">Cari halaman atau tombol</span>
            <input
                type="search"
                x-model.debounce.150ms="search"
                placeholder="Contoh: invoice, kirim, approve"
                class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none"
            >
        </label>
        <div class="text-sm text-gray-700 lg:px-3" aria-live="polite">
            <span class="font-bold text-gray-900" x-text="totalAllowed()"></span>
            dari <span x-text="allKeys.length"></span> akses diizinkan
            <template x-if="mode === 'user'">
                <span class="block text-xs text-gray-600">
                    <span class="font-semibold text-[#15803d]" x-text="'+' + addedCount() + ' ditambah'"></span>,
                    <span class="font-semibold text-red-700" x-text="'-' + revokedCount() + ' dicabut'"></span> khusus user ini
                </span>
            </template>
        </div>
        <div class="flex flex-wrap gap-2" x-show="!readonly">
            <button type="button" @click="setAll(true)" class="cursor-pointer min-h-11 px-3 py-2 rounded-xl border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                Centang semua
            </button>
            <button type="button" @click="setAll(false)" class="cursor-pointer min-h-11 px-3 py-2 rounded-xl border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                Kosongkan semua
            </button>
            <template x-if="mode === 'user'">
                <button type="button" @click="resetToRole()" class="cursor-pointer min-h-11 px-3 py-2 rounded-xl border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                    Samakan dengan peran
                </button>
            </template>
        </div>
    </div>

    @if ($mode === 'user')
        <p class="text-xs text-gray-700 flex flex-wrap gap-x-4 gap-y-1">
            <span><span class="inline-block px-1.5 rounded bg-gray-100 border border-gray-300 font-semibold">Peran</span> ikut peran</span>
            <span><span class="inline-block px-1.5 rounded bg-[#dcfce7] border border-[#86efac] text-[#14532d] font-semibold">+ Ditambah</span> khusus user ini</span>
            <span><span class="inline-block px-1.5 rounded bg-red-50 border border-red-300 text-red-800 font-semibold line-through">Dicabut</span> peran punya, user ini tidak</span>
        </p>
    @endif

    {{-- Status kosong pencarian --}}
    <div x-show="visibleSections().length === 0" x-cloak class="bg-white rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-700">
        Tidak ada halaman atau tombol yang cocok dengan "<span class="font-semibold" x-text="search"></span>".
        <button type="button" @click="search = ''" class="cursor-pointer ml-1 font-bold text-[#15803d] underline underline-offset-2 hover:text-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40 rounded">Tampilkan semua</button>
    </div>

    <template x-for="section in visibleSections()" :key="section.key">
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden" :aria-labelledby="'sec-' + section.key">
            <header class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h2 :id="'sec-' + section.key" class="text-sm font-bold text-gray-900">
                    <span x-text="section.label"></span>
                    <span class="ml-2 text-xs font-semibold text-gray-600" x-text="sectionAllowed(section) + '/' + sectionTotal(section)"></span>
                    <span x-show="section.note" class="block text-xs font-semibold text-amber-800 mt-0.5" x-text="section.note"></span>
                </h2>
                <div class="flex gap-2" x-show="!readonly">
                    <button type="button" @click="setSection(section, true)" class="cursor-pointer min-h-9 px-2.5 py-1.5 rounded-lg text-xs font-bold text-[#15803d] hover:bg-[#dcfce7] active:bg-[#bbf7d0] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40" :aria-label="'Centang semua di ' + section.label">Centang semua</button>
                    <button type="button" @click="setSection(section, false)" class="cursor-pointer min-h-9 px-2.5 py-1.5 rounded-lg text-xs font-bold text-gray-700 hover:bg-gray-200 active:bg-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400" :aria-label="'Kosongkan semua di ' + section.label">Kosongkan</button>
                </div>
            </header>

            <ul class="divide-y divide-gray-100">
                <template x-for="page in visiblePages(section)" :key="page.key">
                    <li class="px-4 py-3 flex flex-col md:flex-row md:items-start gap-2 md:gap-4">
                        <div class="md:w-56 shrink-0 flex items-start justify-between md:block gap-2">
                            <p class="text-sm font-bold text-gray-900" x-text="page.label"></p>
                            <button type="button" x-show="!readonly" @click="setPage(page, !pageAll(page))"
                                class="cursor-pointer text-xs font-semibold text-[#15803d] underline underline-offset-2 hover:text-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40 rounded"
                                :aria-label="(pageAll(page) ? 'Kosongkan semua aksi ' : 'Centang semua aksi ') + page.label"
                                x-text="pageAll(page) ? 'Kosongkan baris' : 'Centang baris'"></button>
                        </div>
                        <div class="flex-1 flex flex-wrap gap-2">
                            <template x-for="act in page.actions" :key="act.key">
                                <label
                                    class="inline-flex items-center gap-2 min-h-9 px-2.5 py-1.5 rounded-lg border text-xs font-semibold select-none"
                                    :class="[chipClass(act.key), readonly ? 'cursor-not-allowed' : 'cursor-pointer', act.action === 'view' ? 'md:min-w-32' : '']"
                                >
                                    <input type="checkbox"
                                        class="h-4 w-4 rounded border-gray-400 text-[#15803d] focus:ring-2 focus:ring-[#15803d]/40 disabled:cursor-not-allowed"
                                        :checked="isAllowed(act.key)"
                                        :disabled="readonly"
                                        @change="toggle(page, act.key, $event.target.checked)">
                                    <span x-text="act.label" :class="state(act.key) === 'revoked' ? 'line-through' : ''"></span>
                                    <template x-if="mode === 'user' && state(act.key) === 'added'"><span class="text-[10px] font-bold">+ Ditambah</span></template>
                                    <template x-if="mode === 'user' && state(act.key) === 'revoked'"><span class="text-[10px] font-bold">Dicabut</span></template>
                                </label>
                            </template>
                        </div>
                    </li>
                </template>
            </ul>
        </section>
    </template>

    {{-- Nilai yang dikirim saat Simpan --}}
    <template x-if="mode === 'role'">
        <div><template x-for="k in checkedList()" :key="k"><input type="hidden" name="keys[]" :value="k"></template></div>
    </template>
    <template x-if="mode === 'user'">
        <div>
            <template x-for="k in overrideList(true)" :key="'a' + k"><input type="hidden" name="add[]" :value="k"></template>
            <template x-for="k in overrideList(false)" :key="'r' + k"><input type="hidden" name="revoke[]" :value="k"></template>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            function accessChecklist(cfg) {
                const allKeys = cfg.catalog.flatMap(s => s.pages.flatMap(p => p.actions.map(a => a.key)));
                return {
                    ...cfg,
                    allKeys,
                    search: '',
                    checkedSet: Object.fromEntries(cfg.checked.map(k => [k, true])),
                    roleSet: Object.fromEntries(cfg.roleKeys.map(k => [k, true])),
                    over: { ...cfg.overrides },

                    isAllowed(k) {
                        if (this.mode === 'role') return !!this.checkedSet[k];
                        return k in this.over ? !!this.over[k] : !!this.roleSet[k];
                    },
                    state(k) {
                        if (this.mode === 'role') return this.checkedSet[k] ? 'on' : 'off';
                        const inRole = !!this.roleSet[k];
                        if (!(k in this.over)) return inRole ? 'role' : 'off';
                        return this.over[k] ? 'added' : 'revoked';
                    },
                    chipClass(k) {
                        return {
                            on: 'bg-[#f0fdf4] border-[#86efac] text-[#14532d]',
                            role: 'bg-gray-50 border-gray-300 text-gray-900',
                            added: 'bg-[#dcfce7] border-[#15803d] text-[#14532d]',
                            revoked: 'bg-red-50 border-red-300 text-red-800',
                            off: 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50',
                        }[this.state(k)];
                    },
                    set(k, value) {
                        if (this.mode === 'role') {
                            if (value) this.checkedSet[k] = true; else delete this.checkedSet[k];
                            return;
                        }
                        if (value === !!this.roleSet[k]) delete this.over[k]; else this.over[k] = value;
                    },
                    // Aksi lain butuh "Lihat": mencentang aksi ikut mencentang Lihat; melepas Lihat melepas semua aksi baris itu.
                    toggle(page, k, value) {
                        this.set(k, value);
                        const viewKey = page.key + '.view';
                        if (value && k !== viewKey) this.set(viewKey, true);
                        if (!value && k === viewKey) page.actions.forEach(a => this.set(a.key, false));
                    },
                    setPage(page, value) { page.actions.forEach(a => this.set(a.key, value)); },
                    pageAll(page) { return page.actions.every(a => this.isAllowed(a.key)); },
                    setSection(section, value) { this.visiblePages(section).forEach(p => this.setPage(p, value)); },
                    setAll(value) { this.visibleSections().forEach(s => this.setSection(s, value)); },
                    resetToRole() { this.over = {}; },

                    matches(page) {
                        const q = this.search.trim().toLowerCase();
                        if (!q) return true;
                        return page.label.toLowerCase().includes(q) || page.actions.some(a => a.label.toLowerCase().includes(q));
                    },
                    visiblePages(section) { return section.pages.filter(p => this.matches(p)); },
                    visibleSections() { return this.catalog.filter(s => this.visiblePages(s).length > 0); },
                    sectionTotal(section) { return section.pages.reduce((n, p) => n + p.actions.length, 0); },
                    sectionAllowed(section) { return section.pages.reduce((n, p) => n + p.actions.filter(a => this.isAllowed(a.key)).length, 0); },
                    totalAllowed() { return this.allKeys.filter(k => this.isAllowed(k)).length; },
                    addedCount() { return Object.values(this.over).filter(v => v).length; },
                    revokedCount() { return Object.values(this.over).filter(v => !v).length; },
                    checkedList() { return Object.keys(this.checkedSet); },
                    overrideList(value) { return Object.keys(this.over).filter(k => !!this.over[k] === value); },
                };
            }
        </script>
    @endpush
@endonce
