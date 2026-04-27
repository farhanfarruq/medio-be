<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Discount;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderRepositoryInterface $orderRepo) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderRepo->getUserOrders($request->user()->id);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_address_id'          => 'required|exists:shipping_addresses,id',
            'courier'                      => 'required|string',
            'courier_service'              => 'required|string',
            'shipping_cost'                => 'required|numeric|min:0',
            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'items.*.variant'              => 'nullable|array',
            'items.*.prescription'         => 'nullable|array',
            'items.*.linked_item_index'    => 'nullable|integer',
            'notes'                        => 'nullable|string|max:500',
            'discount_id'                  => 'nullable|exists:discounts,id',
        ]);

        $items    = [];
        $subtotal = 0;

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $isLinkedLens = isset($item['linked_item_index']);

            // Skip stock check for linked lens items (lensa selalu tersedia di toko optik)
            if (!$isLinkedLens && $product->stock < $item['quantity']) {
                return response()->json([
                    'message' => 'Stok produk "' . $product->name . '" tidak mencukupi.',
                ], 422);
            }

            // Only enforce prescription check for items that are NOT a child/linked lens
            if ($product->is_prescription_required && empty($item['prescription']) && !$isLinkedLens) {
                return response()->json([
                    'message' => 'Produk "' . $product->name . '" membutuhkan data resep mata.',
                ], 422);
            }

            $subtotal += $product->price * $item['quantity'];

            $items[] = [
                'product_id'        => $product->id,
                'product_name'      => $product->name,
                'product_price'     => $product->price,
                'quantity'          => $item['quantity'],
                'weight'            => $product->weight,
                'variant'           => $item['variant'] ?? null,
                'prescription'      => $item['prescription'] ?? null,
                'linked_item_index' => $item['linked_item_index'] ?? null,
            ];
        }
        
        $discountAmount = 0;
        if ($request->discount_id) {
            $discount = Discount::find($request->discount_id);
            if ($discount && $discount->isValid()) {
                if ($discount->type === 'percentage') {
                    $discountAmount = ($subtotal * $discount->value) / 100;
                } else {
                    $discountAmount = $discount->value;
                }
                
                // Ensure discount doesn't exceed subtotal
                $discountAmount = min($discountAmount, $subtotal);
                $discount->increment('used_count');
            }
        }

        $orderData = [
            'user_id'             => $request->user()->id,
            'shipping_address_id' => $request->shipping_address_id,
            'status'              => 'unpaid',
            'subtotal'            => $subtotal,
            'shipping_cost'       => $request->shipping_cost,
            'discount_id'         => $request->discount_id,
            'discount_amount'     => $discountAmount,
            'total_price'         => $subtotal + $request->shipping_cost - $discountAmount,
            'courier'             => $request->courier,
            'courier_service'     => $request->courier_service,
            'notes'               => $request->notes,
        ];

        $order = $this->orderRepo->create($orderData, $items);

        foreach ($request->items as $item) {
            // Jangan kurangi stok untuk lensa yang di-link (selalu tersedia)
            if (!isset($item['linked_item_index'])) {
                Product::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
            }
        }

        return response()->json($order, 201);
    }
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order);
    }

    public function syncPayment(Request $request, int $id, \App\Services\XenditService $xenditService): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$order->payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        $status = $xenditService->syncInvoice($order);

        return response()->json([
            'message' => 'Sync completed',
            'status'  => $status,
            'order'   => $order->fresh(['payment'])
        ]);
    }
}
