<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('started_at');
            $table->text('completion_notes')->nullable()->after('remarks');
            $table->text('maintenance_performed')->nullable()->after('completion_notes');
            $table->text('parts_replaced')->nullable()->after('maintenance_performed');
            $table->string('updated_condition')->nullable()->after('parts_replaced');
            $table->string('updated_asset_status')->nullable()->after('updated_condition');
        });

        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->string('status')->default('Assigned')->change();
        });
        DB::statement("UPDATE maintenance_tasks SET status = 'Assigned' WHERE status = 'Pending'");
    }

    public function down(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'acknowledged_at',
                'completion_notes',
                'maintenance_performed',
                'parts_replaced',
                'updated_condition',
                'updated_asset_status',
            ]);
        });

        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->string('status')->default('Pending')->change();
        });
        DB::statement("UPDATE maintenance_tasks SET status = 'Pending' WHERE status = 'Assigned'");
    }
};
