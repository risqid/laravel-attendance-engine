<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_session_id');
            $table->string('participant_type');
            $table->string('participant_id');
            $table->string('action');
            $table->dateTime('recorded_at');
            $table->unsignedBigInteger('qr_window');
            $table->decimal('scan_lat', 10, 7)->nullable();
            $table->decimal('scan_lon', 10, 7)->nullable();
            $table->unsignedInteger('scan_accuracy_meter')->nullable();
            $table->unsignedInteger('scan_distance_meter')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('source')->default('qr');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('attendance_session_id')
                ->references('id')
                ->on('attendance_sessions')
                ->cascadeOnDelete();

            $table->unique(
                ['attendance_session_id', 'participant_type', 'participant_id', 'action'],
                'attendance_records_unique_action'
            );
            $table->index(['attendance_session_id', 'action', 'recorded_at'], 'attendance_records_session_action_time');
            $table->index(['participant_type', 'participant_id']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
