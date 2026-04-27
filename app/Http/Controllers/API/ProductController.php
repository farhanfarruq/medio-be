<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $productRepo) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productRepo->getAll($request->all());

        return response()->json($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productRepo->findBySlug($slug);

        return response()->json($product);
    }

    public function brands(): JsonResponse
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
