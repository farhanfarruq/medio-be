<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;

interface OrderRepositoryInterface
{
    public function create(array $orderData, array $items): Order;
    public function findById(int $id): Order;
    public function findByOrderNumber(string $orderNumber): Order;
    public function updateStatus(int $id, string $status): bool;
    public function getUserOrders(int $userId);
}
