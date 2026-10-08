<?php

namespace Risqid\AttendanceEngine\Services;

use Risqid\AttendanceEngine\Contracts\AttendanceParticipant;
use Risqid\AttendanceEngine\Contracts\EligibilityResolver;
use Risqid\AttendanceEngine\Models\AttendanceSession;

class DenyAllEligibilityResolver implements EligibilityResolver
{
    public function isEligible(AttendanceSession $session, AttendanceParticipant $participant): bool
    {
        return false;
    }
}
