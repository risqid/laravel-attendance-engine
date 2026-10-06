<?php

namespace Unwahas\AttendanceEngine\Services;

use Unwahas\AttendanceEngine\Contracts\AttendanceContext;
use Unwahas\AttendanceEngine\Models\AttendanceSession;

class AttendanceSessionService
{
    public function createFromContext(AttendanceContext $context, array $options = []): AttendanceSession
    {
        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];
        if (isset($options['require_check_in_for_checkout'])) {
            $metadata['require_check_in_for_checkout'] = (bool) $options['require_check_in_for_checkout'];
        }

        return AttendanceSession::create([
            'context_type' => $context->getAttendanceContextType(),
            'context_id' => (string) $context->getAttendanceContextId(),
            'name' => $options['name'] ?? $context->getAttendanceContextName(),
            'starts_at' => $options['starts_at'] ?? $context->getAttendanceStartsAt(),
            'ends_at' => $options['ends_at'] ?? $context->getAttendanceEndsAt(),
            'timezone' => $options['timezone'] ?? config('attendance-engine.timezone', config('app.timezone', 'UTC')),
            'allowed_actions' => $options['allowed_actions'] ?? $context->getAllowedAttendanceActions(),
            'action_windows' => $options['action_windows'] ?? null,
            'status' => $options['status'] ?? 'draft',
            'qr_rotation_seconds' => $options['qr_rotation_seconds'] ?? config('attendance-engine.defaults.qr_rotation_seconds', 10),
            'qr_grace_windows' => $options['qr_grace_windows'] ?? config('attendance-engine.defaults.qr_grace_windows', 1),
            'location_required' => $options['location_required'] ?? false,
            'location_policy' => $options['location_policy'] ?? null,
            'metadata' => !empty($metadata) ? $metadata : null,
        ]);
    }

    public function open(string $sessionId): AttendanceSession
    {
        $session = AttendanceSession::findOrFail($sessionId);
        $session->forceFill(['status' => 'open'])->save();

        return $session;
    }

    public function close(string $sessionId): AttendanceSession
    {
        $session = AttendanceSession::findOrFail($sessionId);
        $session->forceFill(['status' => 'closed'])->save();

        return $session;
    }
}
