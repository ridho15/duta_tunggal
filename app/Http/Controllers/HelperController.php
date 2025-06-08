<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HelperController extends Controller
{
    public static function saveSignatureImage($base64Image)
    {
        $image_parts = explode(";base64,", $base64Image);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1] ?? 'png';
        $image_base64 = base64_decode($image_parts[1]);

        $fileName = 'signature_' . uniqid() . '.' . $image_type;
        $filePath = 'signatures/' . $fileName;

        // Simpan ke public/storage/signatures/
        Storage::disk('public')->put($filePath, $image_base64);

        return 'storage/' . $filePath;
    }
}
