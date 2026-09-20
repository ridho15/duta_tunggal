<?php

use App\Support\CustomerDuplicateFinder;

// Logika murni (tanpa DB) — tes Unit tidak mem-boot Laravel.

function dupRow(int $id, string $name, array $extra = []): array
{
    return array_merge(['id' => $id, 'name' => $name, 'perusahaan' => null, 'nik_npwp' => null, 'phone' => null, 'telephone' => null], $extra);
}

it('normalizeName menyamakan bentuk badan usaha, tanda baca, huruf besar, dan aksen', function () {
    foreach (['PT Daya Teknik Medika', 'DAYA TEKNIK MEDIKA, PT', 'P.T. Daya  Teknik-Medika', 'CV. Daya Teknik Medika', '  daya teknik medika  '] as $variant) {
        expect(CustomerDuplicateFinder::normalizeName($variant))->toBe('daya teknik medika');
    }

    expect(CustomerDuplicateFinder::normalizeName('Café Étoile UD'))->toBe('cafe etoile')
        ->and(CustomerDuplicateFinder::normalizeName(null))->toBe('')
        ->and(CustomerDuplicateFinder::normalizeName('PT'))->toBe('');
});

it('phoneKey memadankan 0812…, +62 812…, dan 62812…; nomor pendek diabaikan', function () {
    $keys = array_map([CustomerDuplicateFinder::class, 'phoneKey'], ['0812-3456-7890', '+62 812 3456 7890', '6281234567890', '081234567890']);

    expect(array_unique($keys))->toHaveCount(1)
        ->and(CustomerDuplicateFinder::phoneKey('12345'))->toBeNull()
        ->and(CustomerDuplicateFinder::phoneKey(null))->toBeNull();
});

it('varian nama yang sama dikelompokkan; nama yang hanya sebagian mirip TIDAK ikut', function () {
    $groups = (new CustomerDuplicateFinder)->groups([
        dupRow(1, 'PT Daya Teknik Medika'),
        dupRow(2, 'DAYA TEKNIK MEDIKA, PT'),
        dupRow(3, 'P.T. Daya Teknik Medika'),
        dupRow(4, 'CV Daya Teknik Medika'),
        dupRow(5, 'Daya Sentosa'),
        dupRow(6, 'Daya Teknik'),
        dupRow(7, 'Toko Maju Jaya'),
    ]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['ids'])->toBe([1, 2, 3, 4])
        ->and($groups[0]['reasons'])->toBe(['nama sama']);
});

it('urutan kata berbeda dikenali sebagai nama mirip; typo kecil juga', function () {
    $groups = (new CustomerDuplicateFinder)->groups([
        dupRow(1, 'Daya Teknik Medika'),
        dupRow(2, 'Teknik Daya Medika'),
        dupRow(3, 'Sumber Rejeki Abadi'),
        dupRow(4, 'Sumber Rejeki Abadii'),
    ]);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['ids'])->toBe([1, 2])
        ->and($groups[0]['reasons'][0])->toStartWith('nama mirip')
        ->and($groups[1]['ids'])->toBe([3, 4]);
});

it('NPWP/NIK yang sama mengelompokkan walau nama berbeda; nilai placeholder diabaikan', function () {
    $groups = (new CustomerDuplicateFinder)->groups([
        dupRow(1, 'Alpha Mandiri', ['nik_npwp' => '31.712.345.6-789.001']),
        dupRow(2, 'Beta Sejahtera', ['nik_npwp' => '3171234567890012']),   // 16 digit berbeda satu digit → BUKAN sama
        dupRow(3, 'Gamma Nusantara', ['nik_npwp' => '317123456789001']),    // 15 digit sama dengan #1
        dupRow(4, 'Delta Prima', ['nik_npwp' => '0000000000000000']),
        dupRow(5, 'Epsilon Karya', ['nik_npwp' => '0000000000000000']),
        dupRow(6, 'Zeta Utama', ['nik_npwp' => '-']),
        dupRow(7, 'Eta Sukses', ['nik_npwp' => '-']),
    ]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['ids'])->toBe([1, 3])
        ->and($groups[0]['reasons'])->toBe(['NPWP/NIK sama']);
});

it('telepon sama mengelompokkan; nomor yang dipakai banyak customer dianggap nomor bersama dan diabaikan', function () {
    $shared = ['phone' => '021-5550000'];
    $groups = (new CustomerDuplicateFinder)->groups([
        dupRow(1, 'Toko A Sentosa', ['phone' => '0812-3456-7890']),
        dupRow(2, 'Usaha B Makmur', ['telephone' => '+62 812 3456 7890']),
        dupRow(10, 'Cabang Satu', $shared), dupRow(11, 'Cabang Dua', $shared), dupRow(12, 'Cabang Tiga', $shared),
        dupRow(13, 'Cabang Empat', $shared), dupRow(14, 'Cabang Lima', $shared),
    ]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['ids'])->toBe([1, 2])
        ->and($groups[0]['reasons'])->toBe(['telepon sama']);
});

it('beberapa alasan pada grup yang sama digabung dan hasil terurut menurut id terkecil', function () {
    $groups = (new CustomerDuplicateFinder)->groups([
        dupRow(20, 'Zeta Global', ['nik_npwp' => '9999888877776666']),
        dupRow(21, 'Zeta Global', ['nik_npwp' => '9999888877776666']),
        dupRow(3, 'Alpha Bersama'),
        dupRow(4, 'Alpha Bersama'),
    ]);

    expect(array_column($groups, 'ids'))->toBe([[3, 4], [20, 21]])
        ->and($groups[1]['reasons'])->toContain('nama sama')->toContain('NPWP/NIK sama');
});

it('daftar kosong atau customer tunggal menghasilkan tanpa grup', function () {
    expect((new CustomerDuplicateFinder)->groups([]))->toBe([])
        ->and((new CustomerDuplicateFinder)->groups([dupRow(1, 'Sendiri Saja')]))->toBe([]);
});
