<?php

namespace App\Enums;

enum CampaignScope: string
{
    case Nacional = 'nacional';
    case Departamental = 'departamental';
    case Municipal = 'municipal';
    case Regional = 'regional';

    public function label(): string
    {
        return match ($this) {
            self::Nacional => 'Nacional',
            self::Departamental => 'Departamental',
            self::Municipal => 'Municipal',
            self::Regional => 'Regional',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $scope) => [$scope->value => $scope->label()])
            ->toArray();
    }
}
