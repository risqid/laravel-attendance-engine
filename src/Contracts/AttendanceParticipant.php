<?php

namespace Unwahas\AttendanceEngine\Contracts;

interface AttendanceParticipant
{
    public function getAttendanceParticipantType(): string;

    public function getAttendanceParticipantId(): string|int;

    public function getAttendanceParticipantDisplayName(): string;
}
