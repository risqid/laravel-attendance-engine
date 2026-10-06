<?php

namespace Unwahas\AttendanceEngine\Contracts;

use Unwahas\AttendanceEngine\Data\LocationValidationResult;
use Unwahas\AttendanceEngine\Models\AttendanceSession;

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
