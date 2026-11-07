<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->foreignId('asset_id')->nullable()->change();
            $table->foreign('asset_id')->references('id')->on('assets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->foreignId('asset_id')->nullable(false)->change();
            $table->foreign('asset_id')->references('id')->on('assets')->restrictOnDelete();
        });
    }
};
