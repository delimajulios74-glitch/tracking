<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTask extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ASSIGNED = 'Assigned';

    public const STATUS_ACKNOWLEDGED = 'Acknowledged';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_ON_HOLD = 'On Hold';

    public const STATUS_PENDING_APPROVAL = 'Pending Approval';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUS_PENDING = 'Pending';

    public const TRANSITIONS = [
        self::STATUS_ASSIGNED => [
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ACKNOWLEDGED => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_IN_PROGRESS => [
            self::STATUS_ON_HOLD,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ON_HOLD => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PENDING_APPROVAL => [
            self::STATUS_APPROVED,
            self::STATUS_IN_PROGRESS,
        ],
    ];

    protected $attributes = [
        'priority' => 'Medium',
        'status' => self::STATUS_ASSIGNED,
    ];

    protected $fillable = [
        'asset_id',
        'assigned_to',
        'assigned_by',
        'title',
        'instructions',
        'priority',
        'due_date',
        'status',
        'problem',
        'remarks',
        'started_at',
        'acknowledged_at',
        'completed_at',
        'completion_notes',
        'maintenance_performed',
        'parts_replaced',
        'updated_condition',
        'updated_asset_status',
        'previous_asset_status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function maintenanceLog(): HasOne
    {
        return $this->hasOne(MaintenanceLog::class, 'task_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
