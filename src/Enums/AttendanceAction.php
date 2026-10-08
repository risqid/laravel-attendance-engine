<?php

namespace Risqid\AttendanceEngine\Enums;

enum AttendanceAction: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';

    public static function normalize(self|string $action): self
    {
        return $action instanceof self ? $action : self::from($action);
    }
}
