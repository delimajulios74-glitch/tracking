<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('maintenance_type');
            $table->date('date');
            $table->string('performed_by');
            $table->text('description_of_work');
            $table->text('parts_replaced')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index('asset_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
