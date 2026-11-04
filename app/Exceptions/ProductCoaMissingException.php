<?php

namespace App\Exceptions;

/** Produk belum punya akun COA Persediaan / Penyerahan Barang, sehingga jurnal pengiriman DO tidak dapat dibuat. Pesan aman ditampilkan ke pengguna. */
class ProductCoaMissingException extends \DomainException
{
}
