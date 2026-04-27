<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrdersWidget extends BaseWidget
{
    protected static ?int $sort   = 2;
    protected int | string $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesanan Terbaru')
            ->query(Order::with(['user', 'payment'])->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger'  => fn ($state) => in_array($state, ['cancelled', 'refunded']),
                        'warning' => fn ($state) => in_array($state, ['unpaid', 'processing']),
                        'success' => fn ($state) => in_array($state, ['paid', 'delivered']),
                        'primary' => fn ($state) => $state === 'shipped',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i'),
            ]);
    }
}
