<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'brand',
        'price',
        'stock',
        'weight',
        'dimensions',
        'variants',
        'images',
        'tags',
        'is_active',
        'is_best_seller',
        'is_new',
        'is_not_for_sale',
        'is_prescription_required',
    ];

    protected $casts = [
        'price'                    => 'float',
        'stock'                    => 'integer',
        'weight'                   => 'integer',
        'dimensions'               => 'array',
        'variants'                 => 'array',
        'images'                   => 'array',
        'tags'                     => 'array',
        'is_active'                => 'boolean',
        'is_best_seller'           => 'boolean',
        'is_new'                   => 'boolean',
        'is_not_for_sale'          => 'boolean',
        'is_prescription_required' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) return [];
                
                $images = is_array($value) ? $value : json_decode($value, true);
                
                return array_map(function ($image) {
                    if (str_starts_with($image, 'http')) return $image;
                    
                    return Storage::disk(config('filesystems.default'))->url($image);
                }, $images);
            },
        );
    }
}
