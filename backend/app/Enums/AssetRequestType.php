<?php

namespace App\Enums;

enum AssetRequestType: string
{
    case Damage = 'damage';
    case Malfunction = 'malfunction';
    case Concern = 'concern';
    case Request = 'request';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
