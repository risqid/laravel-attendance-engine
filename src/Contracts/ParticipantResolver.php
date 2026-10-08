<?php

namespace Risqid\AttendanceEngine\Contracts;

use Illuminate\Http\Request;

interface ParticipantResolver
{
    public function resolve(Request $request): ?AttendanceParticipant;
}
