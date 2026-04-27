<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?string $navigationGroup = 'Katalog';
    protected static ?int $navigationSort     = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Produk')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(Product::class, 'slug', ignoreRecord: true),

                Forms\Components\Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->required()
                    ->preload(),

                Forms\Components\TextInput::make('brand')
                    ->label('Brand / Merek'),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(4)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Harga, Stok & Berat')->schema([
                Forms\Components\TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),

                Forms\Components\TextInput::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0),

                Forms\Components\TextInput::make('weight')
                    ->label('Berat')
                    ->numeric()
                    ->required()
                    ->default(1000)
                    ->suffix('gram')
                    ->minValue(1),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Dimensi (opsional)')->schema([
                Forms\Components\KeyValue::make('dimensions')
                    ->label('Dimensi')
                    ->keyLabel('Dimensi')
                    ->valueLabel('Nilai (cm)')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Varian & Gambar')->schema([
                Forms\Components\Repeater::make('variants')
                    ->label('Varian')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Nama Varian')->required(),
                        Forms\Components\TextInput::make('value')->label('Nilai')->required(),
                        Forms\Components\TextInput::make('additional_price')
                            ->label('Harga Tambahan')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\TagsInput::make('images')
                    ->label('URL Gambar')
                    ->placeholder('Masukkan URL gambar lalu tekan Enter'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Brand')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->sortable()
                    ->color(fn (int $state): string => $state < 5 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Berat')
                    ->suffix(' g'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Kategori'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
