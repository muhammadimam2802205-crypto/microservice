<?php

namespace App\Exceptions;

use RuntimeException;

class OrderNotPayableException extends RuntimeException
{
    /**
     * Hanya pesanan berstatus PENDING yang boleh dibayar.
     */
    public function __construct(string $status)
    {
        parent::__construct(
            "Pesanan berstatus {$status}, hanya pesanan PENDING yang dapat dibayar"
        );
    }
}
