<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $productRepo) {}

    public function index(Request $request)
    {
        $products = $this->productRepo->getAll($request->all());

        // Support both paginated and collection results
        return ProductResource::collection($products);
    }

    public function show(string $slug)
    {
        $product = $this->productRepo->findBySlug($slug);

        return new ProductResource($product);
    }

    public function brands()
    {
        $brands = \App\Models\Product::select('brand')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return response()->json($brands);
    }
}
