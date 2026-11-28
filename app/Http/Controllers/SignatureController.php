<?php

namespace App\Http\Controllers;

use App\Services\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/** Tanda tangan digital milik user yang sedang masuk (gambar di kanvas atau unggah PNG/JPG). */
class SignatureController extends Controller
{
    public function __construct(private SignatureService $signatures) {}

    public function edit(): View
    {
        $path = $this->signatures->userSignaturePath(Auth::user());

        // Pratinjau sebagai data URL: tidak bergantung pada `php artisan storage:link` di server
        $current = $path ? 'data:' . (Storage::disk('public')->mimeType($path) ?: 'image/png') . ';base64,' . base64_encode(Storage::disk('public')->get($path)) : null;

        return view('settings.signature', ['current' => $current]);
    }

    /** Gambar tanda tangan pada dokumen (hanya untuk pengguna yang masuk; tidak butuh storage:link). */
    public function image(\App\Models\DocumentSignature $signature): \Symfony\Component\HttpFoundation\Response
    {
        $path = $signature->imagePath();
        abort_unless($path !== null, 404, 'Gambar tanda tangan tidak ditemukan.');

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'signature_data' => ['nullable', 'string', 'max:800000'],
            'signature_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:512'],
        ], ['signature_file.max' => 'Ukuran gambar maksimal 512 KB.', 'signature_file.mimes' => 'Gunakan gambar PNG atau JPG.']);

        try {
            if ($request->hasFile('signature_file')) {
                $this->signatures->saveFromUpload(Auth::user(), $request->file('signature_file'));
            } elseif ($request->filled('signature_data')) {
                $this->signatures->saveFromDataUrl(Auth::user(), (string) $request->input('signature_data'));
            } else {
                return back()->with('error', 'Belum ada tanda tangan. Solusi: gambar di kotak atau unggah gambar tanda tangan.');
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('signature.edit')->with('success', 'Tanda tangan tersimpan. Dokumen yang Anda ajukan / setujui / bayar berikutnya memakai tanda tangan ini.');
    }
}
