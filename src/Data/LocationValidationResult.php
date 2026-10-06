<?php

namespace Unwahas\AttendanceEngine\Data;

class LocationValidationResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?int $distanceMeter = null,
        public readonly ?string $message = null,
        public readonly array $metadata = [],
    ) {
    }

    public static function allowed(?int $distanceMeter = null, array $metadata = []): self
    {
        return new self(true, $distanceMeter, null, $metadata);
    }

    public static function denied(string $message, ?int $distanceMeter = null, array $metadata = []): self
    {
        return new self(false, $distanceMeter, $message, $metadata);
    }
}
