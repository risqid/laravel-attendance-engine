<?php

namespace Risqid\AttendanceEngine\Services;

use Illuminate\Http\Request;
use Risqid\AttendanceEngine\Contracts\AttendanceParticipant;
use Risqid\AttendanceEngine\Contracts\ParticipantResolver;

class NullParticipantResolver implements ParticipantResolver
{
    public function resolve(Request $request): ?AttendanceParticipant
    {
        return null;
    }
}
