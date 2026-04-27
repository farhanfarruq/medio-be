<?php

namespace App\Filament\Resources\AppSettingResource\Pages;

use App\Filament\Resources\AppSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\EditRecord;

class ListAppSettings extends ListRecords
{
    protected static string $resource = AppSettingResource::class;
}

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;
}
