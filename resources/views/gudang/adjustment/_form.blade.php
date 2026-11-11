@php
    $inputClass = 'w-full px-3 py-2 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition border-gray-200';
    $oldItems = old('items');
    $source = is_array($oldItems) ? array_values($oldItems) : $items;
    $labels = \App\Models\Product::query()->whereIn('id', collect($source)->pluck('product_id')->filter())->get(['id', 'sku', 'name'])
        ->mapWithKeys(fn ($p) => [$p->id => trim(($p->sku ? "[{$p->sku}] " : '').$p->name)]);
    $initial = collect($source)->map(fn ($i) => [
        'product_id' => $i['product_id'] ?? null,
        'product_label' => $i['product_label'] ?? ($labels[$i['product_id'] ?? 0] ?? ''),
        'rak_id' => $i['rak_id'] ?? '',
        'adjusted_qty' => $i['adjusted_qty'] ?? '',
        'unit_cost' => $i['unit_cost'] ?? 0,
        'notes' => $i['notes'] ?? '',
    ])->values();
@endphp

<div x-data="adjustmentForm({
        warehouseId: @js((string) old('warehouse_id', $adjustment->warehouse_id)),
        type: @js(old('adjustment_type', $adjustment->adjustment_type ?? 'increase')),
        items: @js($initial),
        urls: { products: @js(route('gudang.adjustment.products')), raks: @js(route('gudang.adjustment.raks')), stock: @js(route('gudang.adjustment.stock')) },
    })" x-init="init()" class="space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nomor</label>
            <div class="{{ $inputClass }} bg-gray-50 text-gray-600">{{ $adjustment->adjustment_number }}</div>
            <input type="hidden" name="adjustment_number" id="adjustment_number" value="{{ old('adjustment_number', $adjustment->adjustment_number) }}">
        </div>
        <div>
            <label for="adjustment_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal <span class="text-rose-500">*</span></label>
            <input id="adjustment_date" type="date" name="adjustment_date" required value="{{ old('adjustment_date', optional($adjustment->adjustment_date)->format('Y-m-d')) }}" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="warehouse_id" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Gudang <span class="text-rose-500">*</span></label>
            <select id="warehouse_id" name="warehouse_id" required x-model="warehouseId" @change="warehouseChanged()" class="{{ $inputClass }}">
                <option value="">Pilih gudang</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">({{ $warehouse->kode }}) {{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="adjustment_type" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tipe <span class="text-rose-500">*</span></label>
            <select id="adjustment_type" name="adjustment_type" required x-model="type" class="{{ $inputClass }}">
                <option value="increase">Penambahan stok (+)</option>
                <option value="decrease">Pengurangan stok (-)</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="reason" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alasan <span class="text-rose-500">*</span></label>
            <input id="reason" type="text" name="reason" required maxlength="255" value="{{ old('reason', $adjustment->reason) }}" placeholder="Contoh: barang rusak saat bongkar muat" class="{{ $inputClass }}">
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Catatan</label>
            <textarea id="notes" name="notes" rows="2" class="{{ $inputClass }}">{{ old('notes', $adjustment->notes) }}</textarea>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-gray-900">Item penyesuaian</h2>
            <button type="button" @click="addItem()" class="px-3 py-1.5 text-xs font-semibold text-[#15803d] border border-[#15803d]/40 hover:bg-[#f0fdf4] rounded-lg transition">Tambah item</button>
        </div>

        <p x-show="!warehouseId" class="text-xs text-gray-500 mb-3">Pilih gudang dulu agar qty saat ini dan rak dapat dimuat.</p>

        <div class="space-y-3">
            <template x-for="(item, i) in items" :key="item.key">
                <fieldset class="rounded-xl border border-gray-200 p-4 space-y-3">
                    <legend class="px-1 text-[11px] font-bold text-gray-500 uppercase tracking-wider" x-text="'Item ' + (i + 1)"></legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="relative">
                            <label :for="'q' + item.key" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Produk <span class="text-rose-500">*</span></label>
                            <input :id="'q' + item.key" type="text" autocomplete="off" x-model="item.label" @input.debounce.300ms="searchProducts(item)" @keydown.escape="item.results = []"
                                   placeholder="Ketik nama atau SKU" class="{{ $inputClass }}" :aria-invalid="!item.product_id && item.touched ? 'true' : 'false'">
                            <input type="hidden" :name="'items[' + i + '][product_id]'" :value="item.product_id">
                            <ul x-show="item.results.length" x-cloak class="absolute z-20 mt-1 w-full max-h-56 overflow-auto bg-white border border-gray-200 rounded-xl shadow-lg text-xs">
                                <template x-for="r in item.results" :key="r.id">
                                    <li><button type="button" @click="pick(item, r)" class="w-full text-left px-3 py-2 hover:bg-[#f0fdf4]" x-text="r.label"></button></li>
                                </template>
                            </ul>
                            <p x-show="item.empty" x-cloak class="text-[11px] text-gray-500 mt-1">Produk tidak ditemukan.</p>
                        </div>
                        <div>
                            <label :for="'r' + item.key" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Rak (opsional)</label>
                            <select :id="'r' + item.key" :name="'items[' + i + '][rak_id]'" x-model="item.rak_id" @change="loadStock(item)" class="{{ $inputClass }}">
                                <option value="">Tanpa rak (seluruh gudang)</option>
                                <template x-for="r in raks" :key="r.id"><option :value="r.id" x-text="r.name" :selected="String(r.id) === String(item.rak_id)"></option></template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <span class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Qty saat ini</span>
                            <div class="{{ $inputClass }} bg-gray-50 text-gray-700" x-text="item.product_id ? fmt(item.current) : '-'"></div>
                        </div>
                        <div>
                            <label :for="'a' + item.key" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Qty setelah <span class="text-rose-500">*</span></label>
                            <input :id="'a' + item.key" type="number" step="0.01" min="0" required :name="'items[' + i + '][adjusted_qty]'" x-model.number="item.adjusted_qty" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label :for="'c' + item.key" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Harga satuan</label>
                            <input :id="'c' + item.key" type="number" step="0.01" min="0" :name="'items[' + i + '][unit_cost]'" x-model.number="item.unit_cost" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <span class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Selisih</span>
                            <div class="{{ $inputClass }} bg-gray-50 font-semibold" :class="diff(item) === 0 ? 'text-gray-500' : (signOk(item) ? 'text-[#15803d]' : 'text-rose-600')">
                                <span x-text="(diff(item) > 0 ? '+' : '') + fmt(diff(item))"></span>
                                <span class="font-normal text-gray-500" x-text="' (Rp ' + fmt(diff(item) * (item.unit_cost || 0)) + ')'"></span>
                            </div>
                        </div>
                    </div>

                    <p x-show="item.product_id && diff(item) !== 0 && !signOk(item)" x-cloak class="text-xs text-rose-600"
                       x-text="type === 'increase' ? 'Untuk penambahan, qty setelah harus lebih besar dari qty saat ini.' : 'Untuk pengurangan, qty setelah harus lebih kecil dari qty saat ini.'"></p>

                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label :for="'n' + item.key" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Catatan item</label>
                            <input :id="'n' + item.key" type="text" :name="'items[' + i + '][notes]'" x-model="item.notes" class="{{ $inputClass }}">
                        </div>
                        <button type="button" @click="removeItem(i)" x-show="items.length > 1" class="px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition">Hapus item</button>
                    </div>
                </fieldset>
            </template>
        </div>

        <p class="text-xs text-gray-500 mt-3">Total nilai selisih: <span class="font-bold text-gray-800" x-text="'Rp ' + fmt(total())"></span></p>
    </div>
</div>

<script>
    function adjustmentForm(cfg) {
        let seq = 0;
        const blank = () => ({ key: ++seq, product_id: null, label: '', rak_id: '', adjusted_qty: '', unit_cost: 0, notes: '', current: 0, results: [], empty: false, touched: false });
        return {
            warehouseId: cfg.warehouseId, type: cfg.type, raks: [], items: [],
            fmt: (n) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(n) || 0),
            async init() {
                this.items = cfg.items.length ? cfg.items.map(i => ({ ...blank(), ...i, label: i.product_label, results: [] })) : [blank()];
                if (this.warehouseId) { await this.loadRaks(); this.items.forEach(i => i.product_id && this.loadStock(i)); }
            },
            addItem() { this.items.push(blank()); },
            removeItem(i) { this.items.splice(i, 1); },
            diff(item) { return Math.round(((Number(item.adjusted_qty) || 0) - (Number(item.current) || 0)) * 100) / 100; },
            signOk(item) { const d = this.diff(item); return this.type === 'increase' ? d > 0 : d < 0; },
            total() { return this.items.reduce((s, i) => s + this.diff(i) * (Number(i.unit_cost) || 0), 0); },
            async warehouseChanged() { this.items.forEach(i => { i.rak_id = ''; }); await this.loadRaks(); this.items.forEach(i => i.product_id && this.loadStock(i)); },
            async loadRaks() { this.raks = this.warehouseId ? await (await fetch(`${cfg.urls.raks}?warehouse_id=${this.warehouseId}`, { headers: { Accept: 'application/json' } })).json() : []; },
            async searchProducts(item) {
                item.product_id = null; item.empty = false; item.touched = true;
                const q = item.label.trim();
                if (q.length < 2) { item.results = []; return; }
                item.results = await (await fetch(`${cfg.urls.products}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })).json();
                item.empty = item.results.length === 0;
            },
            pick(item, r) { item.product_id = r.id; item.label = r.label; item.unit_cost = r.cost_price; item.results = []; this.loadStock(item); },
            async loadStock(item) {
                if (!item.product_id || !this.warehouseId) { item.current = 0; return; }
                const res = await fetch(`${cfg.urls.stock}?warehouse_id=${this.warehouseId}&product_id=${item.product_id}&rak_id=${item.rak_id || ''}`, { headers: { Accept: 'application/json' } });
                item.current = (await res.json()).qty;
                if (item.adjusted_qty === '') item.adjusted_qty = item.current;
            },
        };
    }
</script>
