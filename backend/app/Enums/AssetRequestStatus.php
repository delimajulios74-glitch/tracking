<?php

namespace App\Enums;

enum AssetRequestStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
