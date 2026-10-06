<?php

namespace Unwahas\AttendanceEngine\Data;

class QrChallenge
{
    public function __construct(
        public readonly array $payload,
        public readonly string $encodedPayload,
        public readonly int $expiresAt,
        public readonly int $window,
    ) {
    }
}
