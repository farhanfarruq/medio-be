<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function getAll(array $filters = [])
    {
        $query = Product::with('category')->where('is_active', true);

        if (!empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('brand', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 100;
        if ($perPage === 'all') {
            return $query->get();
        }
        return $query->paginate((int) $perPage);
    }

    public function findById(int $id)
    {
        return Product::with('category')->findOrFail($id);
    }

    public function findBySlug(string $slug)
    {
        return Product::with('category')->where('slug', $slug)->firstOrFail();
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update(int $id, array $data)
    {
        $product = $this->findById($id);
        $product->update($data);
        return $product;
    }

    public function delete(int $id)
    {
        return Product::destroy($id);
    }

    public function decrementStock(int $id, int $quantity): void
    {
        Product::where('id', $id)->decrement('stock', $quantity);
    }
}
