<?php

namespace Unwahas\AttendanceEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Unwahas\AttendanceEngine\Data\ScanRequestData;
use Unwahas\AttendanceEngine\Services\AttendanceScanService;

class AttendanceScanController extends Controller
{
    public function __invoke(Request $request, AttendanceScanService $scanner): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required'],
            'lat' => ['nullable', 'numeric'],
            'lon' => ['nullable', 'numeric'],
            'accuracy_meter' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $scanner->scan(new ScanRequestData(
            $validated['payload'],
            $request,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lon']) ? (float) $validated['lon'] : null,
            $validated['accuracy_meter'] ?? null
        ));

        return response()->json([
            'ok' => $result->status === 'recorded' || $result->status === 'duplicate',
            'status' => $result->status,
            'code' => $result->errorCode,
            'message' => $result->message,
            'record' => $result->record,
            'meta' => $result->meta,
        ], $result->status === 'rejected' ? 422 : 200);
    }
}
