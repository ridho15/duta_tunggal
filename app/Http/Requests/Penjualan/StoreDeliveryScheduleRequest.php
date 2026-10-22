<?php

namespace App\Http\Requests\Penjualan;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_number' => 'nullable|string|max:255|unique:delivery_schedules,schedule_number',
            'scheduled_date' => 'required',
            'delivery_method' => 'required|in:internal,kurir_internal,ekspedisi',
            'driver_id' => 'required_if:delivery_method,internal,kurir_internal|nullable|exists:drivers,id',
            'vehicle_id' => 'required_if:delivery_method,internal,kurir_internal|nullable|exists:vehicles,id',
            'driver_name' => 'required_if:delivery_method,ekspedisi|nullable|string|max:255',
            'vehicle_info' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'cabang_id' => 'required|exists:cabangs,id',
            'surat_jalan_ids' => 'nullable|array',
            'surat_jalan_ids.*' => 'exists:surat_jalans,id',
            'delivery_order_ids' => 'nullable|array',
            'delivery_order_ids.*' => 'exists:delivery_orders,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sj = $this->input('surat_jalan_ids', []);
            $do = $this->input('delivery_order_ids', []);
            if (empty($sj) && empty($do)) {
                $validator->errors()->add('surat_jalan_ids', 'Pilih setidaknya satu Surat Jalan atau Delivery Order untuk jadwal ini.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'schedule_number' => 'Nomor Jadwal',
            'scheduled_date' => 'Tanggal & Waktu Keberangkatan',
            'delivery_method' => 'Metode Pengiriman',
            'driver_id' => 'Driver / Pengemudi',
            'vehicle_id' => 'Armada Kendaraan',
            'driver_name' => 'Nama Driver / Ekspedisi',
            'vehicle_info' => 'Informasi Kendaraan',
            'tracking_number' => 'Nomor Resi',
            'cabang_id' => 'Cabang',
            'surat_jalan_ids' => 'Surat Jalan',
            'delivery_order_ids' => 'Delivery Order',
            'notes' => 'Catatan Pengiriman',
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_date.required' => 'Tanggal dan waktu keberangkatan wajib diisi.',
            'delivery_method.required' => 'Metode pengiriman wajib dipilih.',
            'driver_id.required_if' => 'Driver wajib dipilih untuk pengiriman internal.',
            'vehicle_id.required_if' => 'Kendaraan wajib dipilih untuk pengiriman internal.',
            'driver_name.required_if' => 'Nama driver / ekspedisi wajib diisi untuk pengiriman via ekspedisi.',
            'cabang_id.required' => 'Cabang pengiriman wajib dipilih.',
        ];
    }
}
