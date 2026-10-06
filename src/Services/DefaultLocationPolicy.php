<?php

namespace Unwahas\AttendanceEngine\Services;

use Unwahas\AttendanceEngine\Contracts\AttendanceParticipant;
use Unwahas\AttendanceEngine\Contracts\LocationPolicy;
use Unwahas\AttendanceEngine\Data\LocationValidationResult;
use Unwahas\AttendanceEngine\Models\AttendanceSession;

class DefaultLocationPolicy implements LocationPolicy
{
    public function validate(
        AttendanceSession $session,
        AttendanceParticipant $participant,
        string $action,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracyMeter = null
    ): LocationValidationResult {
        if ($session->location_required && ($latitude === null || $longitude === null)) {
            return LocationValidationResult::denied('Lokasi wajib dikirim.');
        }

        return LocationValidationResult::allowed();
    }
}
