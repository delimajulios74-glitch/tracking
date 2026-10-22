<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets') && Schema::hasTable('assets_archive')) {
            throw new RuntimeException('Both assets and assets_archive exist; refusing to rename either table.');
        }

        if (Schema::hasTable('assets')) {
            Schema::rename('assets', 'assets_archive');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('assets_archive')) {
            return;
        }

        if (Schema::hasTable('assets')) {
            throw new RuntimeException('Cannot restore assets_archive while an assets table exists.');
        }

        Schema::rename('assets_archive', 'assets');
    }
};
