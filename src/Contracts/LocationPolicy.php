<?php

namespace Risqid\AttendanceEngine\Contracts;

use Risqid\AttendanceEngine\Data\LocationValidationResult;
use Risqid\AttendanceEngine\Models\AttendanceSession;

interface LocationPolicy
{
    public function validate(
        AttendanceSession $session,
        AttendanceParticipant $participant,
        string $action,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracyMeter = null
    ): LocationValidationResult;
}
