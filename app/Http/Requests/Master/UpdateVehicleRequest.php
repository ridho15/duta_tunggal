<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicle = $this->route('kendaraan') ?? $this->route('vehicle');
        $vehicleId = is_object($vehicle) ? $vehicle->id : $vehicle;

        return [
            'plate' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'plate')->ignore($vehicleId),
            ],
            'type' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'string', 'max:255'],
            'cabang_id' => ['required', 'exists:cabangs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required' => 'Nomor plat kendaraan wajib diisi.',
            'plate.unique' => 'Nomor plat kendaraan sudah digunakan oleh armada lain.',
            'type.required' => 'Jenis / tipe kendaraan wajib diisi.',
            'capacity.required' => 'Kapasitas muatan kendaraan wajib diisi.',
            'cabang_id.required' => 'Cabang operasional wajib dipilih.',
            'cabang_id.exists' => 'Cabang yang dipilih tidak valid.',
        ];
    }
}
