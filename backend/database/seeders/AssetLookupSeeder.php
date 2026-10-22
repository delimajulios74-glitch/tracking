<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Seeder;

class AssetLookupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Office Equipment',
            'Information Technology Equipment',
            'Furniture and Fixtures',
            'Vehicles',
        ] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        foreach ([
            'Municipal Hall',
            'Municipal Health Office',
            'Municipal Agriculture Office',
            'Municipal Engineering Office',
        ] as $name) {
            Location::firstOrCreate(['name' => $name]);
        }
    }
}
