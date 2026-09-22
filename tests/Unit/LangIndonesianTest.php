<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;

// Tes Unit tidak mem-boot Laravel: translator dirakit manual dari folder lang/ proyek dan bawaan framework.
function idTranslator(): Translator
{
    $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'id');
    $translator->setFallback('id');

    return $translator;
}

function frameworkLang(string $group): array
{
    return require dirname(__DIR__, 2)."/vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$group}.php";
}

/** Ratakan kunci bertingkat: ['between' => ['numeric' => x]] → ['between.numeric']. */
function flattenKeys(array $lines, string $prefix = ''): array
{
    $keys = [];
    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        is_array($value) ? $keys = array_merge($keys, flattenKeys($value, $path)) : $keys[] = $path;
    }

    return $keys;
}

it('setiap kunci bawaan Laravel tersedia dalam bahasa Indonesia dan bukan kunci mentah', function (string $group) {
    $translator = idTranslator();

    $missing = [];
    foreach (flattenKeys(frameworkLang($group)) as $key) {
        // 'custom' dan 'attributes' adalah tempat kustomisasi, bukan pesan.
        if (in_array(explode('.', $key)[0], ['custom', 'attributes'], true)) {
            continue;
        }

        $line = $translator->get("{$group}.{$key}");
        if ($line === "{$group}.{$key}" || trim((string) $line) === '') {
            $missing[] = "{$group}.{$key}";
        }
    }

    expect($missing)->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('pesan Indonesia tidak sama dengan teks Inggris bawaan (benar-benar diterjemahkan)', function (string $group) {
    $translator = idTranslator();
    $english = frameworkLang($group);

    $untranslated = [];
    foreach (flattenKeys($english) as $key) {
        if (in_array(explode('.', $key)[0], ['custom', 'attributes'], true)) {
            continue;
        }
        $en = data_get($english, $key);
        if ($translator->get("{$group}.{$key}") === $en) {
            $untranslated[] = "{$group}.{$key}";
        }
    }

    expect($untranslated)->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('validator menghasilkan pesan Indonesia lengkap dengan nama atribut', function () {
    $validator = (new ValidatorFactory(idTranslator()))->make(
        ['customer_id' => null, 'quantity' => 'abc', 'email' => 'bukan-email', 'kode' => 'x'],
        ['customer_id' => 'required', 'quantity' => 'numeric|min:1', 'email' => 'email', 'kode' => 'min:3']
    );

    $errors = $validator->errors();

    expect($errors->first('customer_id'))->toBe('Isian customer wajib diisi.')
        ->and($errors->first('quantity'))->toBe('Isian kuantitas harus berupa angka.')
        ->and($errors->first('email'))->toBe('Isian email harus berupa alamat email yang valid.')
        ->and($errors->first('kode'))->toBe('Isian kode minimal 3 karakter.');
});

it('pesan berparameter dan bertingkat (between, size, max) terisi', function () {
    $translator = idTranslator();

    expect($translator->get('validation.between.numeric', ['attribute' => 'diskon', 'min' => 0, 'max' => 100]))
        ->toBe('Isian diskon harus bernilai antara 0 dan 100.')
        ->and($translator->get('validation.max.string', ['attribute' => 'nama', 'max' => 255]))
        ->toBe('Isian nama tidak boleh lebih dari 255 karakter.')
        ->and($translator->get('auth.throttle', ['seconds' => 30]))
        ->toBe('Terlalu banyak percobaan masuk. Silakan coba lagi dalam 30 detik.');
});

it('atribut kustom penjualan tersedia', function () {
    $attributes = idTranslator()->get('validation.attributes');

    expect($attributes)->toBeArray()
        ->and($attributes['tax_invoice_number'])->toBe('nomor faktur pajak')
        ->and($attributes['payment_reference'])->toBe('nomor referensi pembayaran')
        ->and($attributes['kredit_limit'])->toBe('limit kredit');
});
