<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state) . '-' . Str::random(5)) : null),
                Forms\Components\TextInput::make('slug')
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->maxLength(255)
                    ->unique(Product::class, 'slug', ignoreRecord: true),
                Forms\Components\TextInput::make('brand')
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('weight')
                    ->required()
                    ->numeric()
                    ->default(1000)
                    ->suffix('gram'),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Forms\Components\Toggle::make('is_best_seller')
                    ->required()
                    ->default(false),
                Forms\Components\Toggle::make('is_prescription_required')
                    ->required()
                    ->default(false),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('current_images_preview')
                    ->label('Preview Gambar Saat Ini')
                    ->content(function ($record) {
                        if (!$record || empty($record->images)) {
                            return 'Belum ada gambar yang tersimpan.';
                        }
                        
                        $images = is_array($record->images) ? $record->images : json_decode($record->images, true);
                        if (empty($images)) return 'Belum ada gambar yang tersimpan.';

                        $html = '<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px;">';
                        foreach ($images as $image) {
                            $url = str_starts_with($image, 'http') 
                                ? $image 
                                : \Illuminate\Support\Facades\Storage::disk('public')->url($image);
                            
                            $html .= "
                                <div style='position: relative;'>
                                    <img src='{$url}' style='height: 120px; width: 120px; object-fit: cover; border-radius: 12px; border: 2px solid #eee; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                                </div>";
                        }
                        $html .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('images')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('products')
                    ->columnSpanFull()
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Jika gambar tidak muncul di sini, itu karena gambar tersebut masih berupa link eksternal. Tenang saja, gambar tidak akan hilang saat Anda menyimpan detail produk lainnya.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                /* Tables\Columns\ImageColumn::make('images')
                    ->label('Foto Produk')
                    ->circular()
                    ->stacked()
                    ->disk('public'), */
                Tables\Columns\TextColumn::make('name')->searchable(),
                // Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('stock')->numeric()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_best_seller')->label('Best Seller')->boolean(),
                Tables\Columns\IconColumn::make('is_prescription_required')->boolean(),
            ])
            ->filters([ Tables\Filters\TrashedFilter::make() ])
            ->actions([ \Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make() ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('setAsBestSeller')
                        ->label('Set Jadi Best Seller')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['is_best_seller' => true]))
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('setPrescriptionRequired')
                        ->label('Set Butuh Resep')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['is_prescription_required' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
