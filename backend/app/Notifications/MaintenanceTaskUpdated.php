<?php

namespace App\Notifications;

use App\Models\MaintenanceTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceTaskUpdated extends Notification
{
    use Queueable;

    public function __construct(private MaintenanceTask $task, private string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->task->loadMissing(['asset', 'assignee']);
        $acknowledged = $this->event === 'acknowledged';

        return [
            'title' => $acknowledged ? 'Maintenance Task Acknowledged' : 'Maintenance Task Completed',
            'message' => $acknowledged
                ? sprintf('%s acknowledged task "%s".', $this->task->assignee->name, $this->task->title)
                : sprintf('%s completed task "%s".', $this->task->assignee->name, $this->task->title),
            'task_id' => $this->task->id,
            'asset_id' => $this->task->asset_id,
            'asset_name' => $this->task->asset->asset_name,
            'property_number' => $this->task->asset->asset_tag,
            'task_description' => $this->task->title,
            'priority' => $this->task->priority,
            'status' => $this->task->status,
            'updated_at' => now()->toISOString(),
            'type' => $acknowledged ? 'maintenance_task_acknowledged' : 'maintenance_task_completed',
        ];
    }
}
