<?php

return [
    'admin' => 'admin',
    'maintenance' => 'maintenance',
    'end_user' => 'end_user',
    'assignable' => ['admin', 'maintenance', 'end_user'],
    'permissions' => [
        'admin' => ['manage_all'],
        'maintenance' => ['view_assets', 'manage_maintenance'],
        'end_user' => ['view_equipment', 'submit_asset_requests', 'view_own_asset_requests', 'manage_own_account'],
    ],
];
