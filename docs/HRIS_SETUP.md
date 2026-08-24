# Absensi → HRIS — Setup & Module Reference

Implements M0–M8 of [MODULE_PLANS.md](MODULE_PLANS.md).
M9–M11 (Recruitment, Performance, Training) are not built — they are marked
optional/LOW in the plan.

---

## Quick start

```bash
docker compose up -d          # MySQL on host port 3307 (+ absensi_testing schema)
composer install
php artisan migrate
php artisan db:seed --class=HrisSeeder
```

`.env` must point at the container:

```
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=secret
```

Then grant yourself the top role:

```bash
php artisan tinker
>>> \App\Models\User::find(1)->assignRole('super_admin');
```

`HrisSeeder` runs every reference-data seeder and is **idempotent** — safe to
re-run after an upgrade. It calls `RolesAndPermissionsSeeder`,
`ApprovalFlowSeeder`, `LeaveTypeSeeder`, `DocumentTypeSeeder`, `TaxRateSeeder`.

### Demo data

```bash
php artisan db:seed --class=DemoDataSeeder    # refuses to run in production
php artisan serve
```

Seeds a five-person company with a **complete previous month** of attendance,
approved and pending leave, a loan, and a full payroll run. Log in at
`/admin/login` with any of `siti@` (super_admin), `rina@` (hr_admin), `budi@`
(manager), `ahmad@` / `dewi@` (employee) `demo.test`, password `password`.

The previous month is used deliberately: a salary recap always measures against
the *whole* month, so a part-way-through current month reads as mass absence.

The data is arranged to demonstrate the payroll fix side by side —
Ahmad takes 3 days of approved paid leave, Dewi is absent 2 days with no
request. Same attendance shortfall, opposite payroll outcome.

### Tests

```bash
./vendor/bin/phpunit          # 150 tests
```

Tests run against a separate `absensi_testing` schema (see `phpunit.xml`), so
they never touch development data.

---

## Backpack free edition — what is not available

`backpack/crud` without a PRO licence throws `BackpackProRequiredException` for
several things the docs show freely. Two that bit this build:

- **`addFilter()` is PRO.** Filtering is done instead with
  `App\Traits\HasSimpleFilters`, which reads plain GET parameters, applies them
  with `addClause()`, and renders a filter bar
  (`resources/views/vendor/backpack/crud/buttons/simple_filters.blade.php`).
  Add filters through `$this->applySimpleFilters([...])`, never `addFilter()`.
- **A CRUD model must use `CrudTrait`.** Spatie's `Role`/`Permission` models do
  not, so `App\Models\Role` and `App\Models\Permission` subclass them purely to
  add the trait. Point CRUD controllers and relations at the app-local classes.

Also worth knowing: Backpack list rows load over AJAX from
`POST /admin/<entity>/search`. Fetching the list URL alone returns an empty
table shell — verify list data against the search endpoint.

---

## Two guards — read this before touching auth

Backpack authenticates admins on the **`backpack`** guard; roles are stored
against the **`web`** guard, which is also Laravel's default. This bites in
three predictable ways:

- Spatie's `@role` / `@can` Blade directives read the *default* guard and are
  therefore false for a logged-in admin. In admin views use
  `backpack_user()->hasRole(...)` / `backpack_user()->can(...)`.
- `$request->user()` is null for admins. Use
  `App\Traits\ResolvesAuthenticatedUser`, which the `role` and `permission`
  middleware already do.
- `CheckIfAdmin` decides who may see `/admin/*`. A user with **no roles at all**
  is treated as an admin so accounts predating the roles upgrade are not locked
  out; accounts limited to `employee` are redirected to `/my`.

---

## Roles

| Role | Scope |
|------|-------|
| `super_admin` | Everything, incl. roles, permissions and approval-flow config |
| `hr_admin` | All HR operations. Cannot change roles, permissions or approval flows |
| `manager` | Team visibility + acting on approvals |
| `employee` | Self-service portal only |

Middleware: `->middleware('role:hr_admin,manager')`, `->middleware('permission:leave.approve')`.

---

## M0 — Foundation

**Roles & permissions** (`spatie/laravel-permission`), **approval engine**, **audit trail**.

### Approval engine

Tables: `approval_flows`, `approval_flow_steps`, `approvals`, `approval_actions`.
One active flow per module (`leave`, `loan`, `overtime`); each ordered step names
an approver by **role**, by the requester's **manager**, or by a **specific user**.

Make any model approvable:

```php
class LeaveRequest extends Model
{
    use \App\Traits\HasApproval;

    public function approvalModule(): string { return 'leave'; }

    public function onApprovalApproved($approval) { /* ... */ }
    public function onApprovalRejected($approval) { /* ... */ }
    public function onApprovalCancelled($approval) { /* ... */ }
}
```

`ApprovalService`: `submitForApproval`, `approve`, `reject`, `cancel`,
`getNextApprovers`, `getPendingForUser`.

Every state change runs in a transaction with `SELECT … FOR UPDATE` on the
approval row and re-authorises against the step current **at lock time**, so two
approvers racing cannot both succeed. One live approval per record is enforced by
a unique index, not just application code.

Errors: `\DomainException` for caller mistakes (wrong approver, not pending,
blank reason, duplicate submit); `\RuntimeException` for misconfiguration (no
active flow, flow with no steps).

### Audit trail

Attach `App\Traits\Auditable` to log create/update/delete into `audit_logs`.
Viewer at `/admin/audit-log`; pruned monthly via `audit:prune --days=90`.

---

## M1 — Organisation structure

`departments` (self-nesting, with a head), `positions`, plus new `users` columns:
`department_id`, `position_id`, `employee_id`, `join_date`, `employment_status`,
`phone`, `address`, `manager_id`.

Nesting is cycle-guarded: the CRUD refuses a parent that is the department
itself or one of its descendants, and `descendants()` terminates even if bad
data already contains a loop.

`User::employed()` scopes to `active` + `probation` — use it anywhere payroll or
headcount is involved, so resigned staff keep their history without appearing in
current figures.

Org chart: `/admin/org-chart`.

---

## M2 — Leave management

Tables: `leave_types`, `leave_balances`, `leave_requests`, `leave_request_dates`.

`leave_balances.remaining` is a generated column computed as
`quota + carry_over - used`. The original plan specified `quota - used`, which
silently discards carried-over days.

### The payroll bug this fixes

Before this module, **every day an employee was not present counted as an unpaid
absence** — approved holidays and sick days quietly docked pay. `SalaryService`
now distinguishes three cases:

| Case | Counts as absence | Deducted |
|------|-------------------|----------|
| Approved **paid** leave | no | no |
| Approved **unpaid** leave | no | yes |
| Unexplained absence | yes | yes |

Pending leave excuses nothing. See `tests/Feature/SalaryLeaveIntegrationTest.php`.

`LeaveService` skips weekends (per the user's schedule) and national holidays
when counting days, refuses overlapping requests, enforces
`max_consecutive_days` and `requires_attachment`, and checks quota twice — once
for fast feedback and again under a row lock at approval time.

Yearly setup (idempotent, scheduled 1 Jan):

```bash
php artisan leave:generate-balances --carry-over --max-carry=6
```

Mid-year joiners get a prorated quota.

---

## M3 — Notifications

`notifications` + `notification_preferences`. Channels: database (always
written, it is the audit trail), email, WhatsApp. Delivery never throws — a mail
or gateway failure must not roll back the action that triggered it.

WhatsApp falls back to `LogWhatsAppGateway` (logs only) until `FONNTE_TOKEN` is
set, so nothing silently pretends to have sent.

Bell in the admin topbar; full list at `/admin/notification` and `/my/notifications`.

Scheduled: missing check-in 08:15, lateness 09:30, missing check-out 17:00
(weekdays); approval digest Mondays 08:00; expiring documents Mondays 07:30.

---

## M4 — Employee self-service portal

`/my/*`, sharing Backpack's login and guard — no second auth system. Blade +
Bootstrap 5 via CDN, no build step.

Dashboard, attendance history, payslips, leave (balance / request / cancel),
loans, profile, password, notifications.

Every query is scoped to the authenticated user, and **no route accepts a user
id from the request**. Employees may edit only contact details — name,
department and employment status stay with HR.

---

## M5 — Tax & compliance

Tables: `employee_tax_profiles`, `ptkp_rates`, `pph21_brackets`, `bpjs_rates`,
plus statutory columns on `salary_recaps`.

Rates are **not hard-coded** — PTKP, brackets and BPJS percentages are stored per
year, because the government revises them and a historical recalculation must
use the rates of its own period. If a year is missing, the most recent published
year is used rather than contributing nothing.

`TaxService`:
- `calculateBPJS` — Kesehatan (1%/4%, capped at 12,000,000), JHT (2%/3.7%,
  uncapped), JP (1%/2%, capped at 10,042,300), JKK and JKM (employer-only).
- `calculatePPh21` — annualised: gross × 12, less biaya jabatan (5%, capped at
  6,000,000/yr), less employee JHT+JP, less PTKP; progressive brackets; ÷ 12.
  Employees without an NPWP get the 20% surcharge.
- `calculateTHR` — a full month after 12 months' service, prorated below that,
  nothing under one month.

> **Verify the seeded rates against current regulations before running real
> payroll.** `TaxRateSeeder` reflects PMK 101/2016 and UU HPP No. 7/2021 as at
> the time of writing, and JKK is seeded at the lowest risk class (0.24%).

Reports: `/admin/tax-report/annual` (SPT basis), `/admin/tax-report/bpjs`.

---

## M6 — Employee documents

`document_types` + `employee_documents`.

Files are stored on the **`local` (private) disk**, not `public` — identity
documents and contracts must not be reachable by guessing a URL. Downloads
stream through the app after an access check: HR sees everything, everyone else
only their own. Deleting a record deletes its file.

Per-type rules for allowed extensions, max size and whether an expiry date is
mandatory. Completeness checklist at `/admin/employee-document/completeness`;
expiry alerts via `documents:notify-expiring --days=30`.

---

## M7 — Multi-branch

`branches` + `users.branch_id` + `presences.branch_id`.

Geofencing is now **per branch** (coordinates *and* radius), replacing the
global config and the 100 m radius that was hard-coded in `PresenceService`.
Resolution order: the branch recorded on the presence row → the user's branch →
global `app.office_lat/lng/radius`. With no reference point configured anywhere,
scans are treated as on-site rather than flagging everyone.

A presence keeps the branch it was recorded against, so recalculating history
after someone transfers office does not re-attribute it.

The migration creates one branch from existing config and attaches every user to
it, so single-site behaviour is unchanged after the upgrade.

> `.env` shipped `LAT`/`LNG` with trailing semicolons (`-6.8493328;`). The old
> code hid this behind a `(float)` cast at the call site; `config/app.php` now
> casts once, centrally.

---

## M8 — Reporting & dashboard

`/admin/dashboard` overrides Backpack's stock dashboard: today's attendance,
month payroll totals, a 12-month trend (Chart.js), top latecomers, leave this
week, headcount. Today's figures are cached for 5 minutes — call
`DashboardService::flushCache()` if a figure must be immediately fresh.

Reports: `/admin/report/attendance`, `/report/salary`, `/report/loan`,
`/report/headcount`, plus the leave and tax reports from M2/M5.

The dashboard counts people on approved leave separately from absentees, so
leave no longer shows up as unexplained absence there either.

---

## Scheduled commands

| Schedule | Command |
|----------|---------|
| weekdays 08:15 | `notify:attendance --type=checkin` |
| weekdays 09:30 | `notify:attendance --type=late` |
| weekdays 17:00 | `notify:attendance --type=checkout` |
| Mondays 07:30 | `documents:notify-expiring --days=30` |
| Mondays 08:00 | `notify:approval-digest` |
| 1 Jan 01:00 | `leave:generate-balances --carry-over --max-carry=6` |
| monthly | `audit:prune --days=90` |

---

## Incidental fixes made along the way

Found while building:

- `CheckIfAdmin` returned `true` for everyone, so any authenticated user could
  reach the whole admin panel once roles existed.
- `hr_admin` had been seeded with all 44 permissions — identical to
  `super_admin`. Role/permission/approval-flow management is now super-admin only.
- `PresenceService::calculateExtraTime()` crashed on users without a schedule;
  `calculateLate()` and `calculateOvertime()` already guarded this.
- The stock `ExampleTest` asserted `/` returns 200, but the app redirects to the
  QR scan page.

Found only by running the app against seeded data (the test suite was green
throughout):

- **Geofence never evaluated on insert.** `presences.outside` defaults to `1`
  and `PresenceObserver` only recalculated coordinates on *update*, so any row
  created with its coordinates already attached — import, API, seeder — was
  permanently flagged off-site. Every seeded presence showed "Di Luar Radius"
  until `created()` was fixed to evaluate the geofence too.
- **`approved_by` recorded the first approver, not the last.** The `actions()`
  relation carries `orderBy('step_order')`, so appending `->latest('acted_at')`
  produced `ORDER BY step_order ASC, acted_at DESC` and step 1 still won. On a
  manager→HR chain the manager was credited with the final approval. Fixed with
  `->reorder()`; same bug affected `rejection_reason`. Both now have regression
  tests.
