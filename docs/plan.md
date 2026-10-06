# Plan Laravel Attendance Engine

**Status:** dokumen historis perencanaan. Package V1 sudah diimplementasikan sebagai repo lokal di `/home/debian/projects/laravel-attendance-engine`; README dan source code adalah sumber kebenaran current implementation.
**File ini:** `docs/plan.md`
**Sumber kebutuhan host pertama:** `/home/debian/projects/sikawan/.hermes/plans/presensi-berbasis-dynamic-QR-Code.md`

> Catatan current state: instruksi pada dokumen ini yang melarang pembuatan repo/package atau penulisan kode produksi adalah instruksi historis tahap planning, bukan instruksi aktif setelah implementasi V1. Current V1 tetap tanpa side effect ke tabel presensi harian `presensis`.

## 1. Tujuan

Bangun rencana package Composer Laravel bernama kerja **Laravel Attendance Engine** untuk menangani presensi kegiatan berbasis dynamic QR Code secara reusable.

V1 fokus pada kebutuhan nyata SIKAWAN: presensi kegiatan pegawai dengan QR dinamis. Package mengambil bagian generic yang bisa dipakai ulang oleh host lain, terutama sistem akademik dan modul kedokteran.

Core package mengelola:

- attendance session;
- participant identity secara generic;
- attendance action;
- QR challenge;
- validasi scan;
- attendance record;
- event;
- service API dan HTTP API opsional.

Core package tidak mengelola domain host seperti pegawai, mahasiswa, kegiatan, kelas, modul, lokasi kampus, daftar peserta kegiatan, role, policy bisnis, UI, atau migrasi data presensi lama. Semua itu tetap milik host application.

Dokumen ini bukan perintah untuk langsung membuat repo package atau menulis kode produksi. Output tahap terdekat adalah proposal implementable yang siap disetujui, lalu agent berikutnya dapat lanjut implementasi dengan boundary jelas.

## 2. Hubungan langsung dengan plan SIKAWAN

Plan SIKAWAN di `/home/debian/projects/sikawan/.hermes/plans/presensi-berbasis-dynamic-QR-Code.md` adalah sumber kebutuhan nyata pertama. Agent wajib membacanya sebelum menyusun proposal teknis package.

Gunakan plan SIKAWAN sebagai input untuk memilah:

- kebutuhan generic lintas aplikasi yang masuk package;
- kebutuhan khusus SIKAWAN yang tetap di host;
- titik integrasi antara package dan SIKAWAN;
- risiko migrasi dari data atau flow presensi lama.

Default V1: presensi kegiatan berbasis dynamic QR Code di SIKAWAN berdampingan dengan presensi harian yang sudah ada. Package ini tidak menggantikan fitur presensi harian SIKAWAN.

Khusus SIKAWAN, beberapa jenis kegiatan dapat berdampak pada presensi harian pegawai. Dampak tersebut adalah aturan host SIKAWAN, bukan aturan core package:

- kegiatan yang kehadirannya menggantikan presensi masuk dan presensi pulang, misalnya upacara 17 Agustus setelah itu pegawai pulang;
- kegiatan yang kehadirannya tidak menggantikan presensi harian, misalnya pembekalan pegawai di tengah jam kerja;
- kegiatan yang kehadirannya menggantikan presensi masuk saja, sedangkan presensi pulang tetap dilakukan lewat fitur presensi harian, misalnya upacara hari nasional setelah itu pegawai bekerja sampai jam pulang.

Package cukup mencatat presensi kegiatan dan menerbitkan event/hasil scan yang stabil. SIKAWAN menentukan apakah hasil presensi kegiatan juga dipakai sebagai sumber rekonsiliasi presensi harian masuk, pulang, keduanya, atau tidak sama sekali.

Jangan mengubah plan SIKAWAN kecuali ada instruksi eksplisit. Jika plan SIKAWAN dan dokumen ini terlihat bertentangan, baca source code terkait untuk memastikan fakta. Agent boleh bertanya hanya jika konflik tersebut benar-benar menghalangi proposal atau implementasi yang aman.

## 3. Prinsip kerja agent lokal

Agent lokal bekerja dengan urutan ini:

1. Baca file ini dan plan SIKAWAN.
2. Lakukan discovery baca saja pada source code yang relevan.
3. Susun proposal implementable untuk package dan integrasi SIKAWAN.
4. Minta persetujuan hanya saat proposal siap dinilai atau saat ada blocker faktual.
5. Implementasi hanya setelah disetujui.

Jangan berhenti hanya karena ada detail kecil yang belum ideal. Jadikan detail tersebut sebagai **asumsi V1** dengan default aman, lalu lanjutkan.

Agent hanya boleh bertanya jika:

- source code dan file plan memberi instruksi yang saling bertentangan;
- informasi faktual yang diperlukan tidak ada dan tidak bisa disimpulkan dari kode;
- keputusan tersebut akan mengubah data produksi, merusak compatibility, atau menentukan boundary package yang tidak bisa dibalik dengan mudah.

Jangan bertanya untuk hal yang sudah dipakukan di bagian Default Keputusan V1.

## 4. Default keputusan V1

Keputusan berikut berlaku sebagai default dan tidak perlu divalidasi ulang pada awal pekerjaan:

- V1 fokus pada presensi kegiatan berbasis dynamic QR Code.
- Presensi kegiatan berdampingan dengan fitur presensi harian SIKAWAN, bukan menggantikan fiturnya.
- Dampak presensi kegiatan terhadap presensi harian SIKAWAN ditentukan oleh host per jenis kegiatan: tidak mengganti, mengganti presensi masuk saja, atau mengganti presensi masuk dan pulang.
- Core package tidak menulis langsung ke tabel presensi harian SIKAWAN.
- SIKAWAN adalah host pertama dan sumber use case nyata.
- Package harus reusable untuk calon host lain seperti sistem akademik dan modul kedokteran.
- Check-in dan check-out memakai QR action eksplisit.
- Check-out wajib punya check-in sebelumnya pada session dan participant yang sama.
- Session menentukan action yang diizinkan, minimal `check_in` dan opsional `check_out`.
- Daftar peserta kegiatan dan eligibility ditentukan oleh host melalui contract atau resolver.
- Operator dan otorisasi kegiatan ditentukan oleh host melalui policy atau adapter.
- Lokasi bersifat opsional per session/kegiatan.
- Foto, survei, NFC, biometrik, fingerprint perangkat, dan fraud detection kompleks berada di luar core V1.
- QR payload harus signed, memiliki expiry pendek, dan tidak perlu menyimpan setiap rotasi QR ke database.
- Server memvalidasi waktu scan memakai waktu server, bukan waktu client.
- Database harus menegakkan uniqueness untuk mencegah scan ganda.
- Race condition ditangani dengan transaksi database dan unique constraint sebagai garis pertahanan terakhir.
- Core package tidak boleh bergantung pada Blade, React, Inertia, atau model spesifik SIKAWAN.
- UI host hanya memanggil API atau contract backend dari package.
- Package boleh menyediakan HTTP API opsional, tetapi public API utama tetap service/contract Laravel.
- Event dikirim setelah data attendance berhasil tersimpan dan commit aman untuk dibaca listener.

Asumsi V1 yang aman jika source code tidak memberi jawaban lain:

- attendance record disimpan satu baris per `(session, participant, action)`;
- uniqueness minimal berlaku untuk `(session_id, participant_type, participant_id, action)`;
- QR rotation default pendek, misalnya 10 detik, dengan grace window kecil untuk latensi;
- payload QR memuat versi format, session id, action, window atau expiry, dan signature;
- validasi signature memakai HMAC dan perbandingan constant-time;
- timestamp storage memakai UTC, tampilan mengikuti timezone host;
- lokasi dikirim sebagai data scan opsional dan hanya divalidasi jika session atau policy host memintanya;
- eligibility default ditolak jika host tidak bisa me-resolve participant sebagai peserta yang berhak.

## 5. Boundary package vs host

Package bertanggung jawab atas attendance engine yang generic:

- model dan migration untuk session generic dan attendance record;
- enum/value object untuk attendance action;
- QR challenge generator dan validator;
- service untuk membuka/membaca session, menerbitkan challenge, dan memproses scan;
- validasi inti: signature QR, session aktif, action diizinkan, time window, check-out setelah check-in, duplicate prevention;
- extension point untuk eligibility, authorization handoff, location validation, dan policy tambahan;
- event minimal seperti `AttendanceRecorded`;
- response/error shape yang stabil untuk API.

Host application bertanggung jawab atas domain dan pengalaman aplikasi:

- model pegawai/mahasiswa/peserta;
- model kegiatan/pertemuan/modul;
- daftar peserta kegiatan atau eligibility;
- role operator, permission, dan otorisasi halaman;
- data lokasi kampus atau titik kegiatan;
- UI operator dan scanner;
- auth middleware;
- notifikasi, rekap khusus domain, dan pelaporan;
- migrasi data lama serta compatibility dengan fitur presensi yang sudah ada.

SIKAWAN sebagai host pertama tetap memiliki data pegawai, kegiatan, peserta kegiatan, role, lokasi, policy bisnis, UI, rekonsiliasi ke presensi harian, dan migrasi data lama. Package tidak boleh mengimpor model SIKAWAN secara langsung.

Untuk host SIKAWAN, tabel/model kegiatan memang belum ada dan perlu dibuat pada tahap implementasi host. Nama tabel yang disukai untuk SIKAWAN:

- `kegiatans` untuk data kegiatan;
- `peserta_kegiatans` untuk daftar pegawai peserta kegiatan.

Padanan generic untuk dokumentasi lintas host boleh disebut `events` dan `event_participants`, tetapi jangan memaksakan nama Inggris tersebut ke SIKAWAN jika konvensi host memakai bahasa Indonesia.

Aturan SIKAWAN untuk kegiatan yang memengaruhi presensi harian harus ditempatkan di adapter/service host. Package menyediakan record dan event presensi kegiatan; host boleh menerjemahkan record tersebut menjadi:

- tidak berdampak ke presensi harian;
- pemenuhan presensi masuk harian;
- pemenuhan presensi masuk dan pulang harian.

Default aman V1: package tidak melakukan side effect ke presensi harian. Jika SIKAWAN membutuhkan side effect, lakukan melalui listener/service host yang eksplisit, teruji, dan idempotent.

Calon host akademik dan modul kedokteran menjadi pembanding boundary. Jangan membangun fitur khusus akademik atau kedokteran di V1, tetapi pastikan contract package tidak mengunci konsep pegawai/kegiatan SIKAWAN.

## 6. Tahap discovery baca saja

Discovery dilakukan tanpa mengubah file aplikasi, tanpa membuat repo package, dan tanpa menulis kode produksi.

Langkah wajib:

1. Baca plan SIKAWAN dynamic QR Code.
2. Baca panduan repo yang relevan, misalnya `AGENTS.md` jika ada.
3. Baca kode presensi SIKAWAN yang sudah ada, terutama model, migration, route, controller, policy, middleware, UI, timezone, dan data lokasi.
4. Catat bagaimana presensi harian SIKAWAN bekerja agar presensi kegiatan V1 tidak mengganggunya.
5. Petakan jenis kegiatan SIKAWAN yang berdampak pada presensi harian: tidak mengganti, mengganti presensi masuk saja, atau mengganti presensi masuk dan pulang.
6. Anggap model kegiatan SIKAWAN belum ada. Proposal host harus memasukkan rancangan tabel `kegiatans` dan `peserta_kegiatans` atau menjelaskan jika discovery menemukan nama tabel lain yang sudah lebih tepat.
7. Identifikasi versi Laravel, PHP, database, queue/event, auth guard, dan pola service yang dipakai SIKAWAN.
8. Petakan requirement SIKAWAN menjadi `package`, `host`, atau `di luar V1`.
9. Catat potensi migrasi data lama, tetapi jangan menjalankan migrasi atau mengubah data.

Output discovery harus berupa temuan faktual, bukan asumsi bebas. Jika repo calon host lain tidak tersedia, tulis sebagai asumsi V1 bahwa sistem akademik memakai session per pertemuan dan modul kedokteran memakai session per modul.

## 7. Tahap proposal implementable

Setelah discovery, susun proposal yang cukup konkret untuk langsung diimplementasikan setelah disetujui. Proposal tidak perlu berupa daftar pertanyaan panjang.

Proposal minimal berisi:

- boundary final package vs SIKAWAN host;
- rancangan tabel host SIKAWAN untuk `kegiatans` dan `peserta_kegiatans`, termasuk jenis dampak kegiatan ke presensi harian;
- schema tabel package, termasuk kolom, indeks, unique constraint, tipe ID, dan strategi polymorphic reference atau resolver;
- contract/resolver untuk participant, context/session owner, eligibility, location policy, dan authorization handoff;
- public service API untuk membuat/membuka session, menerbitkan QR challenge, memproses scan, dan membaca hasil;
- HTTP API opsional, route prefix, middleware, rate limit, dan error response;
- format QR payload, signing, expiry, grace window, secret, dan strategi rotation tanpa record database per QR;
- flow check-in dan check-out eksplisit, termasuk aturan check-out wajib punya check-in;
- transaksi scan dan penanganan duplicate/race condition;
- event minimal dan waktu dispatch setelah commit;
- integrasi SIKAWAN: adapter yang diperlukan, UI yang memanggil backend, dan cara host merekonsiliasi kegiatan ke presensi harian jika jenis kegiatan memintanya;
- strategi idempotent untuk side effect host ke presensi harian, dengan default tidak ada penulisan langsung dari core package;
- test plan V1 untuk signature/expiry, action, eligibility, duplicate scan, race condition, dan integrasi host;
- risiko dan batas V1.

Jika ada ketidakpastian kecil, tulis sebagai asumsi V1 dengan default aman. Contoh: "Asumsi V1: QR rotation 10 detik, menerima current dan previous window." Jangan mengubah ketidakpastian kecil menjadi pertanyaan untuk user.

Proposal boleh meminta keputusan manusia maksimal untuk hal yang blocking, misalnya target versi Laravel/PHP jika source code host saling bertentangan, atau strategi migrasi data jika ada risiko mengubah data produksi.

## 8. Tahap implementasi setelah disetujui

Tahap ini baru berjalan setelah proposal implementable disetujui.

Langkah implementasi V1:

1. Buat package Composer Laravel sebagai repo/direktori sibling hanya jika sudah disetujui.
2. Implementasikan engine package dari schema dan service API terlebih dahulu.
3. Tambahkan migration, config, service provider, contracts, services, policies internal, events, HTTP opsional, dan tests.
4. Pasang package ke SIKAWAN memakai Composer path repository selama development.
5. Buat model/tabel host SIKAWAN untuk `kegiatans` dan `peserta_kegiatans`, lalu adapter untuk context kegiatan, participant pegawai, daftar peserta/eligibility, otorisasi operator, dan lokasi opsional.
6. Integrasikan UI SIKAWAN agar hanya memanggil backend package/adapter.
7. Uji flow operator dan pemindai untuk check-in, check-out, QR expired, duplicate scan, peserta tidak eligible, session tutup, dan lokasi opsional.
8. Dokumentasikan instalasi, config, extension point, API, migration, dan batas keamanan QR.

Batas implementasi V1:

- jangan mengerjakan foto, survei, NFC, biometrik, fraud detection kompleks, atau pelaporan besar;
- jangan mengubah data presensi produksi tanpa migration plan terpisah;
- jangan membuat dependency core ke Blade, React, Inertia, atau model SIKAWAN;
- jangan membuat abstraction tambahan yang tidak dipakai oleh SIKAWAN atau calon host pembanding.

## 9. Kriteria selesai untuk proposal

Proposal dianggap selesai jika memenuhi semua poin berikut:

- Semua requirement dari plan SIKAWAN punya pemilik: package, host SIKAWAN, atau di luar V1.
- Presensi kegiatan jelas berdampingan dengan fitur presensi harian SIKAWAN.
- Proposal menjelaskan tiga dampak kegiatan SIKAWAN terhadap presensi harian: tidak mengganti, mengganti masuk saja, atau mengganti masuk dan pulang.
- Core package tidak punya side effect langsung ke tabel presensi harian SIKAWAN; side effect host, jika ada, dilakukan lewat adapter/listener/service yang eksplisit dan idempotent.
- Check-in dan check-out eksplisit, termasuk aturan check-out setelah check-in.
- Daftar peserta kegiatan, eligibility, operator, authorization, dan lokasi berada di host melalui contract/resolver/policy.
- Proposal memakai nama tabel host SIKAWAN `kegiatans` dan `peserta_kegiatans`, kecuali discovery menemukan alasan faktual untuk nama lain.
- Schema menegakkan uniqueness untuk mencegah scan ganda.
- QR payload signed, expiry pendek, stateless per rotation, dan tidak perlu menyimpan setiap QR.
- Flow scan menjelaskan validasi server, transaksi database, duplicate handling, dan event after commit.
- Core package bebas dari ketergantungan Blade, React, Inertia, dan model SIKAWAN.
- API/contract cukup jelas untuk dipakai UI host.
- Risiko keamanan QR dijelaskan realistis, termasuk bahwa QR dinamis tidak sepenuhnya mencegah titip presensi jika kode dibagikan dalam window aktif.
- Maksimal ada dua keputusan manusia yang benar-benar blocking sebelum coding.

## 10. Instruksi singkat untuk agent berikutnya

Buka:

- `docs/plan.md`
- `docs/proposal-v1.md`
- `/home/debian/projects/sikawan/.hermes/plans/presensi-berbasis-dynamic-QR-Code.md`

Current package sudah ada. Jangan membuat package baru; lanjutkan repo lokal `/home/debian/projects/laravel-attendance-engine`.

Gunakan README dan source code sebagai sumber kebenaran current implementation. Dokumen ini adalah riwayat perencanaan dan boundary V1. Jangan mengubah plan SIKAWAN kecuali diminta eksplisit. Current V1 tetap tanpa side effect ke tabel `presensis`.
