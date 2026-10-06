<?php

namespace Unwahas\AttendanceEngine\Contracts;

use Unwahas\AttendanceEngine\Models\AttendanceSession;

interface EligibilityResolver
{
    public function isEligible(AttendanceSession $session, AttendanceParticipant $participant): bool;
}
