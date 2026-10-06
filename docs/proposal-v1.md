# Proposal Implementable Laravel Attendance Engine V1

**Status:** dokumen historis proposal V1. Package V1 sudah diimplementasikan sebagai repo lokal di `/home/debian/projects/laravel-attendance-engine`; README dan source code adalah sumber kebenaran current implementation.
**File:** `docs/proposal-v1.md`
**Input:** `docs/plan.md` dan `/home/debian/projects/sikawan/.hermes/plans/presensi-berbasis-dynamic-QR-Code.md`
**Batas current V1:** core package tidak menulis ke tabel presensi harian `presensis`; side effect presensi harian SIKAWAN ditunda/di luar implementasi saat ini.

> Catatan current implementation: package sudah dibuat dengan Composer name `unwahas/laravel-attendance-engine`. Optional HTTP route package saat ini minimal: `GET /sessions/{session}/challenge` dan `POST /scan`. Create/open/close/records/summary dipakai lewat service API atau host wrapper, bukan route package lengkap.

## 1. Ringkasan Keputusan V1

Laravel Attendance Engine V1 dibuat sebagai package Composer Laravel untuk presensi kegiatan berbasis dynamic QR Code. Host pertama adalah SIKAWAN, tetapi core package harus tetap generic untuk host lain seperti sistem akademik dan modul kedokteran.

V1 menyimpan presensi kegiatan secara terpisah dari presensi harian SIKAWAN. Package tidak menulis langsung ke tabel `presensis`. Jika kegiatan perlu memengaruhi presensi harian, SIKAWAN melakukannya lewat listener/service host yang eksplisit, teruji, dan idempotent.

Check-in dan check-out adalah action eksplisit. Session menentukan action yang diizinkan. Check-out hanya valid jika participant yang sama sudah memiliki check-in pada session yang sama.

## 2. Temuan Discovery SIKAWAN

- SIKAWAN memakai Laravel `^10.10`, PHP `^8.1`, PHPUnit 10, Sanctum tersedia, dan aplikasi memakai timezone `Asia/Jakarta`.
- ID domain utama memakai UUID string. Trait `App\Traits\UUID` menghasilkan UUIDv7 saat `creating`.
- Auth dan authorization route memakai middleware seperti `sso.auth`, `role.auth`, dan `menu.auth:<role>`. Role aktif disimpan di session.
- Presensi harian saat ini memakai tabel `presensis` dengan satu baris per pegawai per hari kerja/shift: kolom masuk wajib, kolom pulang nullable.
- Presensi harian bergantung pada `biodatas`, `shifts`, `shift_pegawais`, `shift_pegawai_dinamis`, `kampuses`, `presensi_switches`, dan `allowed_ips`.
- Presensi harian menyimpan foto, lat/lon, jarak, kampus masuk/pulang, IP, keterlambatan LKH/transport, dan pulang awal. Ini bukan tanggung jawab core package V1.
- Validasi lokasi harian memakai dua titik kampus hardcoded di helper: Kampus 1 radius 200 meter, Kampus 2 radius 1000 meter, dengan opsi pegawai boleh dua kampus.
- Filter IP harian dikendalikan `presensi_switches` bernama `IP`.
- Ada migration `after_production_1.php` yang menambah `is_allowed_both_kampus`, `kampus_id_masuk`, dan `kampus_id_pulang` secara guarded. Ini menandakan integrasi ke presensi harian perlu hati-hati terhadap riwayat schema produksi.
- Belum ditemukan model/tabel kegiatan presensi pegawai yang siap dipakai. Proposal host memakai tabel baru `kegiatans` dan `peserta_kegiatans`.

## 3. Boundary Final

### Package

Package bertanggung jawab atas:

- `attendance_sessions` generic;
- `attendance_records` generic;
- enum/value object `AttendanceAction` minimal `check_in` dan `check_out`;
- generator dan validator QR challenge yang signed dan stateless per rotation;
- service API untuk membuka/tutup session, menerbitkan QR, memproses scan, dan membaca hasil;
- validasi inti: signature, expiry/window, session aktif, action diizinkan, eligibility handoff, check-out setelah check-in, duplicate prevention;
- transaksi scan dan unique constraint;
- contracts untuk participant, context, eligibility, location policy, dan authorization handoff;
- event minimal setelah commit;
- HTTP API opsional tanpa ketergantungan ke Blade, React, Inertia, atau model SIKAWAN.

### Host SIKAWAN

SIKAWAN bertanggung jawab atas:

- tabel/model `kegiatans` dan `peserta_kegiatans`;
- peserta kegiatan dari `biodatas`;
- operator, halaman admin/operator, dan menu/role;
- pemilihan kampus/lokasi kegiatan;
- UI display QR dan UI scanner;
- adapter participant/context/eligibility/location/authorization;
- aturan dampak kegiatan ke presensi harian;
- listener/service rekonsiliasi ke `presensis` jika dibutuhkan;
- laporan kegiatan dan migration data lama jika ada.

## 4. Rancangan Tabel Host SIKAWAN

### `kegiatans`

Kolom:

- `id` uuid primary key.
- `nama` string.
- `deskripsi` text nullable.
- `tanggal_mulai` dateTime.
- `tanggal_selesai` dateTime.
- `kampus_id` uuid nullable, foreign ke `kampuses.id`.
- `lokasi_label` string nullable.
- `lat` decimal(10, 7) nullable.
- `lon` decimal(10, 7) nullable.
- `radius_meter` unsignedInteger nullable.
- `mode_presensi` string, contoh `check_in_only` atau `check_in_check_out`.
- `dampak_presensi_harian` string enum-like:
  - `none`: tidak mengganti presensi harian;
  - `check_in`: mengganti/pemenuhan presensi masuk saja;
  - `check_in_check_out`: mengganti/pemenuhan presensi masuk dan pulang.
- `attendance_session_id` uuid nullable, menyimpan referensi session package untuk lookup cepat.
- `status` string, contoh `draft`, `open`, `closed`, `cancelled`.
- `created_by` uuid nullable, mengarah ke `biodatas.id` sebagai operator pembuat jika sesuai pola SIKAWAN.
- soft deletes dan timestamps.

Index:

- index `tanggal_mulai`, `tanggal_selesai`;
- index `kampus_id`;
- index `attendance_session_id`;
- index `status`.

### `peserta_kegiatans`

Kolom:

- `id` uuid primary key.
- `kegiatan_id` uuid foreign ke `kegiatans.id`.
- `biodata_id` uuid foreign ke `biodatas.id`.
- `role_peserta` string nullable, contoh `peserta`, `panitia`, `narasumber`.
- `metadata` json nullable.
- soft deletes dan timestamps.

Constraint/index:

- unique aktif secara logis untuk `(kegiatan_id, biodata_id)`. Jika MySQL tidak mendukung partial unique bersama soft delete, V1 dapat memakai unique fisik `(kegiatan_id, biodata_id)` dan melarang duplicate setelah soft delete, atau menambah `deleted_at` dalam strategi host dengan konsekuensi MySQL.
- index `biodata_id`;
- index `kegiatan_id`.

## 5. Schema Package

Asumsi V1: package memakai UUID string sebagai default agar cocok dengan SIKAWAN, tetapi config menyediakan opsi `id_type` untuk host lain. Timestamp disimpan UTC di package; host menampilkan sesuai timezone aplikasi.

### `attendance_sessions`

Kolom:

- `id` uuid primary key.
- `context_type` string.
- `context_id` string.
- `name` string nullable.
- `starts_at` timestamp.
- `ends_at` timestamp.
- `timezone` string default dari config host.
- `allowed_actions` json, contoh `["check_in","check_out"]`.
- `status` string: `draft`, `open`, `closed`, `cancelled`.
- `qr_rotation_seconds` unsignedSmallInteger default 10.
- `qr_grace_windows` unsignedTinyInteger default 1.
- `location_required` boolean default false.
- `location_policy` json nullable.
- `metadata` json nullable.
- timestamps.

Index:

- index `(context_type, context_id)`;
- index `(status, starts_at, ends_at)`;
- index `starts_at`;
- index `ends_at`.

### `attendance_records`

Kolom:

- `id` uuid primary key.
- `attendance_session_id` uuid foreign ke `attendance_sessions.id`.
- `participant_type` string.
- `participant_id` string.
- `action` string.
- `recorded_at` timestamp, waktu server.
- `qr_window` bigint unsigned.
- `scan_lat` decimal(10, 7) nullable.
- `scan_lon` decimal(10, 7) nullable.
- `scan_accuracy_meter` unsignedInteger nullable.
- `scan_distance_meter` unsignedInteger nullable.
- `ip_address` string nullable.
- `user_agent` text nullable.
- `source` string default `qr`.
- `metadata` json nullable.
- timestamps.

Constraint/index:

- unique `(attendance_session_id, participant_type, participant_id, action)`;
- index `(attendance_session_id, action, recorded_at)`;
- index `(participant_type, participant_id)`;
- index `recorded_at`.

Tidak ada tabel QR rotation. QR bersifat deterministic dan divalidasi dari payload signed.

## 6. Contracts dan Resolver

Nama final namespace dapat disesuaikan saat implementasi, tetapi contract V1 harus mencakup:

- `AttendanceParticipant`: mengembalikan identifier, type, display name, dan optional metadata.
- `AttendanceContext`: mengembalikan identifier, type, name, start/end, allowed actions, dan metadata.
- `ParticipantResolver`: resolve participant dari authenticated user/request atau identifier scan. Untuk SIKAWAN: session user `id` menjadi `Biodata`.
- `ContextResolver`: resolve context dari `context_type/context_id`. Untuk SIKAWAN: `Kegiatan`.
- `EligibilityResolver`: memutuskan participant boleh scan session tertentu. Untuk SIKAWAN: ada record di `peserta_kegiatans`.
- `LocationPolicy`: menerima session, participant, action, lat/lon/accuracy, dan mengembalikan allowed/denied plus detail jarak. Default package: allow jika session tidak mewajibkan lokasi.
- `AttendanceAuthorization`: handoff otorisasi operator untuk open/close session, issue QR, dan read records. SIKAWAN tetap memakai middleware route untuk halaman.
- `DailyAttendanceImpactHandler` bukan contract core package; ini adapter/listener host SIKAWAN untuk dampak ke `presensis`.

Default aman: eligibility menolak jika resolver host tidak tersedia atau participant tidak bisa di-resolve.

## 7. Public Service API

Service utama package current V1:

- `AttendanceSessionService::createFromContext(AttendanceContext $context, array $options): AttendanceSession`
- `AttendanceSessionService::open(string|AttendanceSession $session): AttendanceSession`
- `AttendanceSessionService::close(string|AttendanceSession $session): AttendanceSession`
- `AttendanceQrService::issueChallenge(string|AttendanceSession $session, AttendanceAction|string $action, ?CarbonInterface $now = null): QrChallenge`
- `AttendanceScanService::scan(ScanRequestData $data): AttendanceScanResult`
- `AttendanceRecordService::forSession(string|AttendanceSession $session): Collection`

Planned-but-not-current V1: `AttendanceRecordService::summary()` dan paginator/filter records bawaan package.

`ScanRequestData` minimal memuat:

- QR payload string/array;
- participant resolver input, biasanya authenticated request;
- action eksplisit;
- optional lat/lon/accuracy;
- IP dan user agent dari server request.

`AttendanceScanResult` minimal memuat:

- `status`: `recorded`, `duplicate`, atau `rejected`;
- `record` nullable;
- `error_code` nullable;
- pesan aman untuk UI;
- metadata seperti server time dan action.

## 8. HTTP API Opsional

Package boleh menyediakan route opsional yang bisa dipublish/diaktifkan:

- Prefix default: `/attendance-engine`.
- Middleware default: `web`, `auth` atau configurable. Untuk SIKAWAN: host wrapper route memakai `sso.auth`, `role.auth`, dan bila perlu `menu.auth`.
- Rate limit default:
  - issue QR: `120 per minute` per operator/session;
  - scan: `30 per minute` per user/IP.

Endpoint opsional current V1:

- `GET /sessions/{session}/challenge?action=check_in`
- `POST /scan`

Planned/future endpoints, bila package nanti perlu HTTP API lengkap:

- `POST /sessions` create session dari context payload host.
- `POST /sessions/{session}/open`
- `POST /sessions/{session}/close`
- `GET /sessions/{session}/records`
- `GET /sessions/{session}/summary`

Pada integrasi SIKAWAN saat ini, create/open/close/records dilakukan melalui service package dan controller host, bukan route package langsung.

Error response stabil:

```json
{
  "ok": false,
  "code": "qr_expired",
  "message": "QR sudah kedaluwarsa.",
  "meta": {
    "server_time": "2026-09-28T08:00:00Z"
  }
}
```

Kode error V1: `invalid_payload`, `invalid_signature`, `qr_expired`, `session_not_open`, `action_not_allowed`, `participant_not_resolved`, `participant_not_eligible`, `location_required`, `location_denied`, `check_in_required`, `duplicate_scan`, `rate_limited`.

## 9. Format QR Payload

Payload V1:

```json
{
  "v": 1,
  "sid": "session-uuid",
  "act": "check_in",
  "win": 178000000,
  "exp": 1780000010,
  "sig": "base64url-hmac"
}
```

Signing:

- canonical string: `v|sid|act|win|exp`;
- signature: HMAC-SHA256 dengan secret dari config package;
- compare memakai constant-time comparison;
- secret default berasal dari `ATTENDANCE_ENGINE_SECRET`, fallback aman dapat memakai `APP_KEY` hanya jika secret eksplisit belum diset.

Rotation:

- default `qr_rotation_seconds = 10`;
- grace menerima current dan previous window (`qr_grace_windows = 1`);
- tidak menyimpan setiap QR ke database;
- server memakai waktu server, bukan waktu client;
- action masuk payload agar QR check-in tidak bisa dipakai untuk check-out.

Risiko realistis: dynamic QR mengurangi replay kode lama, tetapi tidak sepenuhnya mencegah titip presensi jika payload dibagikan dalam window aktif. Mitigasi V1 adalah expiry pendek, rate limit, eligibility, optional lokasi, audit metadata, dan UI operator yang menampilkan QR hanya pada session aktif.

## 10. Flow Scan

1. UI scanner mengirim QR payload, action, dan optional lokasi.
2. Server parse payload dan validasi versi.
3. Server validasi signature untuk `sid`, `act`, `win`, dan `exp`.
4. Server validasi expiry/window memakai waktu server dan grace.
5. Server mengambil session dan memastikan status `open`, waktu session aktif, dan action diizinkan.
6. Participant di-resolve dari request/auth host.
7. Eligibility resolver memastikan participant boleh hadir di session/context.
8. Location policy dijalankan jika session mewajibkan lokasi.
9. Untuk `check_out`, package memastikan record `check_in` sudah ada.
10. Dalam transaksi database, package insert `attendance_records`.
11. Unique constraint menangani duplicate/race. Duplicate dikembalikan sebagai status stabil, bukan error 500.
12. Setelah commit, package dispatch event `AttendanceRecorded`.

## 11. Event dan Side Effect

Event package minimal:

- `AttendanceRecorded`, after commit, membawa session id, participant reference, action, record id, recorded_at, dan context reference.
- `AttendanceScanRejected` opsional untuk audit ringan, tetapi V1 dapat cukup memakai log host jika diperlukan.

Untuk SIKAWAN current V1:

- Listener side effect ke `presensis` tidak diimplementasikan.
- Tidak ada penulisan ke tabel presensi harian `presensis`.
- Event `AttendanceRecorded` tersedia untuk extension host di masa depan.

Future-only jika side effect presensi harian disetujui terpisah:

- Listener host menerima `AttendanceRecorded`.
- Jika `kegiatans.dampak_presensi_harian = none`, listener tidak melakukan apa-apa.
- Jika `check_in`, listener membuat/menandai pemenuhan masuk harian secara idempotent.
- Jika `check_in_check_out`, listener memetakan check-in kegiatan ke masuk dan check-out kegiatan ke pulang.
- Tambahkan tabel host `kegiatan_presensi_harian_impacts` dengan unique `(attendance_record_id, impact_type)` sebelum menulis `presensis`.

Default current V1: side effect presensi harian nonaktif dan di luar scope implementasi.

## 12. Integrasi SIKAWAN

Adapter yang perlu dibuat setelah proposal disetujui:

- `SikawanAttendanceParticipant` untuk `Biodata`.
- `SikawanParticipantResolver` dari `session('user.id')`.
- `KegiatanAttendanceContext` untuk `Kegiatan`.
- `KegiatanContextResolver` dari `kegiatans`.
- `KegiatanEligibilityResolver` dari `peserta_kegiatans`.
- `KegiatanLocationPolicy` yang dapat memakai `kegiatans.kampus_id`, `lat/lon/radius_meter`, atau default kampus SIKAWAN.
- `KegiatanAuthorization` yang membaca role/menu SIKAWAN untuk operator.
- `KegiatanDailyAttendanceImpactListener` untuk dampak opsional ke `presensis`.

UI host:

- Halaman admin/operator membuat kegiatan, peserta, dan session.
- Halaman display QR memanggil challenge endpoint berkala sesuai rotation.
- Halaman scanner peserta mengirim payload QR ke backend.
- Halaman rekap kegiatan membaca records dari package dan menggabungkan nama dari `biodatas`.

Presensi harian lama tetap berjalan di `/user/presensi` dan tidak diganti oleh fitur kegiatan.

## 13. Mapping Requirement

Package:

- attendance session generic;
- participant/context reference generic;
- action check-in/check-out;
- QR signed stateless;
- expiry/grace/rotation;
- duplicate prevention;
- transaction scan;
- event after commit;
- service API dan HTTP API opsional.

Host SIKAWAN:

- model kegiatan dan peserta;
- role/operator/menu;
- UI Blade saat ini atau UI lain nanti;
- kampus/lokasi kegiatan;
- eligibility peserta;
- dampak kegiatan ke presensi harian;
- laporan dan rekap domain.

Di luar V1:

- foto presensi kegiatan;
- survei;
- NFC;
- biometrik;
- fingerprint perangkat;
- fraud detection kompleks;
- pelaporan besar lintas unit;
- migrasi data presensi lama;
- penggantian presensi harian SIKAWAN.

## 14. Test Plan V1

Package tests:

- QR challenge valid untuk current window.
- QR expired ditolak.
- Previous window diterima saat grace aktif.
- Signature salah ditolak.
- Action payload berbeda dari request ditolak.
- Session closed/outside time ditolak.
- Action tidak diizinkan ditolak.
- Participant tidak ter-resolve ditolak.
- Participant tidak eligible ditolak.
- Location required tanpa lokasi ditolak.
- Check-out tanpa check-in ditolak.
- Duplicate scan mengembalikan `duplicate`.
- Race condition dua scan paralel hanya menghasilkan satu record karena unique constraint.
- Event `AttendanceRecorded` dikirim after commit.

SIKAWAN integration tests:

- Kegiatan `check_in_only` membuat session dengan allowed action `check_in`.
- Kegiatan `check_in_check_out` membuat session dengan dua action.
- Peserta di `peserta_kegiatans` bisa scan; non-peserta ditolak.
- Lokasi kampus opsional bekerja sesuai kebijakan kegiatan.
- Dampak `none` tidak menulis `presensis`.
- Dampak `check_in` idempotent terhadap `presensis`.
- Dampak `check_in_check_out` idempotent dan tidak membuat duplicate presensi harian.
- Presensi harian existing tetap dapat dipakai tanpa attendance package.

## 15. Risiko dan Batas V1

- Dynamic QR tidak mencegah pembagian kode dalam window aktif.
- Lokasi browser/mobile dapat dipalsukan; V1 hanya validasi jarak basic jika host mengaktifkannya.
- Schema presensi harian SIKAWAN punya riwayat migration produksi, sehingga side effect harus kecil, terisolasi, dan mudah dimatikan.
- Contract generic perlu dijaga agar tidak bocor konsep `Biodata`, `Kegiatan`, `Kampus`, atau `Shift`.
- HTTP API package harus opsional karena SIKAWAN memakai pola route dan middleware host.
- Jika calon host akademik/kedokteran belum tersedia, asumsi V1: context mereka adalah session/pertemuan dan participant mereka adalah mahasiswa.

## 16. Keputusan Manusia Sebelum Coding

Tidak ada blocker faktual untuk mulai implementasi setelah proposal disetujui.

Dua keputusan yang sebaiknya dikonfirmasi sebelum coding:

1. Nama package Composer final dan namespace vendor, misalnya `unwahas/laravel-attendance-engine`.
2. Apakah side effect kegiatan ke presensi harian SIKAWAN masuk batch implementasi V1 awal atau ditunda setelah engine kegiatan stabil. Default aman: ditunda/nonaktif.

## 17. Kriteria Selesai Implementasi V1

- Package bisa membuat session, issue QR, scan check-in/check-out, dan menyimpan record.
- Unique constraint mencegah duplicate scan.
- Event after commit tersedia.
- SIKAWAN memiliki `kegiatans` dan `peserta_kegiatans`.
- SIKAWAN bisa menjalankan flow kegiatan tanpa mengganggu `/user/presensi`.
- Dampak ke presensi harian hanya terjadi jika listener host diaktifkan.
- Test package dan integrasi host menutup flow sukses, expired, duplicate, non-eligible, check-out tanpa check-in, dan side effect idempotent jika side effect diaktifkan.
