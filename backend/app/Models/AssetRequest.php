<?php

namespace App\Models;

use App\Enums\AssetRequestStatus;
use App\Enums\AssetRequestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'asset_id',
        'location_id',
        'reported_by',
        'type',
        'description',
        'photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetRequestType::class,
            'status' => AssetRequestStatus::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

}
