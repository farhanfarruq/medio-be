<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRevenue = Order::where('status', '!=', 'cancelled')
            ->whereNotNull('paid_at')
            ->sum('total_price');

        $pendingOrders = Order::whereIn('status', ['paid', 'processing'])->count();
        $totalProducts = Product::where('is_active', true)->count();
        $lowStock      = Product::where('stock', '<', 5)->where('is_active', true)->count();

        return [
            Stat::make('Total Pendapatan', 'Rp ' . number_format($totalRevenue, 0, ',', '.'))
                ->description('Semua order yang berhasil dibayar')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Order Perlu Diproses', $pendingOrders)
                ->description('Status: Paid & Processing')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Total Produk Aktif', $totalProducts)
                ->description($lowStock . ' produk stok menipis')
                ->color($lowStock > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shopping-bag'),

            Stat::make('Total Customer', User::count())
                ->description('Pengguna terdaftar')
                ->color('primary')
                ->icon('heroicon-o-users'),
        ];
    }
}
