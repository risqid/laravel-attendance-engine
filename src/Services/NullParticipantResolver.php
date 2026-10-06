<?php

namespace Unwahas\AttendanceEngine\Services;

use Illuminate\Http\Request;
use Unwahas\AttendanceEngine\Contracts\AttendanceParticipant;
use Unwahas\AttendanceEngine\Contracts\ParticipantResolver;

class NullParticipantResolver implements ParticipantResolver
{
    public function resolve(Request $request): ?AttendanceParticipant
    {
        return null;
    }
}
