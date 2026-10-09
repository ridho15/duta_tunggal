@php
    $deliveryOrders = $deliveryOrders ?? collect();
    $customers = collect();
    $addresses = collect();
    $soNumbers = collect();

    foreach ($deliveryOrders as $do) {
        foreach ($do->salesOrders as $so) {
            if ($so->customer) {
                $customers->push($so->customer);
            }
            $addr = filled($so->shipped_to) ? $so->shipped_to : ($so->customer?->address ?? null);
            if (filled($addr)) {
                $addresses->push(trim((string) $addr));
            }
            if (filled($so->so_number)) {
                $soNumbers->push($so->so_number);
            }
        }
    }

    $uniqueCustomers = $customers->unique('id')->values();
    $uniqueAddresses = $addresses->unique()->values();
    $uniqueSos = $soNumbers->unique()->values();
@endphp

@if ($deliveryOrders->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-xl bg-gray-50/80 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/80">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                Customer / Penerima
            </div>
            @if ($uniqueCustomers->isNotEmpty())
                @foreach ($uniqueCustomers as $c)
                    <div class="font-bold text-sm text-gray-900 dark:text-white">
                        {{ $c->name }} <span class="text-xs font-normal text-gray-500">({{ $c->code }})</span>
                    </div>
                    @if ($c->perusahaan && $c->perusahaan !== $c->name)
                        <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">{{ $c->perusahaan }}</div>
                    @endif
                    @if ($c->telepon)
                        <div class="text-xs text-gray-500">Telp: {{ $c->telepon }}</div>
                    @endif
                @endforeach
            @else
                <div class="text-xs text-gray-500 italic">Tidak ada data customer terhubung pada DO.</div>
            @endif

            <div class="mt-3">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sales Order Referensi:</span>
                <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 ml-1">
                    {{ $uniqueSos->isNotEmpty() ? $uniqueSos->implode(', ') : '-' }}
                </span>
            </div>
        </div>

        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                Alamat Tujuan Pengiriman
            </div>
            @if ($uniqueAddresses->isNotEmpty())
                <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed bg-white dark:bg-gray-900 p-2.5 rounded-lg border border-gray-200/80 dark:border-gray-700">
                    {{ $uniqueAddresses->implode(' | ') }}
                </div>
            @else
                <div class="text-xs text-gray-500 italic bg-white dark:bg-gray-900 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700">
                    Alamat pengiriman mengikuti alamat default customer atau diambil sendiri di cabang.
                </div>
            @endif

            <div class="mt-3 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                <span class="font-semibold">DO Tergabung:</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-300">
                    {{ $deliveryOrders->count() }} DO
                </span>
                <span>({{ $deliveryOrders->pluck('do_number')->implode(', ') }})</span>
            </div>
        </div>
    </div>
@endif
