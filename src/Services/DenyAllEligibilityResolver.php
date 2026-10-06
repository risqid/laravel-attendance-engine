<?php

namespace Unwahas\AttendanceEngine\Services;

use Unwahas\AttendanceEngine\Contracts\AttendanceParticipant;
use Unwahas\AttendanceEngine\Contracts\EligibilityResolver;
use Unwahas\AttendanceEngine\Models\AttendanceSession;

class DenyAllEligibilityResolver implements EligibilityResolver
{
    public function isEligible(AttendanceSession $session, AttendanceParticipant $participant): bool
    {
        return false;
    }
}
