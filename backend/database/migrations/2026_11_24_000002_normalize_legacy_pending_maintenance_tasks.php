<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_tasks')
            ->where('status', 'Pending')
            ->update(['status' => 'Assigned']);
    }

    public function down(): void
    {
        throw new LogicException(
            'This migration is irreversible because converted tasks cannot be distinguished from previously Assigned tasks.',
        );
    }
};
