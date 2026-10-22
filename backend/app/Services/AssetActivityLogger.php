<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;

class AssetActivityLogger
{
    public function logCreated(Asset $asset, User $user): void
    {
        $asset->activityLogs()->create([
            'user_id' => $user->id,
            'action' => 'created',
            'description' => "Created asset {$asset->asset_tag}.",
        ]);
    }

    public function logUpdated(Asset $asset, User $user, array $original): void
    {
        $asset->refresh();

        $this->logChangedValue(
            $asset,
            $user,
            $original,
            'location_id',
            'location changed',
            fn (mixed $value): string => $this->locationName($value),
        );
        $this->logChangedValue(
            $asset,
            $user,
            $original,
            'condition',
            'condition changed',
            fn (mixed $value): string => (string) $value,
        );
        $this->logChangedValue(
            $asset,
            $user,
            $original,
            'status',
            'status changed',
            fn (mixed $value): string => (string) $value,
        );

        $otherChanges = array_diff(
            array_keys($asset->getChanges()),
            ['location_id', 'condition', 'status', 'updated_at'],
        );

        if ($otherChanges !== []) {
            $asset->activityLogs()->create([
                'user_id' => $user->id,
                'action' => 'updated',
                'description' => 'Updated asset details.',
            ]);
        }
    }

    public function logDeleted(Asset $asset, User $user): void
    {
        $asset->activityLogs()->create([
            'user_id' => $user->id,
            'action' => 'deleted',
            'description' => "Soft-deleted asset {$asset->asset_tag}.",
        ]);
    }

    private function logChangedValue(
        Asset $asset,
        User $user,
        array $original,
        string $field,
        string $action,
        callable $format,
    ): void {
        if (! array_key_exists($field, $original) || $original[$field] == $asset->getAttribute($field)) {
            return;
        }

        $asset->activityLogs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => sprintf(
                '%s changed from "%s" to "%s".',
                match ($field) {
                    'location_id' => 'Location',
                    default => ucfirst($field),
                },
                $format($original[$field]),
                $format($asset->getAttribute($field)),
            ),
        ]);
    }

    private function locationName(mixed $locationId): string
    {
        if ($locationId === null) {
            return 'None';
        }

        return (string) Location::query()->findOrFail($locationId)->name;
    }
}
