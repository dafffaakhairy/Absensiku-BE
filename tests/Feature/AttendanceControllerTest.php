<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        // Waktu pengujian: Senin, 26 Oktober 2026, pukul 08.00.
        Carbon::setTestNow('2026-10-26 08:00:00');

        // Gunakan storage palsu agar file pengujian tidak tersimpan di storage asli.
        Storage::fake('public');

        // Buat user yang akan melakukan absensi.
        $this->user = User::factory()->create([
            'is_active' => true,
        ]);

        // Autentikasi sebagai user tersebut menggunakan Sanctum.
        Sanctum::actingAs($this->user);

        // Buat jadwal kerja dengan radius geofence 100 meter.
        $this->schedule = WorkSchedule::create([
            'name' => 'Jadwal Testing',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'office_latitude' => -6.595038,
            'office_longitude' => 106.816635,
            'geofence_radius_meters' => 100,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Reset waktu agar tidak memengaruhi test lainnya.
        Carbon::setTestNow();

        parent::tearDown();
    }

    // TEST 1: Check-in berhasil jika lokasi berada di dalam radius kantor.
    public function test_check_in_successfully_inside_office_radius(): void
    {
        $response = $this->postJsonWithPhoto([
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        // Pastikan API mengembalikan HTTP 201 dan pesan sukses.
        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Check-in berhasil.');

        // Pastikan tanggal absensi tersimpan dengan benar.
        $attendance = Attendance::where(
            'user_id',
            $this->user->id
        )->firstOrFail();

        $this->assertSame(
            '2026-10-26',
            $attendance->work_date->toDateString()
        );

        // Pastikan data absensi tersimpan di database.
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'check_in_latitude' => -6.595038,
            'check_in_longitude' => 106.816635,
        ]);

        // Pastikan foto check-in tersimpan di storage.
        Storage::disk('public')->assertExists(
            $attendance->check_in_photo_path
        );
    }

    // TEST 2: Check-in ditolak jika lokasi berada di luar radius kantor.
    public function test_check_in_rejected_outside_office_radius(): void
    {
        // Koordinat Manchester, Inggris, jauh di luar radius kantor pengujian.
        $response = $this->postJsonWithPhoto([
            'latitude' => 53.463056,
            'longitude' => -2.291333,
            'photo' => $this->fakePhoto(),
        ]);

        // Pastikan API mengembalikan HTTP 422 dan informasi jarak.
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Anda berada di luar area kantor.'
            )
            ->assertJsonStructure([
                'message',
                'distance_meters',
            ]);

        // Pastikan absensi tidak tersimpan.
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
        ]);
    }

    // TEST 3: Check-in ditolak jika koordinat tidak valid.
    public function test_check_in_rejected_when_coordinates_are_invalid(): void
    {
        $response = $this->postJsonWithPhoto([
            'latitude' => 100, // Latitude tidak valid; rentang yang diperbolehkan -90 sampai 90.
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        // Pastikan validasi menolak latitude yang tidak valid.
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);

        // Pastikan absensi tidak tersimpan.
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
        ]);
    }

    // TEST 4: Check-in ditolak jika hari tersebut bukan hari kerja.
    public function test_check_in_rejected_when_not_a_working_day(): void
    {
        // Atur waktu pengujian menjadi Minggu, 25 Oktober 2026.
        Carbon::setTestNow('2026-10-25 08:00:00');

        $response = $this->postJsonWithPhoto([
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        // Pastikan API menolak absensi pada hari libur.
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Hari ini bukan hari kerja.'
            );

        // Pastikan absensi tidak tersimpan.
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
        ]);
    }

    // TEST 5: Check-in kedua pada tanggal yang sama harus ditolak.
    public function test_duplicate_check_in_is_rejected(): void
    {
        // Buat data check-in yang sudah ada untuk user dan tanggal tersebut.
        Attendance::create([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'work_date' => '2026-10-26',
            'check_in_at' => now(),
            'check_in_latitude' => -6.595038,
            'check_in_longitude' => 106.816635,
            'check_in_photo_path' => 'attendance-selfies/old.jpg',
            'status' => 'present',
        ]);

        $response = $this->postJsonWithPhoto([
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        // Pastikan API mengembalikan HTTP 409 karena absensi sudah tercatat.
        $response
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Absensi hari ini sudah tercatat.'
            );

        // Pastikan tidak ada data absensi duplikat.
        $this->assertDatabaseCount('attendances', 1);
    }

    // Helper: kirim request check-in dengan header JSON dan file foto.
    private function postJsonWithPhoto(array $data)
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/attendance/check-in', $data);
    }

    // Helper: buat file foto pengujian tanpa membutuhkan ekstensi GD.
    private function fakePhoto(): UploadedFile
    {
        return UploadedFile::fake()->create(
            'selfie.jpg',
            100,
            'image/jpeg'
        );
    }

    
    // TEST 6: Check-out berhasil jika sudah check-in dan berada di radius kantor.
    public function test_check_out_successfully_inside_office_radius(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->postJson('/api/attendance/check-out', [
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Check-out berhasil.');

        $attendance->refresh();

        $this->assertNotNull($attendance->check_out_at);
        $this->assertSame(
            -6.595038,
            (float) $attendance->check_out_latitude
        );

        Storage::disk('public')->assertExists(
            $attendance->check_out_photo_path
        );
    }

    // TEST 7: Check-out ditolak jika berada di luar radius kantor.
    public function test_check_out_rejected_outside_office_radius(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->postJson('/api/attendance/check-out', [
            'latitude' => 53.463056,
            'longitude' => -2.291333,
            'photo' => $this->fakePhoto(),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Anda berada di luar area kantor.'
            )
            ->assertJsonStructure(['message', 'distance_meters']);

        $this->assertNull($attendance->fresh()->check_out_at);
    }

    // TEST 8: Check-out ditolak jika belum melakukan check-in.
    public function test_check_out_rejected_without_check_in(): void
    {
        $response = $this->postJson('/api/attendance/check-out', [
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Anda belum melakukan check-in.'
            );
    }

    // TEST 9: Check-out kedua ditolak.
    public function test_duplicate_check_out_is_rejected(): void
    {
        $attendance = $this->createAttendance([
            'check_out_at' => now(),
            'check_out_latitude' => -6.595038,
            'check_out_longitude' => 106.816635,
        ]);

        $response = $this->postJson('/api/attendance/check-out', [
            'latitude' => -6.595038,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Check-out hari ini sudah tercatat.'
            );

        $this->assertNotNull($attendance->fresh()->check_out_at);
    }

    // TEST 10: Check-out ditolak jika koordinat tidak valid.
    public function test_check_out_rejected_when_coordinates_are_invalid(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->postJson('/api/attendance/check-out', [
            'latitude' => 100,
            'longitude' => 106.816635,
            'photo' => $this->fakePhoto(),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);

        $this->assertNull($attendance->fresh()->check_out_at);
    }

    // TEST 11: Preview menunjukkan sisa waktu sebelum memenuhi 9 jam.
    public function test_preview_check_out_calculates_remaining_work_minutes(): void
    {
        $this->createAttendance([
            'check_in_at' => Carbon::parse('2026-10-26 08:00:00'),
        ]);

        // Waktu sekarang pukul 10.30: sudah bekerja 150 menit.
        Carbon::setTestNow('2026-10-26 10:30:00');

        $response = $this->getJson('/api/attendance/check-out/preview');

        $response
            ->assertOk()
            ->assertJsonPath('worked_minutes', 150)
            ->assertJsonPath('remaining_minutes', 390)
            ->assertJsonPath('minimum_work_minutes', 540)
            ->assertJsonPath('requires_confirmation', true);
    }

    // TEST 12: Preview tidak meminta konfirmasi setelah mencapai 9 jam.
    public function test_preview_check_out_does_not_require_confirmation_after_minimum_work(): void
    {
        $this->createAttendance([
            'check_in_at' => Carbon::parse('2026-10-26 08:00:00'),
        ]);

        // Waktu sekarang pukul 17.00: sudah bekerja 540 menit.
        Carbon::setTestNow('2026-10-26 17:00:00');

        $response = $this->getJson('/api/attendance/check-out/preview');

        $response
            ->assertOk()
            ->assertJsonPath('worked_minutes', 540)
            ->assertJsonPath('remaining_minutes', 0)
            ->assertJsonPath('requires_confirmation', false);
    }

    // Helper: buat absensi check-in untuk pengujian check-out.
    private function createAttendance(array $overrides = []): Attendance
    {
        return Attendance::create(array_merge([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'work_date' => '2026-10-26',
            'check_in_at' => Carbon::parse('2026-10-26 08:00:00'),
            'check_in_latitude' => -6.595038,
            'check_in_longitude' => 106.816635,
            'check_in_photo_path' => 'attendance-selfies/check-in.jpg',
            'status' => 'present',
        ], $overrides));
    }

}
