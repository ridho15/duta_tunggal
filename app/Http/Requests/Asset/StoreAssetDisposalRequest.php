<?php

namespace App\Http\Requests\Asset;

use App\Models\Asset;
use App\Models\AssetDisposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAssetDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sale_price') && is_string($this->sale_price)) {
            $cleaned = preg_replace('/[^\d]/', '', $this->sale_price);
            $this->merge([
                'sale_price' => $cleaned !== '' ? (float) $cleaned : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'exists:assets,id'],
            'disposal_date' => ['required', 'date'],
            'disposal_type' => ['required', 'in:sale,scrap,donation,theft,other'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'disposal_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $assetId = $this->input('asset_id');
            $disposalType = $this->input('disposal_type');
            $salePrice = $this->input('sale_price');

            if ($assetId) {
                $asset = Asset::find($assetId);

                if (!$asset) {
                    $validator->errors()->add('asset_id', 'Aset tidak ditemukan.');
                    return;
                }

                if ($asset->status !== 'active') {
                    $validator->errors()->add('asset_id', "Hanya aset dengan status 'Aktif' yang dapat dilepas/didisposal.");
                }

                // Cek apakah aset sudah memiliki disposal yang aktif / tercatat
                $hasDisposal = AssetDisposal::where('asset_id', $assetId)
                    ->whereIn('status', ['pending', 'completed'])
                    ->exists();

                if ($hasDisposal) {
                    $validator->errors()->add('asset_id', 'Aset ini sudah memiliki rekaman pelepasan (disposal) dan tidak dapat dilepas ganda.');
                }
            }

            if ($disposalType === 'sale') {
                if ($salePrice === null || $salePrice <= 0) {
                    $validator->errors()->add('sale_price', 'Harga jual pelepasan wajib diisi dan harus lebih besar dari Rp 0 untuk tipe Penjualan (Sale).');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Pilih aset tetap yang akan dilepas.',
            'asset_id.exists' => 'Aset yang dipilih tidak valid.',
            'disposal_date.required' => 'Tanggal pelepasan wajib diisi.',
            'disposal_date.date' => 'Format tanggal pelepasan tidak valid.',
            'disposal_type.required' => 'Pilih jenis pelepasan aset.',
            'disposal_type.in' => 'Jenis pelepasan tidak valid.',
            'sale_price.numeric' => 'Nominal harga jual harus berupa angka.',
            'sale_price.min' => 'Harga jual tidak boleh negatif.',
            'notes.max' => 'Catatan keterangan maksimal 1000 karakter.',
            'disposal_document.mimes' => 'Dokumen Berita Acara harus berupa file PDF atau gambar (JPG, JPEG, PNG).',
            'disposal_document.max' => 'Ukuran dokumen Berita Acara maksimal 5 MB.',
        ];
    }
}
