<?php

namespace Risqid\AttendanceEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Risqid\AttendanceEngine\Services\AttendanceQrService;

class AttendanceChallengeController extends Controller
{
    public function __invoke(Request $request, string $session, AttendanceQrService $qr): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string'],
        ]);

        try {
            $challenge = $qr->issueChallenge($session, $validated['action']);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'code' => $e->getMessage(),
                'message' => 'QR challenge tidak dapat diterbitkan.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'payload' => $challenge->payload,
            'encoded_payload' => $challenge->encodedPayload,
            'expires_at' => $challenge->expiresAt,
            'window' => $challenge->window,
        ]);
    }
}
