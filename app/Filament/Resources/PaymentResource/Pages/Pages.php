<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\EditRecord;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;
}
