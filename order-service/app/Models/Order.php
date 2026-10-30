<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Kolom yang boleh diisi massal saat checkout.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
    ];

    /**
     * Item snapshot milik pesanan ini.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Tipe data atribut setelah dibaca dari database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
        ];
    }
}
