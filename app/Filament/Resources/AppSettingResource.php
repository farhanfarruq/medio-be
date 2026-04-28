<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSettingResource\Pages;
use App\Models\AppSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppSettingResource extends Resource
{
<<<<<<< HEAD
    protected static ?string $model = AppSetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $navigationGroup = 'Sistem';

    public static function form(Form $form): Form
    {
        return $form
=======
    protected static ?string \$model = AppSetting::class;
    protected static string | \BackedEnum | null \$navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static string | \UnitEnum | null \$navigationGroup = 'Sistem';

    public static function form(Form \$form): Form
    {
        return \$form
>>>>>>> 822d7bb628b0f423247d84190be941c85dc0c471
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('group')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('value')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->searchable(),
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\TextColumn::make('value')->limit(50),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSettings::route('/'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }
}
