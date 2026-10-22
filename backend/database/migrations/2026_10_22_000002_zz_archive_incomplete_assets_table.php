<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets') || ! Schema::hasTable('assets_archive')) {
            return;
        }

        if (DB::table('migrations')
            ->where('migration', '2026_10_22_000003_create_assets_table')
            ->exists()) {
            return;
        }

        $requiredColumns = [
            'asset_tag',
            'asset_name',
            'category_id',
            'location_id',
            'purchase_date',
            'purchase_cost',
        ];

        if (array_diff($requiredColumns, Schema::getColumnListing('assets')) !== []) {
            throw new RuntimeException('The pending assets table does not match the incomplete ERD migration schema.');
        }

        if (Schema::hasTable('assets_previous_attempt_archive')) {
            throw new RuntimeException('assets_previous_attempt_archive already exists; refusing to rename assets.');
        }

        Schema::rename('assets', 'assets_previous_attempt_archive');
    }

    public function down(): void
    {
        if (
            Schema::hasTable('assets_previous_attempt_archive')
            && ! Schema::hasTable('assets')
            && ! Schema::hasTable('assets_archive')
        ) {
            Schema::rename('assets_previous_attempt_archive', 'assets');
        }
    }
};
