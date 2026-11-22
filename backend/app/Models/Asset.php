<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_tag',
        'asset_name',
        'category_id',
        'location_id',
        'brand',
        'model',
        'serial_number',
        'description',
        'room_area',
        'purchase_date',
        'purchase_cost',
        'condition',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class);
    }

    public function scopeVisibleToEndUser(Builder $query, ?User $user = null): Builder
    {
        return $query->whereRaw('LOWER(status) <> ?', ['disposed']);
    }

    public function scopeSearch(Builder $query, ?string $search, bool $includeBuilding = true): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $pattern = '%'.$search.'%';

        return $query->where(function (Builder $query) use ($pattern, $includeBuilding): void {
            foreach ([
                'asset_tag',
                'asset_name',
                'description',
                'brand',
                'model',
                'serial_number',
                'room_area',
            ] as $column) {
                $query->orWhereRaw("LOWER({$column}) LIKE LOWER(?)", [$pattern]);
            }

            $query->orWhereHas('category', fn (Builder $category) => $category
                ->whereRaw('LOWER(name) LIKE LOWER(?)', [$pattern]))
                ->orWhereHas('location', function (Builder $location) use ($pattern, $includeBuilding): void {
                    $location->whereRaw('LOWER(name) LIKE LOWER(?)', [$pattern]);
                    if ($includeBuilding) {
                        $location->orWhereRaw('LOWER(building) LIKE LOWER(?)', [$pattern]);
                    }
                });
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $query->search($filters['q'] ?? null);

        foreach (['category_id', 'location_id', 'status', 'condition'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['purchase_date_from']) && $filters['purchase_date_from'] !== '') {
            $query->whereDate('purchase_date', '>=', $filters['purchase_date_from']);
        }

        if (isset($filters['purchase_date_to']) && $filters['purchase_date_to'] !== '') {
            $query->whereDate('purchase_date', '<=', $filters['purchase_date_to']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $query->orderBy($sortBy, $sortDirection);
    }
}
