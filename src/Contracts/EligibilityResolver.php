<?php

namespace Risqid\AttendanceEngine\Contracts;

use Risqid\AttendanceEngine\Models\AttendanceSession;

interface EligibilityResolver
{
    public function isEligible(AttendanceSession $session, AttendanceParticipant $participant): bool;
}
