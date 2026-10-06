<?php

namespace Unwahas\AttendanceEngine\Data;

use Illuminate\Http\Request;

class ScanRequestData
{
    public function __construct(
        public readonly array|string $payload,
        public readonly Request $request,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $accuracyMeter = null,
    ) {
    }
}
