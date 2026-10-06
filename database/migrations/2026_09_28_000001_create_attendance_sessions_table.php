<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('context_type');
            $table->string('context_id');
            $table->string('name')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone')->default('UTC');
            $table->json('allowed_actions');
            $table->json('action_windows')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('qr_rotation_seconds')->default(10);
            $table->unsignedTinyInteger('qr_grace_windows')->default(1);
            $table->boolean('location_required')->default(false);
            $table->json('location_policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
