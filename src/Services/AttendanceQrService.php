<?php

namespace Risqid\AttendanceEngine\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Risqid\AttendanceEngine\Data\QrChallenge;
use Risqid\AttendanceEngine\Enums\AttendanceAction;
use Risqid\AttendanceEngine\Models\AttendanceSession;

class AttendanceQrService
{
    public function issueChallenge(
        string $sessionId,
        AttendanceAction|string $action,
        ?CarbonInterface $now = null
    ): QrChallenge {
        $session = AttendanceSession::findOrFail($sessionId);
        $action = AttendanceAction::normalize($action)->value;

        if (!$session->allowsAction($action)) {
            throw new InvalidArgumentException('action_not_allowed');
        }

        $now ??= Carbon::now();
        $rotationSeconds = max(1, (int) $session->qr_rotation_seconds);
        $window = intdiv($now->getTimestamp(), $rotationSeconds);
        $expiresAt = ($window + 1) * $rotationSeconds;

        $actionCode = match ($action) {
            AttendanceAction::CheckIn->value => 1,
            AttendanceAction::CheckOut->value => 2,
            default => throw new InvalidArgumentException('action_not_allowed'),
        };

        $cleanUuid = str_replace('-', '', (string) $session->id);
        if (strlen($cleanUuid) === 32 && ctype_xdigit($cleanUuid)) {
            // v2 Compact Binary format (24 bytes -> 32 base64url characters)
            $header = pack('C', (2 << 4) | $actionCode);
            $sessionBytes = hex2bin($cleanUuid);
            $winBytes = pack('N', $window);
            $signedData = $header . $sessionBytes . $winBytes;
            $sig = substr(hash_hmac('sha256', $signedData, $this->secret(), true), 0, 3);
            $binaryPayload = $signedData . $sig;
            $encodedPayload = $this->base64UrlEncode($binaryPayload);
        } else {
            $encodedPayload = null;
        }

        $payload = [
            'v' => 1,
            'sid' => $session->id,
            'act' => $action,
            'win' => $window,
            'exp' => $expiresAt,
        ];
        $payload['sig'] = $this->sign($payload);

        $encodedPayload ??= $this->encodePayload($payload);

        return new QrChallenge($payload, $encodedPayload, $expiresAt, $window);
    }

    public function validatePayload(array|string $payload, ?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();

        if (is_string($payload)) {
            // Check if string is compact v2 binary payload (24 bytes = 32 base64url chars)
            $binary = base64_decode(strtr($payload, '-_', '+/'), true);
            if ($binary !== false && strlen($binary) === 24) {
                return $this->validateCompactV2($binary, $now);
            }

            // Otherwise, decode legacy v1 JSON
            $payload = $this->decodePayload($payload);
        }

        if (isset($payload['v']) && (int) $payload['v'] === 2) {
            return $this->validateArrayV2($payload, $now);
        }

        return $this->validateLegacyV1($payload, $now);
    }

    private function validateCompactV2(string $binary, CarbonInterface $now): array
    {
        $header = unpack('C', substr($binary, 0, 1))[1];
        $version = ($header >> 4) & 0x0F;
        $actionCode = $header & 0x0F;

        if ($version !== 2) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $action = match ($actionCode) {
            1 => AttendanceAction::CheckIn->value,
            2 => AttendanceAction::CheckOut->value,
            default => throw new InvalidArgumentException('action_not_allowed'),
        };

        $sessionBytes = substr($binary, 1, 16);
        $hex = bin2hex($sessionBytes);
        $sessionId = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        $window = unpack('N', substr($binary, 17, 4))[1];

        $signedData = substr($binary, 0, 21);
        $expectedSig = substr(hash_hmac('sha256', $signedData, $this->secret(), true), 0, 3);
        $actualSig = substr($binary, 21, 3);

        if (!hash_equals($expectedSig, $actualSig)) {
            throw new InvalidArgumentException('invalid_signature');
        }

        try {
            $session = AttendanceSession::findOrFail($sessionId);
        } catch (ModelNotFoundException) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $rotationSeconds = max(1, (int) $session->qr_rotation_seconds);
        $currentWindow = intdiv($now->getTimestamp(), $rotationSeconds);
        $grace = max(0, (int) $session->qr_grace_windows);
        $expiresAt = ($window + 1) * $rotationSeconds;

        if ($window > $currentWindow || $window < $currentWindow - $grace) {
            throw new InvalidArgumentException('qr_expired');
        }

        $payload = [
            'v' => 2,
            'sid' => $session->id,
            'act' => $action,
            'win' => $window,
            'exp' => $expiresAt,
            'sig' => bin2hex($actualSig),
        ];

        return [$payload, $session];
    }

    private function validateArrayV2(array $payload, CarbonInterface $now): array
    {
        foreach (['v', 'sid', 'act', 'win', 'exp', 'sig'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidArgumentException('invalid_payload');
            }
        }

        $cleanUuid = str_replace('-', '', (string) $payload['sid']);
        if (strlen($cleanUuid) !== 32 || !ctype_xdigit($cleanUuid)) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $actionCode = match ($payload['act']) {
            AttendanceAction::CheckIn->value => 1,
            AttendanceAction::CheckOut->value => 2,
            default => throw new InvalidArgumentException('action_not_allowed'),
        };

        $header = pack('C', (2 << 4) | $actionCode);
        $signedData = $header . hex2bin($cleanUuid) . pack('N', (int) $payload['win']);
        $expectedSig = substr(hash_hmac('sha256', $signedData, $this->secret(), true), 0, 3);
        $actualSig = @hex2bin((string) $payload['sig']);

        if (!$actualSig || !hash_equals($expectedSig, $actualSig)) {
            throw new InvalidArgumentException('invalid_signature');
        }

        try {
            $session = AttendanceSession::findOrFail((string) $payload['sid']);
        } catch (ModelNotFoundException) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $rotationSeconds = max(1, (int) $session->qr_rotation_seconds);
        $currentWindow = intdiv($now->getTimestamp(), $rotationSeconds);
        $payloadWindow = (int) $payload['win'];
        $grace = max(0, (int) $session->qr_grace_windows);

        if ($payloadWindow > $currentWindow || $payloadWindow < $currentWindow - $grace) {
            throw new InvalidArgumentException('qr_expired');
        }

        return [$payload, $session];
    }

    private function validateLegacyV1(array $payload, CarbonInterface $now): array
    {
        foreach (['v', 'sid', 'act', 'win', 'exp', 'sig'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidArgumentException('invalid_payload');
            }
        }

        if ((int) $payload['v'] !== 1) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $expected = $this->sign($payload);
        if (!hash_equals($expected, (string) $payload['sig'])) {
            throw new InvalidArgumentException('invalid_signature');
        }

        try {
            $session = AttendanceSession::findOrFail((string) $payload['sid']);
        } catch (ModelNotFoundException) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $rotationSeconds = max(1, (int) $session->qr_rotation_seconds);
        $currentWindow = intdiv($now->getTimestamp(), $rotationSeconds);
        $payloadWindow = (int) $payload['win'];
        $grace = max(0, (int) $session->qr_grace_windows);

        if ((int) $payload['exp'] < $now->getTimestamp() && $payloadWindow < $currentWindow - $grace) {
            throw new InvalidArgumentException('qr_expired');
        }

        if ($payloadWindow > $currentWindow || $payloadWindow < $currentWindow - $grace) {
            throw new InvalidArgumentException('qr_expired');
        }

        return [$payload, $session];
    }

    private function sign(array $payload): string
    {
        $data = implode('|', [
            (string) ($payload['v'] ?? ''),
            (string) ($payload['sid'] ?? ''),
            (string) ($payload['act'] ?? ''),
            (string) ($payload['win'] ?? ''),
            (string) ($payload['exp'] ?? ''),
        ]);

        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret(), true));
    }

    private function encodePayload(array $payload): string
    {
        return $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function decodePayload(string $payload): array
    {
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            throw new InvalidArgumentException('invalid_payload');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('invalid_payload');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function secret(): string
    {
        $secret = (string) config('attendance-engine.secret', '');

        if (str_starts_with($secret, 'base64:')) {
            return substr($secret, 7);
        }

        return $secret;
    }
}
