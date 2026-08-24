# Absensi — Business Flow Documentation

> **System:** Attendance & Payroll Management System
> **Stack:** Laravel 10 + Backpack CRUD v6, Vite, DomPDF, Maatwebsite Excel, Firefly III (ACC)
> **Generated:** 2026-08-06

---

## 1. System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    ABSENSI SYSTEM                           │
│                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────────┐  │
│  │ Kehadiran│  │  Gajian  │  │  Kasbon  │  │ Akuntansi  │  │
│  │(Presence)│→ │ (Salary) │← │  (Loan)  │→ │   (ACC)    │  │
│  └──────────┘  └──────────┘  └──────────┘  └────────────┘  │
│       ↑              ↑             ↑             ↑         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │  Jadwal  │  │   User   │  │  Libur   │  │ Company  │   │
│  │(Schedule)│  │          │  │Nasional  │  │ Profile  │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
```

The system manages **employee attendance** (check-in/out via QR code), **salary calculation** (auto-computed from attendance data), **loan (kasbon) management**, and **external accounting sync** to Firefly III.

---

## 2. Actors

### 2.1 Admin (Human)
- **Auth:** Laravel Backpack auth (`/admin/login`)
- **Capabilities:**
  - Manage users (CRUD, print ID cards, export to Excel)
  - Manage schedules (work hours, overtime hours, day-off config)
  - Mass-assign schedules to employees
  - Manage salary configuration per employee (base pay, overtime rate, fine rules)
  - View/edit attendance records manually
  - Manage loans (kasbon) — create, view recap, print, export
  - Manage loan payments — create, edit, delete
  - View salary recaps — filter by month, export to Excel, print to PDF
  - Set payment status (Cash/Transfer) on salary recaps
  - Recalculate salary for specific recap entries
  - Manage national holidays
  - Configure company profile (name, address, logo, ID card background)
  - Configure accounting integration (ACC code mappings)

### 2.2 Employee (Human)
- **Auth:** None — unauthenticated access to QR scan page
- **Capabilities:**
  - Scan QR code to clock in/out (via `/scan` public route)
  - System auto-detects if it's clock-in (first scan of the day) or clock-out (second scan)
  - GPS coordinates captured on scan for geofencing validation

### 2.3 System / Background Processes (Automated)
- **Observers:** Event-driven logic triggered on model create/update/delete
- **Scheduled Tasks (Cron):**
  - `backup:run --only-db` — daily at 23:00 (database backup)
  - `db:seed RecalculatePresence` — daily at 23:30 (recalculate presence metrics)
- **CLI Commands:**
  - `salary:recalculate` — recalculate salary for specific months/users
  - `calculate:salary` — recalculate ALL salary recaps
  - `import:presence-command` — bulk import attendance from Excel

### 2.4 Firefly III (External System)
- **Role:** External double-entry accounting system
- **Integration:** REST API via Guzzle HTTP client
- **Direction:** Absensi → Firefly III (push-only, no pull)
- **Controlled by:** `ACC_ACTIVE` env flag (can be disabled)
- **Transactions synced:**
  - Salary payment → Withdrawal (`GAJIAN`)
  - Loan issued → Withdrawal (`KASBON`)
  - Loan payment → Deposit (`BAYARKASBON`)

---

## 3. Data Model (Entity Relationship)

```
┌──────────────┐     ┌──────────────┐     ┌──────────────────┐
│     User     │────→│   Schedule   │────→│  ScheduleDayOff  │
│              │  N:1│              │  1:N │                  │
│ - name       │     │ - name       │     │ - schedule_id    │
│ - email      │     │ - in  (time) │     │ - day (→ Days)   │
│ - password   │     │ - out (time) │     └──────────────────┘
│ - qr (uuid)  │     │ - over_in    │            │
│ - image      │     │ - over_out   │     ┌──────┴───────┐
│ - schedule_id│     └──────────────┘     │     Day      │
└──────┬───────┘                          │ - name       │
       │                                  │ (senin..     │
       │ 1:1                              │  minggu)     │
       ▼                                  └──────────────┘
┌──────────────┐
│    Salary    │  (salary config per employee)
│ - amount     │  base monthly salary
│ - overtime_amount │  per-overtime pay
│ - overtime_type   │  'hour' | 'flat'
│ - fine_type       │  'minute' | 'flat'
│ - fine            │  flat fine per late day
│ - fine_per_minute │  fine per late minute
│ - unpaid_leave_deduction │  per-day deduction for absence
│ - extra_time      │  rate per extra minute
│ - extra_time_rule │  1 = enabled, 0 = disabled
└──────────────┘

       │ 1:N (via user_id)
       ▼
┌──────────────────┐         ┌──────────────────┐
│    Presence      │────────→│   SalaryRecap    │
│                  │ triggers│                  │
│ - user_id        │ via     │ - user_id        │
│ - in  (datetime) │ observer│ - recap_month    │  format: "mm-YYYY"
│ - out (datetime) │         │ - work_day       │
│ - is_late        │         │ - late_day       │
│ - late_minute    │         │ - salary_amount  │
│ - is_overtime    │         │ - overtime_amount│
│ - lat / lng      │         │ - loan_cut       │
│ - outside        │         │ - late_cut       │
│ - extra_time     │         │ - abstain_cut    │
└──────────────────┘         │ - abstain_count  │
                             │ - late_minute_count │
                             │ - extra_time     │
                             │ - extra_time_amount │
                             │ - received       │  (net pay)
                             │ - paid           │  boolean
                             │ - method         │  'cash' | 'transfer'
                             │ - desc           │
                             │ - acc_id         │  → Firefly III
                             └────────┬─────────┘
                                      │
       ┌──────────────────────────────┤
       ▼                              ▼
┌──────────────┐              ┌──────────────┐
│     Loan     │              │ LoanPayment  │
│ - user_id    │              │ - user_id    │
│ - amount     │              │ - amount     │
│ - date       │              │ - date       │
│ - acc_id     │              │ - salary_recap_id │
└──────────────┘              │ - acc_id     │
                              └──────────────┘

┌──────────────────┐          ┌──────────────────┐
│ NationalHoliday  │          │  CompanyProfile  │
│ - date           │          │ - name           │
│ - info           │          │ - address        │
└──────────────────┘          │ - phone          │
                              │ - image (logo)   │
┌──────────────────┐          │ - id_card (bg)   │
│      Acc         │          └──────────────────┘
│ - code           │  e.g. "GAJIAN", "KASBON", "BAYARKASBON"
│ - source_id      │  → Firefly III account ID
│ - destination_id │  → Firefly III account ID
│ - source_name    │
│ - destination_name│
└──────────────────┘
```

---

## 4. Use Case Breakdown

### UC-1: Employee Clock In/Out (QR Scan)

```
Actor: Employee
Trigger: Employee opens /scan page and scans QR code
Precondition: Employee has a User record with a QR UUID and assigned Schedule
```

**Flow:**
1. Employee opens public scan page (`/scan`)
2. Browser activates camera via `instascan` JS library
3. Employee scans their personal QR code
4. Browser sends POST `/presence/record` with `{ qr, lat, lng }`
5. System looks up User by QR UUID
6. **PresenceService.writeRecord()** checks if a Presence exists for today:
   - **No existing record** → **Clock In**: create Presence with `in = now()`
   - **Existing record** → **Clock Out**: update Presence with `out = now()`
7. **PresenceService.updateCoordinate()** saves GPS coords & runs geofencing:
   - Haversine formula calculates distance from office (config `office_lat`/`office_lng`)
   - If distance > 100m → `outside = true`
8. Presence `save()` triggers **PresenceObserver**

**Geofencing Logic:**
```
Office coords from config('app.office_lat'), config('app.office_lng')
Distance = Haversine(employee_coords, office_coords)
outside = (distance > 100 meters)
```

---

### UC-2: Auto-Calculate Attendance Metrics (Observer-Driven)

```
Actor: System (PresenceObserver)
Trigger: Presence created or updated
```

**Flow (runs automatically after UC-1):**
1. **PresenceObserver.created() / updated()** fires
2. **calculateLate():**
   - Compare `presence.in` against `user.schedule.in`
   - If employee clocked in after schedule → `is_late = true`, `late_minute = diff`
   - If on time → `is_late = false`, `late_minute = 0`
3. **calculateOvertime():**
   - If today is a day-off for the user's schedule → `is_overtime = true` (working on off-day)
   - Else if `presence.out > schedule.over_in` → `is_overtime = true`
   - Else → `is_overtime = false`
4. **calculateExtraTime():**
   - If `presence.out > schedule.out` AND `presence.out < schedule.over_in`:
     - `extra_time` = minutes between schedule.out and presence.out (capped at overtime threshold)
5. **recalCulateCoordinate()** (on update only): re-validates geofencing
6. **SalaryService.recap()** → triggers UC-3

```
Timeline:
─────────────────────────────────────────────────────────────►
 schedule.in   presence.in    schedule.out   over_in    presence.out
     08:00       08:15          17:00        19:00        18:30
     │            │               │            │            │
     ├─ late ────►│               │            │            │
     │  15 min    │               ├─ extra ───►│            │
     │            │               │   time     │            │
     │            │               │  90 min    │            │
```

---

### UC-3: Auto-Create/Recalculate Salary Recap (Observer-Driven)

```
Actor: System (via PresenceObserver → SalaryService)
Trigger: Every Presence create/update
```

**Flow:**
1. Extract `recap_month` from `presence.in` (format: `mm-YYYY`)
2. Look up existing SalaryRecap for this user + month
3. **If no recap exists** → create one with zeroed fields
4. **If recap exists** → recalculate via `calculateSalaryRecap()`:

```
┌─────────────────────────────────────────────────────────┐
│                 SALARY CALCULATION                       │
│                                                         │
│  work_day = count(presences in this month)              │
│  late_day = sum(is_late)                                │
│  late_minute_count = sum(late_minute)                   │
│  extra_time = sum(extra_time)                           │
│                                                         │
│  salary_amount = user.salary.amount (base pay)          │
│  overtime_amount = sum(is_overtime) × overtime_rate     │
│  extra_time_amount = extra_time × extra_time_rate       │
│                     (if extra_time_rule == 1)           │
│                                                         │
│  ┌─── DEDUCTIONS ───────────────────────────────────┐   │
│  │ workdays_in_month = calendar_days - off_days     │   │
│  │                     - national_holidays          │   │
│  │                                                  │   │
│  │ abstain_count = workdays_in_month - work_day     │   │
│  │ abstain_cut = abstain_count × unpaid_leave_rate  │   │
│  │                                                  │   │
│  │ late_cut:                                        │   │
│  │   if fine_type == 'minute':                      │   │
│  │     = fine_per_minute × late_minute_count        │   │
│  │   else (flat):                                   │   │
│  │     = fine × late_day                            │   │
│  └──────────────────────────────────────────────────┘   │
│                                                         │
│  received = salary_amount                               │
│           + overtime_amount                              │
│           + extra_time_amount                           │
│           - loan_cut                                    │
│           - abstain_cut                                 │
│           - late_cut                                    │
└─────────────────────────────────────────────────────────┘
```

5. Save with `saveQuietly()` (prevents observer loop)
6. If recap is already `paid` → sync to Firefly III via `updateRecordSalaryToACC()`

---

### UC-4: Admin Sets Payment on Salary Recap

```
Actor: Admin
Trigger: Admin clicks "Bayar Cash" or "Bayar Transfer" on a salary recap row
```

**Flow:**
1. Admin navigates to Rekap Gaji (`/admin/salary-recap`)
2. Optionally filters by month
3. Clicks line button "Bayar Cash" or "Bayar Transfer"
4. System sets `recap.paid = 1`, `recap.method = 'cash'|'transfer'`
5. Saves with `saveQuietly()` (no observer trigger)
6. Explicitly calls `TransactionService.updateRecordSalaryToACC()`:
   - If no `acc_id` → creates new Firefly III transaction (type: withdrawal, code: `GAJIAN`)
   - If `acc_id` exists → updates existing Firefly III transaction
7. Flash success message, redirect to list

**Uncheck Payment Flow (UC-4b):**
When a recap's `paid` changes from 1→0 (via update form):
1. `SalaryRecapObserver.updated()` fires
2. `deleteWhenUncheck()` checks: if `acc_id` exists AND `paid == 0`:
   - Delete related `LoanPayment` + its ACC record
   - Delete salary ACC transaction from Firefly III
   - Set `acc_id = NULL`
   - Save quietly

---

### UC-5: Loan (Kasbon) Management

```
Actor: Admin
```

#### UC-5a: Create Loan
1. Admin navigates to Kasbon → Kasbon
2. Fills in: employee, amount, date
3. On save → `LoanCrudController.store()`:
   - Creates `Loan` record
   - Calls `TransactionService.recordLoanACC()` → creates withdrawal in Firefly III (code: `KASBON`)
   - Firefly III transaction ID saved as `loan.acc_id`

#### UC-5b: Loan Recap View
1. Admin navigates to Kasbon → Rekap
2. `LoanRepository.recap()` calculates per-user:
   - Total kasbon (sum of loans)
   - Total paid (sum of loan_payments)
   - Remaining balance (selisih)
3. Can drill down to detail per user
4. Can export recap to Excel or print to PDF

#### UC-5c: Auto Loan Deduction from Salary
When a SalaryRecap is updated (via observer):
1. If `loan_cut > 0`:
   - Find or create `LoanPayment` linked to this `salary_recap_id`
   - If recap is `paid` → sync to Firefly III (code: `BAYARKASBON`)
2. If `loan_cut == 0`:
   - Delete any existing `LoanPayment` for this recap
   - Delete ACC record

**Note:** `loan_cut` on SalaryRecap is manually editable by admin — it's NOT auto-calculated from outstanding loan balance.

---

### UC-6: Schedule & Day-Off Management

```
Actor: Admin
```

#### UC-6a: Create/Edit Schedule
1. Admin creates a schedule with:
   - `name` (e.g., "Shift Pagi")
   - `in` / `out` — regular work hours (e.g., 08:00 – 17:00)
   - `over_in` / `over_out` — overtime threshold hours (e.g., 19:00 – 22:00)
   - Day-off checkboxes (e.g., Sabtu, Minggu)
2. Day-offs stored in `ScheduleDayOff` pivot table linking to `Days` reference table

#### UC-6b: Mass Schedule Assignment
1. Admin opens "Setting Jadwal"
2. Sees all users with their current schedules
3. Can reassign schedules in bulk
4. On submit → iterates users and updates `schedule_id`

**Schedule affects:**
- Late calculation (presence.in vs schedule.in)
- Overtime detection (presence.out vs schedule.over_in)
- Extra time calculation (presence.out between schedule.out and schedule.over_in)
- Workday count (calendar days minus schedule day-offs)

---

### UC-7: Export & Print

```
Actor: Admin
```

| Feature | Output | Route | Format |
|---------|--------|-------|--------|
| Salary Recap Export | All recaps for a month | `/admin/salary-recap/export?salary_recap=mm-YYYY` | Excel (.xlsx) |
| Salary Slip Print | Individual or monthly batch | `/admin/salary-recap/print?id=X` or `?salary_recap=mm-YYYY` | PDF (slip) |
| User ID Card Print | Single or all users | `/admin/user/{id}/print` or `/admin/user/print-all` | PDF (card) |
| User Export | All users | `/admin/user/export` | Excel |
| Loan Recap Export | All loans summary | `/admin/loan/download` | Excel |
| Loan Detail Export | Per-user loan history | `/admin/loan/{id}/download-detail` | Excel |
| Loan Detail Print | Per-user loan history | `/admin/loan/{id}/print-detail` | PDF |

---

### UC-8: National Holiday Management

```
Actor: Admin
Trigger: Admin adds/removes holidays in the calendar year
```

**Flow:**
1. Admin adds holidays with `date` + `info` (description)
2. These holidays are subtracted from workday counts when calculating:
   - `unpaidLeaveDeduction()` — reduces expected workdays before computing absences
   - `getAbstain()` — reduces expected workdays for absence count

**Impact:** If an employee works 22 days and there are 23 workdays minus 2 holidays = 21 expected → employee has 0 absences (not penalized).

---

### UC-9: Accounting Integration (ACC / Firefly III)

```
Actor: System (TransactionService → Acc service)
External: Firefly III REST API
Gate: env('ACC_ACTIVE') must be truthy
```

**Architecture:**
```
┌───────────┐        ┌──────────────────┐       ┌──────────────┐
│  Absensi  │───────→│TransactionService│──────→│ Firefly III  │
│           │        │                  │  HTTP  │  /api/v1/    │
│ SalaryRecap│       │ recordSalaryToACC│  POST  │ transactions │
│ Loan       │       │ recordLoanACC    │  PUT   │              │
│ LoanPayment│       │ recordPayLoanACC │  DEL   │              │
└───────────┘        └──────────────────┘       └──────────────┘
```

**ACC Code Mapping** (stored in `accs` table):

| Code | Type | When |
|------|------|------|
| `GAJIAN` | Withdrawal | Salary paid to employee |
| `KASBON` | Withdrawal | Loan issued to employee |
| `BAYARKASBON` | Deposit | Loan repaid (deducted from salary) |

Each code maps `source_id` → `destination_id` (Firefly III account IDs).

**Sync Rules:**
- Create: new transaction in Firefly III, store `acc_id` locally
- Update: fetch existing transaction, merge updated fields, PUT
- Delete: DELETE transaction by `acc_id`
- `internal_reference`: `"ABSEN-{CODE}-{record_id}"` for traceability

---

## 5. Observer Chain (Event-Driven Flow)

### 5.1 Presence Observer

```
Presence::created / updated
    │
    ├─→ calculateLate(presence)         → is_late, late_minute
    ├─→ calculateOvertime(presence)     → is_overtime
    ├─→ calculateExtraTime(presence)    → extra_time
    ├─→ recalCulateCoordinate(presence) → outside  [update only]
    │
    └─→ SalaryService::recap(presence)
            │
            ├─ No SalaryRecap? → createSalaryRecap() → SalaryRecap::created (→ observer)
            └─ Has SalaryRecap? → calculateSalaryRecap() → saveQuietly() (no observer)
```

### 5.2 SalaryRecap Observer

```
SalaryRecap::created
    └─→ calculateSalaryRecap()         → compute all salary fields → saveQuietly()

SalaryRecap::updated
    ├─→ calculateSalaryRecap()         → recompute salary → saveQuietly()
    ├─→ payLoan()                      → create/update/delete LoanPayment
    └─→ deleteWhenUncheck()            → if paid→unpaid: delete ACC records

SalaryRecap::deleted
    └─→ removeLoanPayment()            → delete related LoanPayment + ACC record
```

### 5.3 User Observer
```
User::observe(UserObserver::class)     → registered but observer file not in source
                                         (likely handles QR generation or cascade)
```

---

## 6. Background Processes & Scheduled Tasks

### 6.1 Cron Schedule (Kernel.php)

| Time | Command | Purpose |
|------|---------|---------|
| 23:00 daily | `backup:run --only-db` | Database backup (via spatie/laravel-backup) |
| 23:30 daily | `db:seed RecalculatePresence` | Nightly recalculation of presence metrics for all records |

### 6.2 Artisan Commands

| Command | Purpose | Arguments |
|---------|---------|-----------|
| `salary:recalculate` | Recalculate salary for filtered set | `--month=mm-YYYY`, `--user=*` |
| `calculate:salary` | Recalculate ALL salary recaps | none |
| `import:presence-command` | Import attendance from Excel file | Reads `storage/app/public/recap_absen_januari.xlsx` |

---

## 7. Full Business Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         DAILY OPERATION FLOW                            │
│                                                                         │
│  ┌─────────┐    ┌──────────┐    ┌──────────────┐    ┌──────────────┐   │
│  │Employee │    │  QR Scan │    │   Presence   │    │  Presence    │   │
│  │  opens  │───→│  Camera  │───→│   Created    │───→│  Observer    │   │
│  │  /scan  │    │(instascan)│    │  (clock in)  │    │   fires     │   │
│  └─────────┘    └──────────┘    └──────────────┘    └──────┬───────┘   │
│                                                            │           │
│                                        ┌───────────────────┤           │
│                                        ▼                   ▼           │
│                                 ┌────────────┐    ┌──────────────┐    │
│                                 │ Calculate  │    │  Calculate   │    │
│                                 │   Late     │    │  Overtime    │    │
│                                 │ + Extra    │    │              │    │
│                                 └────────────┘    └──────────────┘    │
│                                        │                              │
│                                        ▼                              │
│                                 ┌──────────────┐                      │
│                                 │ SalaryService│                      │
│                                 │   .recap()   │                      │
│                                 └──────┬───────┘                      │
│                                        │                              │
│                          ┌─────────────┴─────────────┐                │
│                          ▼                           ▼                │
│                   ┌────────────┐              ┌────────────┐          │
│                   │   Create   │              │ Recalculate│          │
│                   │ SalaryRecap│              │ SalaryRecap│          │
│                   │  (if new)  │              │ (if exists)│          │
│                   └─────┬──────┘              └─────┬──────┘          │
│                         │                           │                 │
│                         ▼                           ▼                 │
│                  ┌─────────────┐             ┌────────────┐           │
│                  │  Observer   │             │ saveQuietly│           │
│                  │  → calc    │             │ (no loop)  │           │
│                  └─────────────┘             └────────────┘           │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                       MONTHLY PAYROLL FLOW                              │
│                                                                         │
│  ┌─────────┐    ┌──────────────┐    ┌──────────────┐                   │
│  │  Admin  │    │ View Salary  │    │  Edit Recap  │                   │
│  │  Login  │───→│  Recap List  │───→│  (loan_cut,  │                   │
│  └─────────┘    │ filter month │    │   desc, etc) │                   │
│                 └──────────────┘    └──────┬───────┘                   │
│                                            │                           │
│                                            ▼                           │
│                                    ┌──────────────┐                    │
│                                    │   Observer    │                    │
│                                    │  recalculate  │                    │
│                                    │  + payLoan()  │                    │
│                                    └──────┬───────┘                    │
│                                            │                           │
│                                            ▼                           │
│                 ┌─────────────┐    ┌──────────────┐    ┌────────────┐  │
│                 │ Set Payment │    │  Sync to ACC │    │  Export /  │  │
│                 │ Cash/Trans  │───→│  (Firefly)   │    │  Print PDF │  │
│                 └─────────────┘    └──────────────┘    └────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Route Map

### Admin Routes (auth required)

| Method | URI | Controller | Purpose |
|--------|-----|------------|---------|
| CRUD | `/admin/user` | UserCrudController | Employee management |
| GET | `/admin/user/{id}/print` | UserCrudController@print | Print single ID card |
| GET | `/admin/user/print-all` | UserCrudController@printAll | Print all ID cards |
| GET | `/admin/user/export` | UserCrudController@export | Export users to Excel |
| CRUD | `/admin/schedule` | ScheduleCrudController | Schedule management |
| GET | `/admin/schedule/view-update` | ScheduleCrudController@viewSchedule | Mass schedule assignment view |
| POST | `/admin/schedule/mass-update` | ScheduleCrudController@massUpdateSchedule | Save mass assignment |
| CRUD | `/admin/salary` | SalaryCrudController | Salary config per employee |
| CRUD | `/admin/presence` | PresenceCrudController | Attendance records |
| GET | `/admin/presence/scan` | PresenceCrudController@scan | QR scanner (admin) |
| POST | `/admin/presence/record` | PresenceCrudController@record | Process QR scan |
| CRUD | `/admin/salary-recap` | SalaryRecapCrudController | Salary recap CRUD |
| GET | `/admin/salary-recap/{id}/set-payment?method=X` | SetPaymentOperation@setPayment | Mark as paid |
| GET | `/admin/salary-recap/{id}/recalculate-salary` | SetPaymentOperation@recalculateSalary | Force recalculate |
| GET | `/admin/salary-recap/export` | SalaryRecapCrudController@export | Export to Excel |
| GET | `/admin/salary-recap/print` | SalaryRecapCrudController@print | Print salary slips |
| CRUD | `/admin/loan` | LoanCrudController | Loan management |
| GET | `/admin/loan/recap` | LoanCrudController@loanRecap | Loan summary view |
| GET | `/admin/loan/{id}/detail` | LoanCrudController@detail | Per-user loan detail |
| GET | `/admin/loan/download` | LoanCrudController@download | Export loan recap Excel |
| GET | `/admin/loan/{id}/download-detail` | LoanCrudController@downloadDetail | Export per-user Excel |
| GET | `/admin/loan/{id}/print-detail` | LoanCrudController@print | Print per-user PDF |
| CRUD | `/admin/loan-payment` | LoanPaymentCrudController | Loan payment management |
| CRUD | `/admin/national-holiday` | NationalHolidayCrudController | Holiday management |
| CRUD | `/admin/company-profile` | CompanyProfileCrudController | Company settings |
| CRUD | `/admin/acc` | AccCrudController | Accounting code mapping |

### Public Routes (no auth)

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/` | Redirect to scan page |
| GET | `/scan` | QR scanner for employees |
| POST | `/presence/record` | Process QR scan (no CSRF) |

---

## 9. Menu Structure (Admin Panel)

```
📊 Dashboard
👤 Users
📅 Absen
   ├── 📷 Scan
   ├── 📅 Jadwal (Schedule)
   ├── ⚙️ Setting Jadwal (Mass Assignment)
   ├── ✅ Kehadiran (Presence Records)
   └── ☀️ Libur Nasional
💰 Kasbon (Loans)
   ├── 📊 Rekap (Summary)
   ├── 💵 Kasbon (Loan List)
   └── 💳 Pembayaran Kasbon (Payments)
💸 Gajian (Payroll)
   ├── 💰 Gaji (Salary Config)
   └── 📋 Rekap Gaji (Salary Recap)
🏢 Profile Perusahaan (Company)
⚙️ Konfigurasi Akuntansi (ACC)
```

---

## 10. Key Business Rules

| # | Rule | Implementation |
|---|------|---------------|
| 1 | First QR scan of the day = clock in, second = clock out | `PresenceService.writeRecord()` — checks existing record for today |
| 2 | Late = clocked in after `schedule.in` | `PresenceService.calculateLate()` |
| 3 | Overtime = working on a day-off OR clocked out after `schedule.over_in` | `PresenceService.calculateOvertime()` |
| 4 | Extra time = stayed past `schedule.out` but before `schedule.over_in` | `PresenceService.calculateExtraTime()` — capped at max diff |
| 5 | Outside office = GPS distance > 100 meters from configured coords | `PresenceService.inCoordinate()` — Haversine formula |
| 6 | Salary auto-recalculates on every attendance change | `PresenceObserver` → `SalaryService.recap()` |
| 7 | Absence = expected workdays − actual workdays − national holidays | `SalaryService.getAbstain()` |
| 8 | Late fine can be per-minute or flat per-day (configurable per employee) | `Salary.fine_type` = 'minute' | 'flat' |
| 9 | Loan deduction is manually set by admin on salary recap | `SalaryRecap.loan_cut` — not auto-calculated from loan balance |
| 10 | Payment triggers accounting sync; unpayment reverses it | `SetPaymentOperation` + `SalaryService.deleteWhenUncheck()` |
| 11 | All financial operations wrapped in DB transaction | `SalaryService.calculateSalaryRecap()` — `DB::transaction()` |
| 12 | ACC sync can be disabled globally via `ACC_ACTIVE=false` | `TransactionService` — early return guard |

---

## 11. Technology Dependencies

| Component | Package | Purpose |
|-----------|---------|---------|
| Admin Panel | `backpack/crud` v6 | CRUD interface, auth, menu |
| PDF Generation | `barryvdh/laravel-dompdf` | Salary slips, ID cards, loan reports |
| Excel Export/Import | `maatwebsite/excel` | Salary recap, user, loan exports |
| QR Code | `simplesoftwareio/simple-qrcode` | Generate QR for employee ID |
| QR Scanner | `instascan` (npm) | Browser-based QR camera scanning |
| HTTP Client | `guzzlehttp/guzzle` | Firefly III API communication |
| Backup | `spatie/laravel-backup` | Nightly DB backup |
| Log Viewer | `opcodesio/log-viewer` | Web-based log inspection |
| Auth | Laravel Sanctum + Backpack auth | API tokens + admin panel auth |
