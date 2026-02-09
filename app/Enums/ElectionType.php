<?php

namespace App\Enums;

enum ElectionType: string
{
    case MAYOR = 'mayor';
    case GOVERNOR = 'governor';
    case PRESIDENT = 'president';
    case HOUSE = 'house';
    case SENATE = 'senate';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MAYOR => 'Alcaldía',
            self::GOVERNOR => 'Gobernación',
            self::PRESIDENT => 'Presidencia',
            self::HOUSE => 'Cámara',
            self::SENATE => 'Senado',
            self::OTHER => 'Otra',
        };
    }

    public function scope(): CampaignScope
    {
        return match ($this) {
            self::MAYOR => CampaignScope::Municipal,
            self::GOVERNOR => CampaignScope::Departamental,
            self::HOUSE => CampaignScope::Departamental,
            self::PRESIDENT => CampaignScope::Nacional,
            self::SENATE => CampaignScope::Nacional,
            self::OTHER => CampaignScope::Regional,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->toArray();
    }

    public static function fromScope(CampaignScope $scope): self
    {
        return match ($scope) {
            CampaignScope::Municipal => self::MAYOR,
            CampaignScope::Departamental => self::GOVERNOR,
            CampaignScope::Nacional => self::PRESIDENT,
            CampaignScope::Regional => self::OTHER,
        };
    }
}
