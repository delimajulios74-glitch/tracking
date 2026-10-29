<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->foreignId('maintenance_task_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('maintenance_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['maintenance_task_id']);
            $table->dropColumn('maintenance_task_id');
        });
    }
};
