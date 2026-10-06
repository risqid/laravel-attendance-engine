<?php

namespace Unwahas\AttendanceEngine\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'context_type',
        'context_id',
        'name',
        'starts_at',
        'ends_at',
        'timezone',
        'allowed_actions',
        'action_windows',
        'status',
        'qr_rotation_seconds',
        'qr_grace_windows',
        'location_required',
        'location_policy',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'allowed_actions' => 'array',
        'action_windows' => 'array',
        'qr_rotation_seconds' => 'integer',
        'qr_grace_windows' => 'integer',
        'location_required' => 'boolean',
        'location_policy' => 'array',
        'metadata' => 'array',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'attendance_session_id');
    }

    public function allowsAction(string $action): bool
    {
        return in_array($action, $this->allowed_actions ?? [], true);
    }

    public function isOpenAt($time): bool
    {
        return $this->status === 'open'
            && $this->starts_at->lessThanOrEqualTo($time)
            && $this->ends_at->greaterThanOrEqualTo($time);
    }

    public function isActionWindowOpenAt(string $action, $time): bool
    {
        $window = $this->action_windows[$action] ?? null;

        if (!is_array($window) || empty($window['from']) || empty($window['until'])) {
            return $this->starts_at->lessThanOrEqualTo($time)
                && $this->ends_at->greaterThanOrEqualTo($time);
        }

        return $this->asDateTime($window['from'])->lessThanOrEqualTo($time)
            && $this->asDateTime($window['until'])->greaterThanOrEqualTo($time);
    }

    public function requiresCheckInForCheckOut(): bool
    {
        if (isset($this->metadata['require_check_in_for_checkout'])) {
            return (bool) $this->metadata['require_check_in_for_checkout'];
        }

        return (bool) config('attendance-engine.rules.require_check_in_for_checkout', false);
    }
}
