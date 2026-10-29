<?php

namespace App\Policies;

use App\Models\MaintenanceTask;
use App\Models\User;

class MaintenanceTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, config('maintenance_tasks.permissions.view_all'), true)
            || in_array($user->role, config('maintenance_tasks.permissions.view_assigned'), true);
    }

    public function view(User $user, MaintenanceTask $task): bool
    {
        if (in_array($user->role, config('maintenance_tasks.permissions.view_all'), true)) {
            return true;
        }

        return in_array($user->role, config('maintenance_tasks.permissions.view_assigned'), true)
            && $task->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, config('maintenance_tasks.permissions.manage'), true);
    }

    public function update(User $user, MaintenanceTask $task): bool
    {
        if (in_array($user->role, config('maintenance_tasks.permissions.manage'), true)) {
            return true;
        }

        return in_array($user->role, config('maintenance_tasks.permissions.update_assigned'), true)
            && $task->assigned_to === $user->id
            && ! in_array($task->status, config('maintenance_tasks.locked_statuses'), true);
    }

    public function delete(User $user, MaintenanceTask $task): bool
    {
        return in_array($user->role, config('maintenance_tasks.permissions.manage'), true);
    }
}
