<?php

namespace App\Http\Requests\Asset;

use App\Models\Asset;
use App\Models\AssetTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAssetTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'exists:assets,id'],
            'to_cabang_id' => ['required', 'exists:cabangs,id'],
            'transfer_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'transfer_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $assetId = $this->input('asset_id');
            $toCabangId = $this->input('to_cabang_id');

            if ($assetId) {
                $asset = Asset::find($assetId);

                if (!$asset) {
                    $validator->errors()->add('asset_id', 'Aset tidak ditemukan.');
                    return;
                }

                if ($asset->status !== 'active') {
                    $validator->errors()->add('asset_id', "Hanya aset dengan status 'Aktif' yang dapat diajukan untuk mutasi cabang.");
                }

                // Cek apakah aset sudah dalam proses transfer
                $pendingTransfer = AssetTransfer::where('asset_id', $assetId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists();

                if ($pendingTransfer) {
                    $validator->errors()->add('asset_id', 'Aset ini sedang dalam proses transfer dan belum selesai.');
                }

                // Cek cabang tujuan berbeda dengan cabang asal
                if ($toCabangId && (int) $toCabangId === (int) $asset->cabang_id) {
                    $validator->errors()->add('to_cabang_id', 'Cabang tujuan harus berbeda dengan cabang lokasi aset saat ini.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Pilih aset tetap yang akan dimutasi.',
            'asset_id.exists' => 'Aset yang dipilih tidak valid.',
            'to_cabang_id.required' => 'Pilih cabang tujuan mutasi.',
            'to_cabang_id.exists' => 'Cabang tujuan tidak valid.',
            'transfer_date.required' => 'Tanggal rencana mutasi transfer wajib diisi.',
            'transfer_date.date' => 'Format tanggal transfer tidak valid.',
            'reason.required' => 'Alasan mutasi aset wajib diisi.',
            'reason.min' => 'Alasan mutasi minimal 5 karakter.',
            'reason.max' => 'Alasan mutasi maksimal 1000 karakter.',
            'transfer_document.mimes' => 'Dokumen lampiran harus berupa file PDF atau gambar (JPG, JPEG, PNG).',
            'transfer_document.max' => 'Ukuran dokumen lampiran maksimal 5 MB.',
        ];
    }
}
