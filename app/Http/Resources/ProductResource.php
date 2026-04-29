<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->images ?? [];

        $resolvedImages = array_map(function ($image) {
            if (str_starts_with($image, 'http')) {
                return $image;
            }
            return Storage::disk(config('filesystems.default'))->url($image);
        }, $images);

        return [
            'id'                       => $this->id,
            'category_id'              => $this->category_id,
            'category'                 => $this->whenLoaded('category'),
            'name'                     => $this->name,
            'slug'                     => $this->slug,
            'sku'                      => $this->sku,
            'description'              => $this->description,
            'brand'                    => $this->brand,
            'price'                    => $this->price,
            'stock'                    => $this->stock,
            'weight'                   => $this->weight,
            'dimensions'               => $this->dimensions,
            'variants'                 => $this->variants,
            'images'                   => $resolvedImages,
            'tags'                     => $this->tags,
            'is_active'                => $this->is_active,
            'is_best_seller'           => $this->is_best_seller,
            'is_new'                   => $this->is_new,
            'is_not_for_sale'          => $this->is_not_for_sale,
            'is_prescription_required' => $this->is_prescription_required,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
