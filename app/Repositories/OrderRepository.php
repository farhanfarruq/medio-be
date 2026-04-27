<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\XenditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(private XenditService $xenditService) {}

    public function create(array $orderData, array $items): Order
    {
        return DB::transaction(function () use ($orderData, $items) {
            $orderData['order_number'] = 'ORD-' . strtoupper(Str::random(10));
            $order = Order::create($orderData);

            $createdItems = [];

            foreach ($items as $index => $item) {
                $orderItem = OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
                    'product_name'  => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity'      => $item['quantity'],
                    'weight'        => $item['weight'],
                    'variant'       => $item['variant'] ?? null,
                    'prescription'  => $item['prescription'] ?? null,
                    'subtotal'      => $item['product_price'] * $item['quantity'],
                ]);
                
                $createdItems[$index] = $orderItem;
            }

            foreach ($items as $index => $item) {
                if (isset($item['linked_item_index']) && isset($createdItems[$item['linked_item_index']])) {
                    $createdItems[$index]->update([
                        'parent_item_id' => $createdItems[$item['linked_item_index']]->id,
                    ]);
                }
            }

            $order->load(['items', 'payment', 'shippingAddress', 'user']);

            $checkoutUrl = $this->xenditService->createInvoice($order);

            Payment::create([
                'order_id'        => $order->id,
                'transaction_id'  => $order->order_number,
                'checkout_url'    => $checkoutUrl,
                'gross_amount'    => $order->total_price,
            ]);

            return $order->fresh(['items', 'payment', 'shippingAddress']);
        });
    }

    public function findById(int $id): Order
    {
        return Order::with(['items.product', 'payment', 'shippingAddress', 'user'])->findOrFail($id);
    }

    public function findByOrderNumber(string $orderNumber): Order
    {
        return Order::with(['items.product', 'payment', 'shippingAddress'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    public function updateStatus(int $id, string $status): bool
    {
        return Order::where('id', $id)->update(['status' => $status]) > 0;
    }

    public function getUserOrders(int $userId)
    {
        return Order::with(['items.product', 'payment'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }
}
