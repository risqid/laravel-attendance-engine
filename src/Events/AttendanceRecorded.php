<?php

namespace Risqid\AttendanceEngine\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Risqid\AttendanceEngine\Models\AttendanceRecord;

class AttendanceRecorded
{
    use Dispatchable;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly AttendanceRecord $record)
    {
    }
}
