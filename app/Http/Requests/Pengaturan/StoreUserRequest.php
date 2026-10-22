<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'telepon' => 'nullable|string|max:50',
            'password' => 'required|string|min:6|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'manage_type' => 'required|array|min:1',
            'manage_type.*' => 'string|in:all,cabang,warehouse',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'posisi' => 'required|string|max:50',
            'status' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'Nama Depan',
            'last_name' => 'Nama Belakang',
            'username' => 'Username',
            'email' => 'Alamat Email',
            'telepon' => 'Nomor Telepon',
            'password' => 'Password',
            'roles' => 'Peran / Level',
            'manage_type' => 'Akses Kelola Cabang/Gudang',
            'cabang_id' => 'Cabang',
            'warehouse_id' => 'Gudang',
            'posisi' => 'Posisi / Jabatan',
            'status' => 'Status Pengguna',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Nama depan wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username ini sudah digunakan oleh akun lain.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'roles.required' => 'Pilih setidaknya satu peran (role) untuk pengguna ini.',
            'manage_type.required' => 'Pilih setidaknya satu cakupan kelola (Semua / Cabang / Gudang).',
            'posisi.required' => 'Posisi / jabatan kerja wajib diisi.',
        ];
    }
}
