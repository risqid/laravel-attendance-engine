# Laravel Attendance Engine

Reusable Laravel package for session-based attendance using stateless dynamic QR challenges and configurable action windows.

---

## Features

- **Stateless Dynamic QR Challenges:** HMAC-SHA256 signed payloads validated against server time windows without storing rotating QR codes in the database.
- **Compact 32-Character Binary Payload (v2 Default):** 24-byte packed binary payload (`1B header [version 2 << 4 | actionCode] + 16B raw binary UUID session ID + 4B big-endian uint32 window + 3B truncated HMAC-SHA256`) encoded as exactly 32 characters Base64URL (no padding). Fits dense, high-contrast QR Version 2 (25x25 grid) for fast camera detection on mobile screens.
- **Legacy Payload Support (v1):** Backward-compatible with JSON string payload (`v`, `sid`, `act`, `win`, `exp`, `sig`).
- **Granular Action Windows:** Independent `check_in` and `check_out` scan windows inside a broader session lifecycle.
- **Event-Driven Architecture:** Dispatches `AttendanceRecorded` event for host-side reconciliation (e.g. daily attendance bundling, audit logs, notifications).
- **Decoupled Architecture:** 100% generic. Host systems provide their own participant models, authorization, and location rules via contracts.

---

## Requirements

- PHP `^8.1`
- Laravel / Illuminate `^10.10|^11.0|^12.0`

---

## Installation

Install via Composer:

```bash
composer require risqid/laravel-attendance-engine
```

*(Or via GitHub VCS repository if hosted privately)*

Publish and run migrations:

```bash
php artisan vendor:publish --tag=attendance-engine-migrations
php artisan migrate
```

Publish configuration (optional):

```bash
php artisan vendor:publish --tag=attendance-engine-config
```

---

## Environment Configuration

Configure secret key in `.env`:

```env
ATTENDANCE_ENGINE_SECRET=your-secure-random-secret
```

If `ATTENDANCE_ENGINE_SECRET` is not set, the package falls back to Laravel's `APP_KEY`.

---

## Database Tables

- `attendance_sessions`: stores session lifecycle, secret rotation metadata, and `action_windows`.
- `attendance_records`: stores unique attendance transactions per `(attendance_session_id, participant_type, participant_id, action)`.

---

## Core Contracts

Host applications implement contracts to integrate domain logic:

| Contract | Purpose |
| :--- | :--- |
| `Risqid\AttendanceEngine\Contracts\ParticipantResolver` | Resolves authenticated user to participant identity. |
| `Risqid\AttendanceEngine\Contracts\EligibilityResolver` | Verifies whether a participant is authorized for the session. |
| `Risqid\AttendanceEngine\Contracts\LocationPolicy` | Validates client IP or GPS coordinates against location rules. |
| `Risqid\AttendanceEngine\Contracts\AttendanceContext` | Optional host model representation for attendance context. |
| `Risqid\AttendanceEngine\Contracts\AttendanceParticipant` | Optional host model representation for participants. |

---

## Basic Usage

### 1. Creating a Session

```php
use Risqid\AttendanceEngine\Services\AttendanceSessionService;

$sessionService = app(AttendanceSessionService::class);

$session = $sessionService->createFromContext([
    'name' => 'Rapat Koordinasi',
    'starts_at' => now(),
    'ends_at' => now()->addHours(4),
    'qr_rotation_seconds' => 15,
    'action_windows' => [
        'check_in' => [
            'from' => now()->toIso8601String(),
            'until' => now()->addHour()->toIso8601String(),
        ],
        'check_out' => [
            'from' => now()->addHours(3)->toIso8601String(),
            'until' => now()->addHours(4)->toIso8601String(),
        ],
    ],
]);
```

### 2. Generating Dynamic QR Challenge

```php
use Risqid\AttendanceEngine\Services\AttendanceQrService;

$qrService = app(AttendanceQrService::class);

// Returns QrChallenge with payload (32-char binary Base64URL by default), expiry, and remaining seconds
$challenge = $qrService->issueChallenge($session, 'check_in');
$payload = $challenge->payload;
```

### 3. Scanning and Recording Attendance

```php
use Risqid\AttendanceEngine\Services\AttendanceScanService;
use Risqid\AttendanceEngine\Data\ScanRequestData;

$scanService = app(AttendanceScanService::class);

$result = $scanService->recordScan(new ScanRequestData(
    payload: $payload,
    participantId: auth()->id(),
    participantType: get_class(auth()->user()),
    ipAddress: request()->ip(),
    latitude: request()->input('latitude'),
    longitude: request()->input('longitude')
));

if ($result->isSuccess()) {
    // Attendance successfully recorded
}
```

### 4. Listening to Attendance Events

```php
use Risqid\AttendanceEngine\Events\AttendanceRecorded;

Event::listen(AttendanceRecorded::class, function (AttendanceRecorded $event) {
    $record = $event->record;
    // Execute host-specific side effects (e.g. bundling daily attendance)
});
```

---

## License

The MIT License (MIT).
