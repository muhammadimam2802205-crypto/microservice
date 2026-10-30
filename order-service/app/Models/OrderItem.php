<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /**
     * Tabel order_items tidak punya kolom created_at/updated_at (lihat migration).
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Kolom snapshot item yang boleh diisi massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    /**
     * Pesanan pemilik item ini.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Tipe data atribut setelah dibaca dari database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
            'quantity' => 'integer',
            'subtotal' => 'float',
        ];
    }
}
