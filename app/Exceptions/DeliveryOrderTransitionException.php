<?php

namespace App\Exceptions;

/**
 * Transisi status Delivery Order ditolak (T2.3): di luar matriks, alasan wajib kosong, atau stok fisik kurang (D15).
 * Pesan berbahasa Indonesia dan aman ditampilkan langsung ke pengguna.
 */
class DeliveryOrderTransitionException extends \DomainException
{
    /** @param  array<int, array{product: string, warehouse: string, needed: float, physical: float}>  $shortages */
    public function __construct(string $message, public readonly array $shortages = [])
    {
        parent::__construct($message);
    }
}
