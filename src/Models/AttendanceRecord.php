<?php

namespace Risqid\AttendanceEngine\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'attendance_session_id',
        'participant_type',
        'participant_id',
        'action',
        'recorded_at',
        'qr_window',
        'scan_lat',
        'scan_lon',
        'scan_accuracy_meter',
        'scan_distance_meter',
        'ip_address',
        'user_agent',
        'source',
        'metadata',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'qr_window' => 'integer',
        'scan_lat' => 'decimal:7',
        'scan_lon' => 'decimal:7',
        'scan_accuracy_meter' => 'integer',
        'scan_distance_meter' => 'integer',
        'metadata' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }
}
