<?php

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
        'Assigned',
        'Acknowledged',
        'In Progress',
        'On Hold',
        'Completed',
        'Cancelled',
        'Pending',
    ],
    'maintenance_user_statuses' => [
        'Acknowledged',
        'In Progress',
        'On Hold',
        'Completed',
    ],
    'locked_statuses' => [
        'Completed',
        'Cancelled',
    ],
];
