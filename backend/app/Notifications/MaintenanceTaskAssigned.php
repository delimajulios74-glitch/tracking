<?php

namespace App\Notifications;

use App\Models\MaintenanceTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceTaskAssigned extends Notification
{
    use Queueable;

    public function __construct(private MaintenanceTask $task)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->task->loadMissing('asset');

        return [
            'title' => 'New Maintenance Task Assigned',
            'message' => sprintf(
                'You have been assigned a maintenance task for %s (Property No. %s).',
                $this->task->asset->asset_name,
                $this->task->asset->asset_tag,
            ),
            'task_id' => $this->task->id,
            'asset_id' => $this->task->asset_id,
            'asset_name' => $this->task->asset->asset_name,
            'property_number' => $this->task->asset->asset_tag,
            'task_description' => $this->task->title,
            'priority' => $this->task->priority,
            'assigned_at' => $this->task->created_at?->toISOString(),
            'type' => 'maintenance_task_assigned',
        ];
    }
}
