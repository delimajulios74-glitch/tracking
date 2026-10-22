<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\MaintenanceLog;
use Illuminate\Database\Seeder;

class MaintenanceLogSeeder extends Seeder
{
    public function run(): void
    {
        $asset = Asset::query()->orderBy('id')->first();
        if (! $asset) {
            return;
        }

        foreach ([
            [
                'maintenance_type' => 'Inspection',
                'date' => now()->subDays(90)->toDateString(),
                'performed_by' => 'Municipal maintenance staff',
                'description_of_work' => 'Completed a routine condition inspection.',
                'parts_replaced' => null,
                'cost' => null,
                'remarks' => 'No immediate repair required.',
            ],
            [
                'maintenance_type' => 'Preventive',
                'date' => now()->subDays(45)->toDateString(),
                'performed_by' => 'Municipal maintenance staff',
                'description_of_work' => 'Cleaned the equipment and checked normal operation.',
                'parts_replaced' => null,
                'cost' => 250.00,
                'remarks' => null,
            ],
            [
                'maintenance_type' => 'Repair',
                'date' => now()->subDays(15)->toDateString(),
                'performed_by' => 'Accredited service provider',
                'description_of_work' => 'Replaced a worn component and verified operation.',
                'parts_replaced' => 'Replacement component',
                'cost' => 1200.00,
                'remarks' => 'Sample maintenance record.',
            ],
        ] as $values) {
            MaintenanceLog::firstOrCreate([
                'asset_id' => $asset->id,
                'maintenance_type' => $values['maintenance_type'],
                'date' => $values['date'],
                'performed_by' => $values['performed_by'],
            ], $values);
        }
    }
}
