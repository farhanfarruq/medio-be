<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Pendapatan', 'Rp ' . number_format((float) Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total_price'), 0, ',', '.'))
                ->description('Dari pesanan yang sudah dibayar')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Pesanan', Order::count())
                ->description('Seluruh transaksi')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('Total Pelanggan', User::where('role', 'user')->count())
                ->description('Akun terdaftar')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make('Stok Menipis', Product::where('stock', '<', 5)->count())
                ->description('Produk dengan stok < 5')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
