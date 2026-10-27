@extends('layouts.app', ['title' => 'Edit Jadwal Pengiriman'])

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.schedules.index') }}" class="hover:text-gray-600">Jadwal Kirim & Armada</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('penjualan.schedules.show', $deliverySchedule->id) }}" class="hover:text-gray-600">{{ $deliverySchedule->schedule_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Edit</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Edit Jadwal: {{ $deliverySchedule->schedule_number }}</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Perbarui rute armada, waktu keberangkatan, atau sesuaikan alokasi muatan sebelum armada berangkat.
            </p>
        </div>

        <a
            href="{{ route('penjualan.schedules.show', $deliverySchedule->id) }}"
            class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl font-bold text-xs shadow-xs transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M19 12H5m7-7-7 7 7 7" stroke-width="2" stroke-linecap="round"></path>
            </svg>
            <span>Kembali ke Detail</span>
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                    <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"></line>
                </svg>
                <div class="space-y-1">
                    <div class="text-xs font-bold text-rose-800">Terdapat kesalahan pengisian formulir:</div>
                    <ul class="text-xs text-rose-700 list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.schedules.update', $deliverySchedule->id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Section 1: Informasi Jadwal & Cabang --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-extrabold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                Informasi Jadwal & Cabang
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Nomor Jadwal --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Jadwal</label>
                    <input
                        type="text"
                        name="schedule_number"
                        value="{{ old('schedule_number', $deliverySchedule->schedule_number) }}"
                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-semibold text-gray-800 focus:outline-none focus:border-[#15803d]"
                    >
                </div>

                {{-- Tanggal & Waktu Berangkat --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Waktu Keberangkatan <span class="text-red-500">*</span></label>
                    <input
                        type="datetime-local"
                        name="scheduled_date"
                        value="{{ old('scheduled_date', optional($deliverySchedule->scheduled_date)->format('Y-m-d\TH:i')) }}"
                        required
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cabang Operasional <span class="text-red-500">*</span></label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($c) => ['id' => $c->id, 'label' => $c->nama])->values()" :value="(old('cabang_id', $deliverySchedule->cabang_id)) ?: collect($cabangs)->first()?->id" :required="true" size="sm" />
                </div>
            </div>
        </div>

        {{-- Section 2: Metode Pengiriman & Alokasi Armada --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                    Metode Pengiriman & Alokasi Armada
                </h2>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openQuickDriverModal()" class="text-xs text-[#15803d] hover:underline font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Driver
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" onclick="openQuickVehicleModal()" class="text-xs text-[#15803d] hover:underline font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Kendaraan
                    </button>
                </div>
            </div>

            @php
                $currentMethod = old('delivery_method', $deliverySchedule->delivery_method ?? 'internal');
            @endphp

            {{-- Pilihan Metode Pengiriman --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Pilih Jenis Moda Pengiriman <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="relative flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition method-card">
                        <input type="radio" name="delivery_method" value="internal" {{ $currentMethod === 'internal' ? 'checked' : '' }} onchange="toggleDeliveryMethod('internal')" class="text-[#15803d] focus:ring-[#15803d]">
                        <div class="ml-3">
                            <span class="block text-xs font-bold text-gray-900">Internal (Armada PT)</span>
                            <span class="block text-[11px] text-gray-500">Driver & Truk resmi perusahaan</span>
                        </div>
                    </label>

                    <label class="relative flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition method-card">
                        <input type="radio" name="delivery_method" value="kurir_internal" {{ $currentMethod === 'kurir_internal' ? 'checked' : '' }} onchange="toggleDeliveryMethod('kurir_internal')" class="text-[#15803d] focus:ring-[#15803d]">
                        <div class="ml-3">
                            <span class="block text-xs font-bold text-gray-900">Kurir Internal</span>
                            <span class="block text-[11px] text-gray-500">Sepeda motor / kurir toko cepat</span>
                        </div>
                    </label>

                    <label class="relative flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition method-card">
                        <input type="radio" name="delivery_method" value="ekspedisi" {{ $currentMethod === 'ekspedisi' ? 'checked' : '' }} onchange="toggleDeliveryMethod('ekspedisi')" class="text-[#15803d] focus:ring-[#15803d]">
                        <div class="ml-3">
                            <span class="block text-xs font-bold text-gray-900">Ekspedisi Pihak Ketiga</span>
                            <span class="block text-[11px] text-gray-500">JNE, Dakota, Lalamove, Kargo</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Kolom Input Armada Internal --}}
            <div id="internal_fleet_inputs" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 {{ $currentMethod === 'ekspedisi' ? 'hidden' : '' }}">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Driver / Pengemudi <span class="text-red-500">*</span>
                    </label>
                    <x-searchable-select name="driver_id" id="driver_id_select" :options="collect($drivers)->map(fn ($d) => ['id' => $d->id, 'label' => trim($d->name . ($d->phone ? ' (' . $d->phone . ')' : ''))])->values()" :value="old('driver_id', $deliverySchedule->driver_id)" placeholder="-- Pilih Driver Master --" size="sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kendaraan / Nomor Polisi <span class="text-red-500">*</span>
                    </label>
                    <x-searchable-select name="vehicle_id" id="vehicle_id_select" :options="collect($vehicles)->map(fn ($v) => ['id' => $v->id, 'label' => $v->plate . ' (' . ($v->type ?? 'Armada') . ')' . ($v->capacity ? ' - Kap: ' . $v->capacity : '')])->values()" :value="old('vehicle_id', $deliverySchedule->vehicle_id)" placeholder="-- Pilih Kendaraan Master --" size="sm" />
                </div>
            </div>

            {{-- Kolom Input Ekspedisi / 3rd Party --}}
            <div id="ekspedisi_inputs" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 {{ $currentMethod === 'ekspedisi' ? '' : 'hidden' }}">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Driver / Ekspedisi <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="driver_name"
                        value="{{ old('driver_name', $deliverySchedule->driver_name) }}"
                        placeholder="Contoh: J&T Cargo / Pak Joko (Lalamove)"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Info Kendaraan / Jenis Truk</label>
                    <input
                        type="text"
                        name="vehicle_info"
                        value="{{ old('vehicle_info', $deliverySchedule->vehicle_info) }}"
                        placeholder="Contoh: GranMax Box B 9123 XYZ"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Resi / AWB (Opsional)</label>
                    <input
                        type="text"
                        name="tracking_number"
                        value="{{ old('tracking_number', $deliverySchedule->tracking_number) }}"
                        placeholder="Contoh: CGK88291024"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]"
                    >
                </div>
            </div>
        </div>

        {{-- Section 3: Pilih Muatan Dokumen (Surat Jalan & Delivery Order) --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                        Muatan Pengiriman (Surat Jalan & Delivery Order)
                    </h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        Centang Surat Jalan resmi atau DO yang diikutsertakan dalam jadwal ini.
                    </p>
                </div>
                <div class="text-xs font-bold text-[#15803d]">
                    <span id="selectedCountText">0</span> Dokumen Dipilih
                </div>
            </div>

            {{-- Tabs Surat Jalan vs DO --}}
            <div class="space-y-4">
                {{-- Daftar Surat Jalan --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-800 mb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="2"></path>
                            <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="2"></rect>
                            <path d="m9 14 2 2 4-4" stroke-width="2" stroke-linecap="round"></path>
                        </svg>
                        <span>Surat Jalan Resmi</span>
                    </h3>

                    @if($candidateSuratJalans->count() > 0)
                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl divide-y divide-gray-100">
                            @foreach($candidateSuratJalans as $sj)
                                @php
                                    $cust = $sj->deliveryOrder->flatMap->salesOrders->pluck('customer')->first();
                                    $doNumber = $sj->deliveryOrder->pluck('do_number')->implode(', ') ?: '-';
                                    $checked = in_array($sj->id, old('surat_jalan_ids', $attachedSjIds));
                                @endphp
                                <label class="flex items-center justify-between p-3 hover:bg-gray-50 cursor-pointer transition {{ $checked ? 'bg-emerald-50/40' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            name="surat_jalan_ids[]"
                                            value="{{ $sj->id }}"
                                            {{ $checked ? 'checked' : '' }}
                                            onchange="updateSelectedCount()"
                                            class="doc-checkbox text-[#15803d] focus:ring-[#15803d] rounded"
                                        >
                                        <div>
                                            <div class="font-bold text-xs text-gray-900">{{ $sj->sj_number }}</div>
                                            <div class="text-[11px] text-gray-500">
                                                DO: <span class="font-medium text-gray-700">{{ $doNumber }}</span>
                                                • Customer: <span class="font-semibold text-gray-800">{{ $cust->name ?? 'Pelanggan Umum' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right text-[11px] text-gray-400">
                                        {{ optional($sj->issued_at)->format('d/m/Y') }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-200 rounded-xl text-center text-xs text-gray-500">
                            Tidak ada Surat Jalan resmi lain yang tersedia.
                        </div>
                    @endif
                </div>

                {{-- Daftar Delivery Order --}}
                <div class="pt-2">
                    <h3 class="text-xs font-bold text-gray-800 mb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="2"></path>
                            <circle cx="7" cy="18" r="2" stroke-width="2"></circle>
                            <circle cx="17" cy="18" r="2" stroke-width="2"></circle>
                        </svg>
                        <span>Delivery Order (DO) Langsung</span>
                    </h3>

                    @if($candidateDeliveryOrders->count() > 0)
                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl divide-y divide-gray-100">
                            @foreach($candidateDeliveryOrders as $do)
                                @php
                                    $cust = $do->salesOrders->first()?->customer;
                                    $checked = in_array($do->id, old('delivery_order_ids', $attachedDoIds));
                                @endphp
                                <label class="flex items-center justify-between p-3 hover:bg-gray-50 cursor-pointer transition {{ $checked ? 'bg-blue-50/40' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            name="delivery_order_ids[]"
                                            value="{{ $do->id }}"
                                            {{ $checked ? 'checked' : '' }}
                                            onchange="updateSelectedCount()"
                                            class="doc-checkbox text-[#15803d] focus:ring-[#15803d] rounded"
                                        >
                                        <div>
                                            <div class="font-bold text-xs text-gray-900">{{ $do->do_number }}</div>
                                            <div class="text-[11px] text-gray-500">
                                                Customer: <span class="font-semibold text-gray-800">{{ $cust->name ?? 'Pelanggan' }}</span>
                                                • Gudang: <span class="font-medium text-gray-700">{{ $do->warehouse->name ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700">
                                            {{ $do->status_label ?? ucfirst($do->status) }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-200 rounded-xl text-center text-xs text-gray-500">
                            Tidak ada Delivery Order lain yang tersedia.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section 4: Catatan Pengiriman --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                Catatan Instruksi Driver & Pengiriman
            </h2>
            <textarea
                name="notes"
                rows="3"
                placeholder="Instruksi khusus pengantaran, rute jalan, jam buka gudang customer, atau kontak penerima di lokasi..."
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]"
            >{{ old('notes', $deliverySchedule->notes) }}</textarea>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('penjualan.schedules.show', $deliverySchedule->id) }}"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Simpan Perubahan Jadwal</span>
            </button>
        </div>
    </form>
</div>

{{-- Quick Add Driver Modal --}}
<div id="quickDriverModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-sm">Tambah Driver Baru</h3>
            <button type="button" onclick="closeQuickDriverModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Driver <span class="text-red-500">*</span></label>
                <input type="text" id="quick_driver_name" placeholder="Nama lengkap pengemudi" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Telepon / WhatsApp</label>
                <input type="text" id="quick_driver_phone" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">No. SIM / Lisensi</label>
                <input type="text" id="quick_driver_license" placeholder="SIM B1 / B2 Umum" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]">
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="closeQuickDriverModal()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">Batal</button>
            <button type="button" onclick="submitQuickDriver()" class="px-4 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold shadow-sm transition">Simpan Driver</button>
        </div>
    </div>
</div>

{{-- Quick Add Vehicle Modal --}}
<div id="quickVehicleModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-sm">Tambah Kendaraan Armada Baru</h3>
            <button type="button" onclick="closeQuickVehicleModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Plat Polisi <span class="text-red-500">*</span></label>
                <input type="text" id="quick_vehicle_plate" placeholder="B 1234 ABC" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs uppercase focus:outline-none focus:border-[#15803d]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Kendaraan</label>
                <select id="quick_vehicle_type" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d] bg-white">
                    <option value="Truck Engkel">Truck Engkel</option>
                    <option value="Truck CDD">Truck CDD (Double)</option>
                    <option value="Pickup">Pickup</option>
                    <option value="Box">Box</option>
                    <option value="Motor">Sepeda Motor</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Kapasitas Muatan</label>
                <input type="text" id="quick_vehicle_capacity" placeholder="Contoh: 4 Ton / 8 m3" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d]">
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="closeQuickVehicleModal()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">Batal</button>
            <button type="button" onclick="submitQuickVehicle()" class="px-4 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold shadow-sm transition">Simpan Kendaraan</button>
        </div>
    </div>
</div>

<script>
function toggleDeliveryMethod(method) {
    const internalBox = document.getElementById('internal_fleet_inputs');
    const ekspedisiBox = document.getElementById('ekspedisi_inputs');

    if (method === 'ekspedisi') {
        internalBox.classList.add('hidden');
        ekspedisiBox.classList.remove('hidden');
    } else {
        internalBox.classList.remove('hidden');
        ekspedisiBox.classList.add('hidden');
    }
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.doc-checkbox:checked').length;
    document.getElementById('selectedCountText').innerText = count;
}

// Quick Driver Modal
function openQuickDriverModal() {
    document.getElementById('quickDriverModal').classList.remove('hidden');
}
function closeQuickDriverModal() {
    document.getElementById('quickDriverModal').classList.add('hidden');
}
function submitQuickDriver() {
    const name = document.getElementById('quick_driver_name').value.trim();
    const phone = document.getElementById('quick_driver_phone').value.trim();
    const license = document.getElementById('quick_driver_license').value.trim();

    if (!name) {
        alert('Nama driver wajib diisi.');
        return;
    }

    fetch('{{ route("penjualan.schedules.quickDriver") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ name, phone, license })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.driver) {
            const cmp = Alpine.$data(document.getElementById('driver_id_select').closest('[x-data]'));
            cmp.options.push({ id: String(data.driver.id), label: data.driver.name + (data.driver.phone ? ' (' + data.driver.phone + ')' : '') });
            cmp.selected = String(data.driver.id);
            closeQuickDriverModal();
            document.getElementById('quick_driver_name').value = '';
            document.getElementById('quick_driver_phone').value = '';
            document.getElementById('quick_driver_license').value = '';
        } else {
            alert(data.message || 'Gagal menyimpan driver.');
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
    });
}

// Quick Vehicle Modal
function openQuickVehicleModal() {
    document.getElementById('quickVehicleModal').classList.remove('hidden');
}
function closeQuickVehicleModal() {
    document.getElementById('quickVehicleModal').classList.add('hidden');
}
function submitQuickVehicle() {
    const plate = document.getElementById('quick_vehicle_plate').value.trim();
    const type = document.getElementById('quick_vehicle_type').value;
    const capacity = document.getElementById('quick_vehicle_capacity').value.trim();

    if (!plate) {
        alert('Nomor plat kendaraan wajib diisi.');
        return;
    }

    fetch('{{ route("penjualan.schedules.quickVehicle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ plate, type, capacity })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.vehicle) {
            const cmp = Alpine.$data(document.getElementById('vehicle_id_select').closest('[x-data]'));
            cmp.options.push({ id: String(data.vehicle.id), label: data.vehicle.plate + ' (' + (data.vehicle.type || 'Armada') + ')' });
            cmp.selected = String(data.vehicle.id);
            closeQuickVehicleModal();
            document.getElementById('quick_vehicle_plate').value = '';
            document.getElementById('quick_vehicle_capacity').value = '';
        } else {
            alert(data.message || 'Gagal menyimpan kendaraan.');
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>
@endsection
