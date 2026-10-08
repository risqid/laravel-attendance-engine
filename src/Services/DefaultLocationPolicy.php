<?php

namespace Risqid\AttendanceEngine\Services;

use Risqid\AttendanceEngine\Contracts\AttendanceParticipant;
use Risqid\AttendanceEngine\Contracts\LocationPolicy;
use Risqid\AttendanceEngine\Data\LocationValidationResult;
use Risqid\AttendanceEngine\Models\AttendanceSession;

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
