<?php

namespace App\Exceptions;

use RuntimeException;

class EmptyCartException extends RuntimeException
{
    /**
     * Keranjang user tidak berisi item yang bisa di-checkout.
     */
    public function __construct()
    {
        parent::__construct('Keranjang kosong, tidak ada yang bisa di-checkout');
    }
}
