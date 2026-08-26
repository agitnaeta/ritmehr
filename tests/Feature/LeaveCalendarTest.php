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
        foreach (range(1, 6) as $i) {
            $this->makeApprovedLeave("u{$i}@ex.test", '2026-08-' . str_pad((string) ($i + 5), 2, '0', STR_PAD_LEFT), '2026-08-' . str_pad((string) ($i + 5), 2, '0', STR_PAD_LEFT));
        }

        DB::enableQueryLog();
        $entries = app(LeaveService::class)->calendarEntries('2026-08-01', '2026-08-31');
        // Touch relations exactly like the calendar view does.
        foreach ($entries as $e) {
            $e->user?->name;
            $e->leaveType?->color;
            foreach ($e->dates as $d) { $d->date; }
        }
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 6 leave entries; naive N+1 would be ~20+. Eager loading keeps it small.
        $this->assertLessThanOrEqual(5, $queries, "leave calendar ran $queries queries — N+1 regression?");
    }
}
