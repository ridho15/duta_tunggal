<?php

namespace App\Exceptions;

class ClosedPeriodException extends \RuntimeException
{
    public function __construct(string $message = 'Periode akuntansi sudah ditutup.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
