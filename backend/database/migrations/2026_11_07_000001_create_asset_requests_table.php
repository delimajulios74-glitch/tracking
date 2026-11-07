<?php

use App\Enums\AssetRequestStatus;
use App\Enums\AssetRequestType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('type')->default(AssetRequestType::Damage->value);
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('status')->default(AssetRequestStatus::Pending->value);
            $table->timestamps();
            $table->index('asset_id');
            $table->index('reported_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
    }
};
