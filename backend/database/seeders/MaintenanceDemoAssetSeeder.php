<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Seeder;

class MaintenanceDemoAssetSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['name' => 'Office Equipment']);
        $location = Location::firstOrCreate(['name' => 'Municipal Hall']);

        $asset = Asset::withTrashed()->firstOrCreate(
            ['asset_tag' => 'DEMO-MAINT-001'],
            [
                'asset_name' => 'Sample Office Printer',
                'category_id' => $category->id,
                'location_id' => $location->id,
                'description' => 'Demo asset used to verify maintenance log workflows.',
                'purchase_date' => now()->subYear()->toDateString(),
                'purchase_cost' => 12000,
                'condition' => 'Good',
                'status' => 'Available',
                'remarks' => 'Sample data; not an actual municipal asset.',
            ],
        );

        if ($asset->trashed()) {
            $asset->restore();
        }
    }
}
