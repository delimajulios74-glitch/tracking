<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset' => $this->whenLoaded('asset', fn (): array => [
                'id' => $this->asset->id,
                'asset_tag' => $this->asset->asset_tag,
                'asset_name' => $this->asset->asset_name,
                'status' => $this->asset->status,
                'category' => $this->asset->category?->name,
                'location' => $this->asset->location?->name,
            ]),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn (): array => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'username' => $this->assignee->username,
            ]),
            'assigned_by' => $this->assigned_by,
            'assigner' => $this->whenLoaded('assigner', fn (): array => [
                'id' => $this->assigner->id,
                'name' => $this->assigner->name,
            ]),
            'title' => $this->title,
            'instructions' => $this->instructions,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'problem' => $this->problem,
            'remarks' => $this->remarks,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'activity_logs' => $this->whenLoaded('activityLogs', fn (): array => $this->activityLogs
                ->map(fn ($activityLog): array => [
                    'id' => $activityLog->id,
                    'action' => $activityLog->action,
                    'description' => $activityLog->description,
                    'created_at' => $activityLog->created_at,
                    'user' => $activityLog->user ? [
                        'id' => $activityLog->user->id,
                        'name' => $activityLog->user->name,
                    ] : null,
                ])
                ->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
