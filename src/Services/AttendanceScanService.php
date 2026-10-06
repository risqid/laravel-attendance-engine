<?php

namespace Unwahas\AttendanceEngine\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Unwahas\AttendanceEngine\Contracts\EligibilityResolver;
use Unwahas\AttendanceEngine\Contracts\LocationPolicy;
use Unwahas\AttendanceEngine\Contracts\ParticipantResolver;
use Unwahas\AttendanceEngine\Data\AttendanceScanResult;
use Unwahas\AttendanceEngine\Data\ScanRequestData;
use Unwahas\AttendanceEngine\Enums\AttendanceAction;
use Unwahas\AttendanceEngine\Events\AttendanceRecorded;
use Unwahas\AttendanceEngine\Models\AttendanceRecord;

class AttendanceScanService
{
    public function __construct(
        private readonly AttendanceQrService $qr,
        private readonly ParticipantResolver $participants,
        private readonly EligibilityResolver $eligibility,
        private readonly LocationPolicy $locations,
    ) {
    }

    public function scan(ScanRequestData $data): AttendanceScanResult
    {
        try {
            [$payload, $session] = $this->qr->validatePayload($data->payload);
        } catch (InvalidArgumentException $e) {
            return AttendanceScanResult::rejected($e->getMessage(), $this->messageFor($e->getMessage()));
        }

        $now = Carbon::now();
        $action = (string) $payload['act'];

        if (!$session->isOpenAt($now)) {
            return AttendanceScanResult::rejected('session_not_open', 'Sesi presensi tidak aktif.');
        }

        if (!$session->allowsAction($action)) {
            return AttendanceScanResult::rejected('action_not_allowed', 'Action presensi tidak diizinkan.');
        }

        if (!$session->isActionWindowOpenAt($action, $now)) {
            return AttendanceScanResult::rejected('action_window_not_open', 'Waktu presensi untuk action ini belum aktif atau sudah berakhir.');
        }

        $participant = $this->participants->resolve($data->request);
        if (!$participant) {
            return AttendanceScanResult::rejected('participant_not_resolved', 'Peserta tidak dapat dikenali.');
        }

        if (!$this->eligibility->isEligible($session, $participant)) {
            return AttendanceScanResult::rejected('participant_not_eligible', 'Peserta tidak terdaftar pada sesi ini.');
        }

        $location = $this->locations->validate(
            $session,
            $participant,
            $action,
            $data->latitude,
            $data->longitude,
            $data->accuracyMeter
        );

        if (!$location->allowed) {
            return AttendanceScanResult::rejected('location_denied', $location->message ?? 'Lokasi tidak valid.', $location->metadata);
        }

        if ($action === AttendanceAction::CheckOut->value && $session->requiresCheckInForCheckOut()) {
            $hasCheckIn = AttendanceRecord::where('attendance_session_id', $session->id)
                ->where('participant_type', $participant->getAttendanceParticipantType())
                ->where('participant_id', (string) $participant->getAttendanceParticipantId())
                ->where('action', AttendanceAction::CheckIn->value)
                ->exists();

            if (!$hasCheckIn) {
                return AttendanceScanResult::rejected('check_in_required', 'Check-out membutuhkan check-in terlebih dahulu.');
            }
        }

        try {
            $record = DB::transaction(function () use ($session, $participant, $action, $payload, $data, $location, $now) {
                return AttendanceRecord::create([
                    'attendance_session_id' => $session->id,
                    'participant_type' => $participant->getAttendanceParticipantType(),
                    'participant_id' => (string) $participant->getAttendanceParticipantId(),
                    'action' => $action,
                    'recorded_at' => $now,
                    'qr_window' => (int) $payload['win'],
                    'scan_lat' => $data->latitude,
                    'scan_lon' => $data->longitude,
                    'scan_accuracy_meter' => $data->accuracyMeter,
                    'scan_distance_meter' => $location->distanceMeter,
                    'ip_address' => $data->request->ip(),
                    'user_agent' => $data->request->userAgent(),
                    'source' => 'qr',
                    'metadata' => [
                        'location' => $location->metadata,
                    ],
                ]);
            });
        } catch (QueryException $e) {
            if (!$this->isDuplicateException($e)) {
                throw $e;
            }

            $record = AttendanceRecord::where('attendance_session_id', $session->id)
                ->where('participant_type', $participant->getAttendanceParticipantType())
                ->where('participant_id', (string) $participant->getAttendanceParticipantId())
                ->where('action', $action)
                ->firstOrFail();

            return AttendanceScanResult::duplicate($record);
        }

        AttendanceRecorded::dispatch($record);

        return AttendanceScanResult::recorded($record);
    }

    private function isDuplicateException(QueryException $e): bool
    {
        return (string) ($e->errorInfo[0] ?? '') === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }

    private function messageFor(string $code): string
    {
        return match ($code) {
            'invalid_payload' => 'Payload QR tidak valid.',
            'invalid_signature' => 'Signature QR tidak valid.',
            'qr_expired' => 'QR sudah kedaluwarsa.',
            default => 'Scan ditolak.',
        };
    }
}
