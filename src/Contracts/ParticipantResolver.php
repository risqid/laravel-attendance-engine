<?php

namespace Unwahas\AttendanceEngine\Contracts;

use Illuminate\Http\Request;

interface ParticipantResolver
{
    public function resolve(Request $request): ?AttendanceParticipant;
}
