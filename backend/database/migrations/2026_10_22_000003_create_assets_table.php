<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_tag')->index();
            $table->string('asset_name');
            $table->foreignId('category_id');
            $table->foreignId('location_id');
            $table->index('category_id');
            $table->index('location_id');
            $table->foreign('category_id', 'assets_category_id_erd_foreign')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
            $table->foreign('location_id', 'assets_location_id_erd_foreign')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('description');
            $table->string('room_area')->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 12, 2);
            $table->string('condition')->index();
            $table->string('status')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
