<?php

namespace App\Notifications;

use App\Models\MaintenanceTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceTaskApproved extends Notification
{
    use Queueable;

    public function __construct(private MaintenanceTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->task->loadMissing(['asset', 'approver']);

        return [
            'title' => 'Maintenance Task Approved',
            'message' => sprintf(
                "Your maintenance task '%s' was approved by %s.",
                $this->task->title,
                $this->task->approver->name,
            ),
            'task_id' => $this->task->id,
            'asset_id' => $this->task->asset_id,
            'asset_name' => $this->task->asset->asset_name,
            'property_number' => $this->task->asset->asset_tag,
            'status' => $this->task->status,
            'type' => 'maintenance_task_approved',
        ];
    }
}
