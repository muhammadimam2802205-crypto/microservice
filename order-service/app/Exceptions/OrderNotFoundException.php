<?php

namespace App\Exceptions;

use RuntimeException;

class OrderNotFoundException extends RuntimeException
{
    /**
     * Pesanan dengan id tertentu tidak ada di database.
     */
    public function __construct(int $id)
    {
        parent::__construct("Pesanan dengan id {$id} tidak ditemukan");
    }
}
