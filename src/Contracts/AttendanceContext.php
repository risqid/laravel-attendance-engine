<?php

namespace Risqid\AttendanceEngine\Contracts;

use Carbon\CarbonInterface;

interface AttendanceContext
{
    public function getAttendanceContextType(): string;

    public function getAttendanceContextId(): string|int;

    public function getAttendanceContextName(): string;

    public function getAttendanceStartsAt(): CarbonInterface;

    public function getAttendanceEndsAt(): CarbonInterface;

    /**
     * @return array<int, string>
     */
    public function getAllowedAttendanceActions(): array;
}
