<?php

return [
    'roles' => [
        'admin' => 'admin',
        'maintenance' => 'maintenance',
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
        'Pending',
        'In Progress',
        'Completed',
        'Cancelled',
    ],
    'maintenance_user_statuses' => [
        'In Progress',
        'Completed',
    ],
    'locked_statuses' => [
        'Completed',
        'Cancelled',
    ],
];
