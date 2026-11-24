<?php

use App\Models\MaintenanceTask;

return [
    'roles' => [
        'admin' => 'admin',
        'maintenance' => 'maintenance',
        'end_user' => 'end_user',
    ],
    'permissions' => [
        'view_all' => ['admin'],
        'manage' => ['admin'],
        'view_assigned' => ['maintenance'],
        'update_assigned' => ['maintenance'],
    ],
    'priorities' => [
        'Low',
        'Medium',
        'High',
        'Urgent',
    ],
    'statuses' => [
        MaintenanceTask::STATUS_ASSIGNED,
        MaintenanceTask::STATUS_ACKNOWLEDGED,
        MaintenanceTask::STATUS_IN_PROGRESS,
        MaintenanceTask::STATUS_ON_HOLD,
        MaintenanceTask::STATUS_PENDING_APPROVAL,
        MaintenanceTask::STATUS_APPROVED,
        MaintenanceTask::STATUS_COMPLETED,
        MaintenanceTask::STATUS_CANCELLED,
        MaintenanceTask::STATUS_PENDING,
    ],
    'admin_update_statuses' => [
        MaintenanceTask::STATUS_ASSIGNED,
        MaintenanceTask::STATUS_ACKNOWLEDGED,
        MaintenanceTask::STATUS_IN_PROGRESS,
        MaintenanceTask::STATUS_ON_HOLD,
        MaintenanceTask::STATUS_CANCELLED,
    ],
    'maintenance_user_statuses' => [
        MaintenanceTask::STATUS_ACKNOWLEDGED,
        MaintenanceTask::STATUS_IN_PROGRESS,
        MaintenanceTask::STATUS_ON_HOLD,
    ],
];
