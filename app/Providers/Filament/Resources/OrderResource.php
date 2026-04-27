<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Pesanan';
    protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort     = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Pesanan')->schema([
                Forms\Components\TextInput::make('order_number')
                    ->label('Nomor Order')
                    ->disabled(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'unpaid'     => 'Belum Dibayar',
                        'paid'       => 'Sudah Dibayar',
                        'processing' => 'Sedang Diproses',
                        'shipped'    => 'Dikirim',
                        'delivered'  => 'Diterima',
                        'cancelled'  => 'Dibatalkan',
                        'refunded'   => 'Refund',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('courier')
                    ->label('Kurir')
                    ->disabled(),

                Forms\Components\TextInput::make('courier_service')
                    ->label('Layanan')
                    ->disabled(),

                Forms\Components\TextInput::make('tracking_number')
                    ->label('Nomor Resi'),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Ringkasan Pembayaran')->schema([
                Forms\Components\TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->prefix('Rp')
                    ->disabled(),

                Forms\Components\TextInput::make('shipping_cost')
                    ->label('Ongkos Kirim')
                    ->prefix('Rp')
                    ->disabled(),

                Forms\Components\TextInput::make('total_price')
                    ->label('Total')
                    ->prefix('Rp')
                    ->disabled(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger'  => fn ($state) => in_array($state, ['cancelled', 'refunded']),
                        'warning' => fn ($state) => in_array($state, ['unpaid', 'processing']),
                        'success' => fn ($state) => in_array($state, ['paid', 'delivered']),
                        'primary' => fn ($state) => $state === 'shipped',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unpaid'     => 'Belum Dibayar',
                        'paid'       => 'Sudah Dibayar',
                        'processing' => 'Diproses',
                        'shipped'    => 'Dikirim',
                        'delivered'  => 'Diterima',
                        'cancelled'  => 'Dibatalkan',
                        'refunded'   => 'Refund',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('courier')
                    ->label('Kurir')
                    ->formatStateUsing(fn (?string $state) => strtoupper($state ?? '-')),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Resi')
                    ->copyable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'unpaid'     => 'Belum Dibayar',
                        'paid'       => 'Sudah Dibayar',
                        'processing' => 'Diproses',
                        'shipped'    => 'Dikirim',
                        'delivered'  => 'Diterima',
                        'cancelled'  => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Detail Pesanan')->schema([
                Infolists\Components\TextEntry::make('order_number')->label('No. Order'),
                Infolists\Components\TextEntry::make('user.name')->label('Customer'),
                Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                Infolists\Components\TextEntry::make('total_price')->label('Total')->money('IDR'),
                Infolists\Components\TextEntry::make('courier')->label('Kurir'),
                Infolists\Components\TextEntry::make('tracking_number')->label('Resi'),
                Infolists\Components\TextEntry::make('paid_at')->label('Dibayar Pada')->dateTime('d M Y H:i'),
            ])->columns(2),

            Infolists\Components\Section::make('Alamat Pengiriman')->schema([
                Infolists\Components\TextEntry::make('shippingAddress.recipient_name')->label('Penerima'),
                Infolists\Components\TextEntry::make('shippingAddress.phone')->label('Telepon'),
                Infolists\Components\TextEntry::make('shippingAddress.address')->label('Alamat')->columnSpanFull(),
                Infolists\Components\TextEntry::make('shippingAddress.city')->label('Kota'),
                Infolists\Components\TextEntry::make('shippingAddress.postal_code')->label('Kode Pos'),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
