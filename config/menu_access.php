<?php

/*
|--------------------------------------------------------------------------
| Hak akses menu per peran (Spesifikasi UI/UX v1.1 Bagian 5 + menu_mapping.json)
|--------------------------------------------------------------------------
|
| `role_groups` memetakan kode peran di spesifikasi ke nama role Spatie yang
| ada di database. Satu user boleh punya beberapa role; aksesnya digabung.
|
| `menus` memetakan nama menu ke kode peran yang boleh MELIHAT menu
| dan MEMBUKA URL-nya. Menu yang tidak tercantum di sini tidak dibatasi oleh
| file ini (tetap dibatasi Policy/permission yang sudah ada).
|
| Pembatasan ini MENAMBAH, bukan mengganti, pengecekan Policy: user tetap harus
| punya permission "view any ..." agar halaman bisa dibuka.
*/

return [

    'role_groups' => [
        // Owner / Super Admin / Admin → semua menu
        'OWN' => ['Owner', 'Super Admin', 'Admin'],
        'SM'  => ['Sales Manager'],
        'SLS' => ['Sales', 'Customer Service'],
        'KSR' => ['Kasir'],
        'ADP' => ['Admin Pengiriman', 'Delivery Driver'],
        'GDG' => ['Admin Inventory', 'Inventory Manager', 'Warehouse Staff', 'Checker'],
        'PUR' => ['Purchasing', 'Purchasing Manager'],
        // Auditor ikut kelompok Finance untuk melihat; permission-nya tetap hanya "view any".
        'FIN' => ['Finance Manager', 'Accounting', 'Admin Keuangan', 'Auditor'],
        // Tim teknis: hanya menu Pengaturan pengguna.
        'IT'  => ['IT Support'],
    ],

    // Kode peran yang selalu boleh membuka semua menu.
    'super_codes' => ['OWN'],

    'menus' => [
        // Layar HP gudang (picking list). Kunci berupa nama menu, bukan kelas.
        'hp_kiriman' => ['OWN', 'GDG'],
    ],
];
