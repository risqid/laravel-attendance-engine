<?php

namespace Risqid\AttendanceEngine\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Risqid\AttendanceEngine\Models\AttendanceRecord;

class AttendanceRecordService
{
    public function forSession(string $sessionId, array $filters = []): Collection|LengthAwarePaginator
    {
        $query = AttendanceRecord::where('attendance_session_id', $sessionId)
            ->orderByDesc('recorded_at');

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['paginate'])) {
            return $query->paginate((int) ($filters['per_page'] ?? 15));
        }

        return $query->get();
    }

    public function summary(string $sessionId): array
    {
        return AttendanceRecord::where('attendance_session_id', $sessionId)
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->all();
    }
}
