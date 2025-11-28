<?php

namespace App\Filament\Resources\ElectionEvents\Pages;

use App\Filament\Resources\ElectionEvents\ElectionEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateElectionEvent extends CreateRecord
{
    protected static string $resource = ElectionEventResource::class;
}
