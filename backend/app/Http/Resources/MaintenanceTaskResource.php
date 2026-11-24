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
            'linked_report' => $this->when($this->relationLoaded('linkedReport'), function (): ?array {
                $report = $this->linkedReport;
                if (! $report) {
                    return null;
                }

                return [
                    'id' => $report->id,
                    'reference_no' => $report->reference_no,
                    'description' => $report->description,
                    'status' => $report->status->value,
                    'created_at' => $report->created_at,
                    'reporter' => $report->reporter?->name,
                ];
            }),
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
            'acknowledged_at' => $this->acknowledged_at,
            'completed_at' => $this->completed_at,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'rejection_reason' => $this->rejection_reason,
            'completion_notes' => $this->completion_notes,
            'maintenance_performed' => $this->maintenance_performed,
            'parts_replaced' => $this->parts_replaced,
            'updated_condition' => $this->updated_condition,
            'updated_asset_status' => $this->updated_asset_status,
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
