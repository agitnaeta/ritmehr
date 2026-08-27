<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestDate;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M02 polish — the leave calendar query eager-loads user/leaveType/dates so
 * rendering the month grid never triggers a query per entry (N+1 guard).
 */
class LeaveCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedLeave(string $email, string $start, string $end): LeaveRequest
    {
        $type = LeaveType::firstOrCreate(
            ['code' => 'AN'],
            ['name' => 'Cuti Tahunan', 'is_paid' => true, 'default_quota' => 12, 'is_active' => true, 'color' => '#2ecc71']
        );
        $user = User::create(['name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('x')]);

        $req = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $type->id,
            'start_date' => $start, 'end_date' => $end, 'total_days' => 1,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        LeaveRequestDate::create(['leave_request_id' => $req->id, 'date' => $start, 'day_value' => 1]);

        return $req;
    }

    public function test_calendar_entries_returns_approved_leave_in_range(): void
    {
        $this->makeApprovedLeave('a@ex.test', '2026-08-10', '2026-08-10');

        $entries = app(LeaveService::class)->calendarEntries('2026-08-01', '2026-08-31');

        $this->assertCount(1, $entries);
        $this->assertTrue($entries->first()->relationLoaded('dates'), 'dates eager-loaded');
        $this->assertTrue($entries->first()->relationLoaded('leaveType'), 'leaveType eager-loaded');
    }

    public function test_calendar_is_not_n_plus_one_over_entries(): void
    {
        // Ukur pola N+1 lewat SKALA, bukan angka absolut: jumlah query saat
        // menyentuh relasi harus KONSTAN walau jumlah entri bertambah. Ini imun
        // terhadap overhead query boot framework/paket (yang berubah antar versi
        // Laravel) — yang kita jaga murni eager-loading logika kalender.
        $countQueriesFor = function (int $n): int {
            $type = LeaveType::firstOrCreate(
                ['code' => 'AN'],
                ['name' => 'Cuti Tahunan', 'is_paid' => true, 'default_quota' => 12, 'is_active' => true, 'color' => '#2ecc71']
            );
            foreach (range(1, $n) as $i) {
                $day = str_pad((string) ($i + 5), 2, '0', STR_PAD_LEFT);
                $this->makeApprovedLeave("scale{$n}_{$i}@ex.test", "2026-08-$day", "2026-08-$day");
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $entries = app(LeaveService::class)->calendarEntries('2026-08-01', '2026-08-31');
            foreach ($entries as $e) {
                $e->user?->name;
                $e->leaveType?->color;
                foreach ($e->dates as $d) {
                    $d->date;
                }
            }
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $qSmall = $countQueriesFor(2);
        // reset data agar hitungan kedua bersih
        LeaveRequest::query()->delete();
        $qLarge = $countQueriesFor(8);

        // Eager loading → query untuk 8 entri TIDAK jauh lebih banyak dari 2 entri.
        // N+1 asli: 8 entri × (user+leaveType+dates) ≈ 25+ query. Eager: tetap kecil.
        // Toleransi kecil (≤2) menyerap variasi query boot antar versi Laravel.
        $this->assertLessThanOrEqual($qSmall + 2, $qLarge,
            "query kalender melonjak ($qSmall -> $qLarge) saat entri 2→8 — indikasi N+1");
        // Guard mutlak: 8 entri tetap jauh di bawah ambang N+1.
        $this->assertLessThanOrEqual(10, $qLarge,
            "kalender 8 entri butuh $qLarge query — terlalu banyak, cek eager loading");
    }
}
