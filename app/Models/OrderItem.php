<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_price',
        'quantity', 'weight', 'variant', 'subtotal', 'prescription', 'parent_item_id',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal'      => 'decimal:2',
        'variant'       => 'array',
        'prescription'  => 'array',
    ];

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'parent_item_id');
    }

    public function subItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class, 'parent_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
