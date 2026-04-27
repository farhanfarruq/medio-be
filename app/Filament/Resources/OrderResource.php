<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('tracking_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('subtotal')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('shipping_cost')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('total_price')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('courier')
                    ->disabled(),
                Forms\Components\TextInput::make('courier_service')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('shipped_at'),
                Forms\Components\DateTimePicker::make('delivered_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items.product_name')
                    ->label('Produk')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'warning',
                        'paid' => 'success',
                        'processing' => 'primary',
                        'shipped' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('courier'),
                Tables\Columns\TextColumn::make('tracking_number')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([ Tables\Filters\TrashedFilter::make() ])
            ->actions([ \Filament\Actions\EditAction::make(), \Filament\Actions\ViewAction::make() ])
            ->bulkActions([ \Filament\Actions\BulkActionGroup::make([ \Filament\Actions\DeleteBulkAction::make() ]) ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
