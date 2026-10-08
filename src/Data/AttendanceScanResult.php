<?php

namespace Risqid\AttendanceEngine\Data;

use Risqid\AttendanceEngine\Models\AttendanceRecord;

class AttendanceScanResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?AttendanceRecord $record = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
        public readonly array $meta = [],
    ) {
    }

    public static function recorded(AttendanceRecord $record): self
    {
        return new self('recorded', $record, null, 'Presensi berhasil dicatat.');
    }

    public static function duplicate(AttendanceRecord $record): self
    {
        return new self('duplicate', $record, 'duplicate_scan', 'Presensi sudah pernah dicatat.');
    }

    public static function rejected(string $code, string $message, array $meta = []): self
    {
        return new self('rejected', null, $code, $message, $meta);
    }
}
