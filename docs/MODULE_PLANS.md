# Absensi → HRIS Evolution — Module Plans

> **Created:** 2026-08-06
> **Current State:** Attendance + Payroll + Loan + ACC Sync
> **Target:** Modular HRIS with self-service capabilities

---

## Dependency Graph

```
                    ┌─────────────────────┐
                    │  M0: Foundation     │
                    │  (Roles, Approval,  │
                    │   Audit Trail)      │
                    └──────────┬──────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ M1: Org         │ │ M2: Leave       │ │ M3: Notifikasi  │
│ Structure       │ │ Management      │ │                 │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         ▼                   ▼                   │
┌─────────────────┐ ┌─────────────────┐          │
│ M4: Employee    │ │ M5: Tax &       │          │
│ Self-Service    │ │ Compliance      │          │
└────────┬────────┘ └────────┬────────┘          │
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ M6: Employee    │ │ M7: Multi-      │ │ M8: Reporting   │
│ Documents       │ │ Branch          │ │ & Dashboard     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
         │
         ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ M9: Recruitment │ │ M10: Performance│ │ M11: Training   │
│ (optional)      │ │ (optional)      │ │ (optional)      │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

**Build Order:**
```
Sprint 1:  M0 (Foundation)           — wajib duluan, semua module depend ini
Sprint 2:  M1 (Org) + M3 (Notif)    — paralel
Sprint 3:  M2 (Leave)               — butuh M0 approval engine
Sprint 4:  M4 (Self-Service)        — butuh M1 + M2
Sprint 5:  M5 (Tax)                 — butuh salary data
Sprint 6:  M6 (Documents)           — butuh M4 portal
Sprint 7:  M7 (Multi-Branch)        — bisa kapan saja tapi invasive
Sprint 8:  M8 (Reporting)           — setelah data cukup kaya
Sprint 9+: M9/M10/M11              — optional, sesuai kebutuhan bisnis
```

---

## M0: Foundation (Roles, Approval Engine, Audit Trail)

### Kenapa Duluan
Semua module butuh: siapa yang boleh approve, siapa yang bisa akses apa, dan siapa ubah apa kapan. Tanpa ini, setiap module buat approval logic sendiri-sendiri → chaos.

### M0.1: Roles & Permissions

**Database:**
```sql
-- roles
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,     -- 'super_admin', 'hr_admin', 'manager', 'employee'
    display_name VARCHAR(100),
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- permissions
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,    -- 'presence.view', 'salary.edit', 'leave.approve'
    module VARCHAR(50),                   -- 'presence', 'salary', 'leave', etc
    display_name VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- role_permission (pivot)
CREATE TABLE role_permission (
    role_id BIGINT,
    permission_id BIGINT,
    PRIMARY KEY (role_id, permission_id)
);

-- user_role (pivot) — user bisa punya >1 role
CREATE TABLE user_role (
    user_id BIGINT,
    role_id BIGINT,
    PRIMARY KEY (user_id, role_id)
);
```

**Existing `users` table changes:**
```sql
ALTER TABLE users ADD COLUMN manager_id BIGINT NULL REFERENCES users(id);
```

**Implementation:**
- Package: `spatie/laravel-permission` (atau manual, Backpack punya built-in PermissionManager)
- Backpack sudah support `backpack/permissionmanager` — install & configure
- Middleware: `role:hr_admin`, `permission:leave.approve`
- Default roles: `super_admin`, `hr_admin`, `manager`, `employee`

**Routes (Admin):**
```
CRUD /admin/role          — RoleCrudController
CRUD /admin/permission    — PermissionCrudController
     /admin/user          — tambah field role assignment
```

**Key Permissions per Module:**
```
presence.*    — view, create, edit, delete, scan
salary.*      — view, edit, recalculate, pay
leave.*       — request, approve, reject, view_all, view_own
loan.*        — create, approve, view, delete
report.*      — view_dashboard, export
user.*        — create, edit, delete, assign_role
```

---

### M0.2: Approval Engine

**Database:**
```sql
CREATE TABLE approval_flows (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- 'Leave Request', 'Loan Request', 'Overtime Request'
    module VARCHAR(50) NOT NULL,          -- 'leave', 'loan', 'overtime'
    steps INT DEFAULT 1,                  -- jumlah level approval
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE approval_flow_steps (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    approval_flow_id BIGINT NOT NULL,
    step_order INT NOT NULL,              -- 1, 2, 3...
    approver_type ENUM('role', 'manager', 'specific_user') NOT NULL,
    approver_role_id BIGINT NULL,         -- if type = 'role'
    approver_user_id BIGINT NULL,         -- if type = 'specific_user'
    -- type = 'manager' → auto-resolve dari user.manager_id
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Generic approval records (polymorphic)
CREATE TABLE approvals (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    approvable_type VARCHAR(100) NOT NULL, -- 'App\Models\LeaveRequest', 'App\Models\Loan'
    approvable_id BIGINT NOT NULL,
    approval_flow_id BIGINT NOT NULL,
    current_step INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    requested_by BIGINT NOT NULL,          -- user_id
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (approvable_type, approvable_id),
    INDEX (status)
);

CREATE TABLE approval_actions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    approval_id BIGINT NOT NULL,
    step_order INT NOT NULL,
    action ENUM('approve', 'reject') NOT NULL,
    acted_by BIGINT NOT NULL,              -- user_id
    notes TEXT NULL,
    acted_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP
);
```

**Service: `ApprovalService`**
```
- submitForApproval(Model $approvable, User $requester): Approval
- approve(Approval $approval, User $approver, ?string $notes): void
- reject(Approval $approval, User $approver, string $reason): void
- cancel(Approval $approval, User $requester): void
- getNextApprover(Approval $approval): ?User
- getPendingForUser(User $user): Collection
```

**Trait: `HasApproval`** (attach ke model yang butuh approval)
```php
trait HasApproval {
    public function approval() { return $this->morphOne(Approval::class, 'approvable'); }
    public function submitForApproval(): Approval { ... }
    public function isApproved(): bool { ... }
    public function isPending(): bool { ... }
}
```

---

### M0.3: Audit Trail

**Database:**
```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NULL,
    action ENUM('create', 'update', 'delete', 'login', 'logout', 'approve', 'reject', 'export') NOT NULL,
    auditable_type VARCHAR(100) NOT NULL,  -- 'App\Models\SalaryRecap'
    auditable_id BIGINT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX (auditable_type, auditable_id),
    INDEX (user_id),
    INDEX (created_at)
);
```

**Implementation:**
- Package: `owen-it/laravel-auditing` atau manual trait
- Trait `Auditable` di-attach ke semua model yang perlu tracking
- Log viewer di admin panel: filter by user, model, date range
- Retention: keep 90 days, archive older to file

**Routes:**
```
GET /admin/audit-log         — list with filters
GET /admin/audit-log/{id}    — detail view (old vs new values)
```

**Effort:** ~3-4 hari (roles + approval + audit)

---

## M1: Organization Structure

### Problem
User sekarang flat — tidak ada departemen, jabatan, atau hierarki. Tidak bisa filter laporan per departemen, tidak bisa assign manager untuk approval.

### Database

```sql
CREATE TABLE departments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NULL UNIQUE,         -- 'IT', 'HR', 'FIN', 'OPS'
    parent_id BIGINT NULL,                -- for sub-departments
    head_user_id BIGINT NULL,             -- kepala departemen
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE positions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- 'Staff', 'Supervisor', 'Manager', 'Director'
    level INT DEFAULT 0,                  -- for hierarchy ordering
    department_id BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Extend users table
ALTER TABLE users ADD COLUMN department_id BIGINT NULL REFERENCES departments(id);
ALTER TABLE users ADD COLUMN position_id BIGINT NULL REFERENCES positions(id);
ALTER TABLE users ADD COLUMN employee_id VARCHAR(20) NULL UNIQUE;  -- NIK / NIP
ALTER TABLE users ADD COLUMN join_date DATE NULL;
ALTER TABLE users ADD COLUMN employment_status ENUM('active', 'probation', 'resigned', 'terminated') DEFAULT 'active';
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN address TEXT NULL;
```

### Use Cases

| UC | Aktor | Aksi |
|----|-------|------|
| M1-1 | Admin | CRUD departemen (support nested/sub-department) |
| M1-2 | Admin | CRUD jabatan, assign ke departemen |
| M1-3 | Admin | Assign user ke departemen + jabatan |
| M1-4 | Admin | Set kepala departemen (auto jadi approver) |
| M1-5 | Admin | Lihat org chart (tree view) |
| M1-6 | Admin | Filter semua laporan by departemen |

### Routes
```
CRUD /admin/department       — DepartmentCrudController
CRUD /admin/position         — PositionCrudController
GET  /admin/org-chart        — tree visualization
```

### Impact ke Module Lain
- **Salary recap** → bisa filter export per departemen
- **Approval** → kepala departemen jadi default approver
- **Reporting** → breakdown per departemen
- **Leave** → saldo cuti bisa beda per jabatan level

### Effort: ~2-3 hari

---

## M2: Leave Management

### Problem
Sekarang karyawan tidak hadir = langsung dianggap **absen** dan **kena potong gaji**. Padahal bisa jadi cuti, izin, atau sakit. Ini bug bisnis paling kritis.

### Database

```sql
-- Jenis cuti
CREATE TABLE leave_types (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- 'Cuti Tahunan', 'Sakit', 'Izin', 'Cuti Melahirkan'
    code VARCHAR(20) NOT NULL UNIQUE,     -- 'annual', 'sick', 'permission', 'maternity'
    is_paid BOOLEAN DEFAULT TRUE,         -- cuti berbayar atau tidak
    default_quota INT NULL,               -- default saldo per tahun (null = unlimited, e.g. sakit)
    max_consecutive_days INT NULL,         -- max hari berturut-turut
    requires_attachment BOOLEAN DEFAULT FALSE,  -- e.g. surat dokter untuk sakit >1 hari
    is_active BOOLEAN DEFAULT TRUE,
    color VARCHAR(7) DEFAULT '#3498db',   -- untuk calendar view
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Saldo cuti per karyawan per tahun
CREATE TABLE leave_balances (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    leave_type_id BIGINT NOT NULL,
    year INT NOT NULL,                    -- 2026
    quota INT NOT NULL,                   -- total jatah
    used INT DEFAULT 0,
    remaining INT GENERATED ALWAYS AS (quota - used) STORED,
    carry_over INT DEFAULT 0,             -- sisa tahun lalu yang dibawa
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (user_id, leave_type_id, year)
);

-- Pengajuan cuti
CREATE TABLE leave_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    leave_type_id BIGINT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,              -- calculated, exclude weekends/holidays
    reason TEXT NULL,
    attachment VARCHAR(255) NULL,          -- file path (surat dokter, etc)
    status ENUM('draft', 'pending', 'approved', 'rejected', 'cancelled') DEFAULT 'draft',
    approved_by BIGINT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (user_id, status),
    INDEX (start_date, end_date)
);

-- Detail per hari (untuk half-day support & calendar view)
CREATE TABLE leave_request_dates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    leave_request_id BIGINT NOT NULL,
    date DATE NOT NULL,
    day_value DECIMAL(2,1) DEFAULT 1.0,   -- 1.0 = full day, 0.5 = half day
    UNIQUE (leave_request_id, date)
);
```

### Use Cases

| UC | Aktor | Aksi |
|----|-------|------|
| M2-1 | Admin | CRUD jenis cuti (leave_types) |
| M2-2 | Admin | Set saldo cuti per karyawan per tahun |
| M2-3 | Admin | Bulk generate saldo cuti awal tahun (all employees) |
| M2-4 | Employee/Admin | Ajukan cuti: pilih jenis, tanggal mulai-selesai, alasan |
| M2-5 | System | Auto-hitung total hari (skip weekends + holidays + off days) |
| M2-6 | Manager/Admin | Approve / reject pengajuan cuti |
| M2-7 | System | Setelah approved: kurangi saldo, update leave_balance.used |
| M2-8 | System | **Integrasi salary**: hari cuti TIDAK dihitung sebagai absen |
| M2-9 | Admin | Calendar view: lihat siapa cuti kapan |
| M2-10 | System | Carry-over saldo cuti ke tahun berikutnya (configurable) |
| M2-11 | Admin | Laporan: rekap cuti per karyawan, per departemen, per jenis |

### Critical Integration: Salary Calculation

**Current problem in `SalaryService.getAbstain()`:**
```php
// SEKARANG: semua hari tidak hadir = absen
abstain_count = workdays_in_month - work_day

// SEHARUSNYA:
abstain_count = workdays_in_month - work_day - approved_leave_days
```

**Fix required:**
```php
public function getAbstain(SalaryRecap $salaryRecap){
    $workDayInMonth = $this->workdayInAMonth($salaryRecap);
    $workDayInMonth -= $this->countOfNationalHoliday($salaryRecap);

    // NEW: subtract approved leave days
    $approvedLeaveDays = $this->getApprovedLeaveDays($salaryRecap);

    return max(0, $workDayInMonth - $salaryRecap->work_day - $approvedLeaveDays);
}

public function unpaidLeaveDeduction(SalaryRecap $salaryRecap, Salary $salary){
    $workDayInMonth = $this->workdayInAMonth($salaryRecap);
    $workDayInMonth -= $this->countOfNationalHoliday($salaryRecap);

    // NEW: only count UNPAID leave as deduction
    $paidLeaveDays = $this->getPaidLeaveDays($salaryRecap);
    $unpaidLeaveDays = $this->getUnpaidLeaveDays($salaryRecap);

    $actualAbsent = $workDayInMonth - $salaryRecap->work_day - $paidLeaveDays - $unpaidLeaveDays;
    $deductibleDays = max(0, $actualAbsent) + $unpaidLeaveDays;

    return $deductibleDays * $salary->unpaid_leave_deduction;
}
```

### Service: `LeaveService`
```
- requestLeave(User, LeaveType, startDate, endDate, ?reason, ?attachment): LeaveRequest
- calculateLeaveDays(startDate, endDate, User): int  // skip off-days + holidays
- approve(LeaveRequest, User approver): void
- reject(LeaveRequest, User approver, string reason): void
- cancel(LeaveRequest, User): void
- getBalance(User, LeaveType, year): LeaveBalance
- generateYearlyBalances(int year): void  // bulk generate for all employees
- carryOver(int fromYear, int toYear, ?int maxCarry): void
- getApprovedLeaveDaysInMonth(User, string recapMonth): int
```

### Routes
```
CRUD  /admin/leave-type              — LeaveTypeCrudController
CRUD  /admin/leave-balance           — LeaveBalanceCrudController
CRUD  /admin/leave-request           — LeaveRequestCrudController (admin view all)
POST  /admin/leave-request/{id}/approve
POST  /admin/leave-request/{id}/reject
GET   /admin/leave-calendar          — calendar view
GET   /admin/leave-report            — rekap per departemen

# Employee self-service (M4):
GET   /my/leave                      — list my leave requests
POST  /my/leave                      — submit leave request
GET   /my/leave/balance              — my leave balances
DELETE /my/leave/{id}                — cancel pending request
```

### Effort: ~5-7 hari

---

## M3: Notification System

### Problem
Tidak ada notifikasi. Admin tidak tahu siapa yang telat hari ini. Karyawan tidak tahu gajinya sudah dibayar. Manager tidak tahu ada approval pending.

### Database

```sql
CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,              -- recipient
    type VARCHAR(100) NOT NULL,           -- 'leave_approved', 'salary_paid', 'late_alert'
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data JSON NULL,                       -- extra payload: { leave_request_id: 5 }
    channel ENUM('database', 'whatsapp', 'email', 'push') DEFAULT 'database',
    read_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (user_id, read_at),
    INDEX (type)
);

CREATE TABLE notification_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    type VARCHAR(100) NOT NULL,           -- 'leave_approved', 'salary_paid'
    channel_database BOOLEAN DEFAULT TRUE,
    channel_email BOOLEAN DEFAULT FALSE,
    channel_whatsapp BOOLEAN DEFAULT FALSE,
    UNIQUE (user_id, type)
);
```

### Notification Types

| Event | Recipient | Channel |
|-------|-----------|---------|
| Karyawan telat hari ini | Admin/HR | Database, WA |
| Karyawan scan di luar radius | Admin/HR | Database |
| Leave request submitted | Manager/Approver | Database, WA |
| Leave request approved/rejected | Employee | Database, WA |
| Salary recap paid | Employee | Database |
| Loan created | Employee | Database |
| Pending approvals reminder | Manager | Database, Email (daily digest) |
| Leave balance low (<3 days) | Employee | Database |

### Implementation
- Use Laravel's built-in `Notification` system
- Channels: `database` (always), `mail` (optional), custom `WhatsAppChannel`
- WhatsApp: via Fonnte API / WA Gateway (banyak provider lokal ID)
- Bell icon di admin panel header: unread count + dropdown

### Service: `NotificationService`
```
- notify(User $recipient, string $type, array $data): void
- notifyRole(string $role, string $type, array $data): void
- markAsRead(Notification $notification): void
- markAllRead(User $user): void
- getUnread(User $user): Collection
- sendDailyDigest(): void  // scheduled command
```

### Routes
```
GET    /admin/notifications              — list
POST   /admin/notifications/mark-read    — mark all read
GET    /api/notifications/unread-count   — for AJAX polling / badge
```

### Scheduled
```
daily 08:15  → kirim alert "X orang belum absen hari ini"
daily 17:00  → kirim alert "X orang belum clock-out"
weekly Mon   → digest pending approvals ke semua manager
```

### Effort: ~3-4 hari

---

## M4: Employee Self-Service Portal

### Problem
Karyawan sekarang cuma bisa scan QR. Tidak bisa lihat slip gaji, saldo cuti, riwayat kehadiran, atau ajukan cuti/izin sendiri.

### Architecture

```
┌──────────────────────────────────────────────┐
│              ADMIN PANEL (existing)           │
│         /admin/* — Backpack CRUD              │
│         Role: super_admin, hr_admin           │
└──────────────────────────────────────────────┘

┌──────────────────────────────────────────────┐
│           EMPLOYEE PORTAL (new)              │
│         /my/* — Custom Laravel controllers    │
│         Role: employee, manager              │
│         Auth: separate guard or same guard    │
└──────────────────────────────────────────────┘
```

### Features

| Feature | Route | Description |
|---------|-------|-------------|
| Dashboard | `GET /my` | Summary: kehadiran bulan ini, saldo cuti, gaji terakhir |
| Attendance History | `GET /my/attendance` | List kehadiran + filter bulan. Status: tepat/telat/lembur |
| Salary Slips | `GET /my/salary` | List rekap gaji per bulan, download PDF slip |
| Salary Detail | `GET /my/salary/{id}` | Detail 1 slip: breakdown gaji, potongan, net |
| Leave Balance | `GET /my/leave/balance` | Saldo per jenis cuti |
| Leave Request | `POST /my/leave` | Form ajukan cuti |
| Leave History | `GET /my/leave` | Status pengajuan: pending/approved/rejected |
| Loan History | `GET /my/loan` | Riwayat kasbon + pembayaran + sisa |
| Profile | `GET /my/profile` | Lihat & edit data pribadi (nama, foto, phone, alamat) |
| Change Password | `POST /my/password` | Ganti password sendiri |
| Notifications | `GET /my/notifications` | List notifikasi + mark read |

### Auth
- Same `users` table, same guard
- Middleware `role:employee` untuk `/my/*`
- Login page: `/login` (single login, redirect based on role)
  - `super_admin` / `hr_admin` → `/admin/dashboard`
  - `employee` / `manager` → `/my`

### UI
- Opsi 1: **Blade + Tailwind** — simple, server-rendered, mobile-friendly
- Opsi 2: **Inertia + Vue/React** — SPA feel, tapi masih monolith
- Rekomendasi: **Blade + Tailwind** karena scope kecil dan tidak perlu complexity SPA

### Key Controller: `EmployeePortalController`
```
- dashboard(): View — summary cards
- attendance(Request): View — paginated attendance with month filter
- salaryIndex(): View — list salary recaps for auth user
- salaryShow(SalaryRecap): View — detail + download PDF
- leaveIndex(): View — my leave requests
- leaveCreate(): View — form
- leaveStore(Request): RedirectResponse — validate & submit
- leaveCancel(LeaveRequest): RedirectResponse
- loanIndex(): View — my loans + payments
- profile(): View — my profile
- profileUpdate(Request): RedirectResponse
- changePassword(Request): RedirectResponse
```

### Effort: ~5-7 hari

---

## M5: Tax & Compliance (PPh 21, BPJS)

### Problem
Admin hitung pajak manual di luar sistem. BPJS tidak tercatat. THR tidak ada.

### Database

```sql
-- Konfigurasi pajak karyawan
CREATE TABLE employee_tax_profiles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL UNIQUE,
    npwp VARCHAR(30) NULL,
    tax_status ENUM('TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3','K/I/0','K/I/1','K/I/2','K/I/3') NOT NULL,
    -- TK = Tidak Kawin, K = Kawin, I = Istri bekerja
    -- /0 /1 /2 /3 = jumlah tanggungan
    tax_method ENUM('gross', 'gross_up', 'nett') DEFAULT 'gross',
    bpjs_kesehatan BOOLEAN DEFAULT TRUE,
    bpjs_ketenagakerjaan BOOLEAN DEFAULT TRUE,
    bpjs_tk_jht BOOLEAN DEFAULT TRUE,      -- Jaminan Hari Tua
    bpjs_tk_jp BOOLEAN DEFAULT TRUE,       -- Jaminan Pensiun
    bpjs_tk_jkk BOOLEAN DEFAULT TRUE,      -- Jaminan Kecelakaan Kerja
    bpjs_tk_jkm BOOLEAN DEFAULT TRUE,      -- Jaminan Kematian
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- PTKP table (updated yearly by government)
CREATE TABLE ptkp_rates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    status VARCHAR(10) NOT NULL,           -- 'TK/0', 'K/1', etc
    amount BIGINT NOT NULL,                -- e.g. 54000000 for TK/0
    UNIQUE (year, status)
);

-- PPh 21 bracket rates (progressive tax)
CREATE TABLE pph21_brackets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    lower_bound BIGINT NOT NULL,
    upper_bound BIGINT NULL,               -- NULL for last bracket
    rate DECIMAL(5,2) NOT NULL,            -- 5.00, 15.00, 25.00, 30.00, 35.00
    UNIQUE (year, lower_bound)
);

-- BPJS rates (configurable, changes periodically)
CREATE TABLE bpjs_rates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    type VARCHAR(30) NOT NULL,             -- 'kesehatan', 'jht', 'jp', 'jkk', 'jkm'
    employer_rate DECIMAL(5,2) NOT NULL,   -- % ditanggung perusahaan
    employee_rate DECIMAL(5,2) NOT NULL,   -- % dipotong dari gaji
    max_salary BIGINT NULL,                -- batas atas perhitungan (e.g. JP max 10.042.300)
    UNIQUE (year, type)
);

-- Extend salary_recaps: add tax & benefit columns
ALTER TABLE salary_recaps ADD COLUMN pph21 BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_kes_employee BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_kes_employer BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jht_employee BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jht_employer BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jp_employee BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jp_employer BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jkk BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bpjs_jkm BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN thr BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN bonus BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN gross_income BIGINT DEFAULT 0;
ALTER TABLE salary_recaps ADD COLUMN net_income BIGINT DEFAULT 0;
```

### Salary Calculation Flow (Updated)

```
┌─────────────────────────────────────────────────────────────┐
│  GROSS INCOME                                               │
│  = salary_amount + overtime + extra_time_amount             │
│    + thr + bonus                                            │
│                                                             │
│  DEDUCTIONS (employee share)                                │
│  = loan_cut + late_cut + abstain_cut                        │
│    + bpjs_kes_employee (1%)                                 │
│    + bpjs_jht_employee (2%)                                 │
│    + bpjs_jp_employee (1%)                                  │
│    + pph21 (progressive tax on taxable income)              │
│                                                             │
│  COMPANY COST (tidak dipotong dari gaji, tapi dicatat)      │
│  = bpjs_kes_employer (4%)                                   │
│    + bpjs_jht_employer (3.7%)                               │
│    + bpjs_jp_employer (2%)                                  │
│    + bpjs_jkk (0.24%-1.74% based on risk)                  │
│    + bpjs_jkm (0.3%)                                       │
│                                                             │
│  NET INCOME (take-home pay)                                 │
│  = gross_income - all_employee_deductions                   │
└─────────────────────────────────────────────────────────────┘

PPh 21 Calculation (monthly, TER method since 2024):
1. Gross monthly income (include tunjangan, exclude reimbursement)
2. Annual projection = monthly × 12
3. Subtract biaya jabatan (5%, max 6jt/tahun)
4. Subtract BPJS JHT + JP employee share
5. Subtract PTKP based on tax_status
6. Taxable income = step 4 result
7. Apply progressive rate: 5% / 15% / 25% / 30% / 35%
8. Monthly PPh 21 = annual tax ÷ 12
```

### Service: `TaxService`
```
- calculatePPh21(User, int grossMonthly, int month, int year): int
- calculateBPJS(User, int baseSalary): array  // returns all components
- calculateTHR(User, Carbon joinDate): int     // prorated if <12 months
- getApplicablePTKP(User, int year): int
- generateAnnualTaxReport(int year): Collection  // for SPT reporting
```

### Routes
```
CRUD /admin/tax-profile           — per employee tax config
CRUD /admin/ptkp-rate             — PTKP rates management
CRUD /admin/pph21-bracket         — tax bracket management
CRUD /admin/bpjs-rate             — BPJS rate management
GET  /admin/tax-report/{year}     — annual tax summary
GET  /admin/bpjs-report/{month}   — monthly BPJS summary for submission
```

### Effort: ~7-10 hari (complex calculation, need thorough testing)

---

## M6: Employee Documents

### Problem
Tidak ada penyimpanan dokumen karyawan: kontrak, KTP, NPWP, BPJS card, surat peringatan, dll.

### Database

```sql
CREATE TABLE document_types (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- 'KTP', 'NPWP', 'Kontrak Kerja', 'BPJS', 'Ijazah'
    code VARCHAR(20) NOT NULL UNIQUE,
    has_expiry BOOLEAN DEFAULT FALSE,     -- true for KTP, kontrak
    is_required BOOLEAN DEFAULT FALSE,    -- required for onboarding
    max_file_size_mb INT DEFAULT 5,
    allowed_extensions VARCHAR(100) DEFAULT 'pdf,jpg,png',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE employee_documents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    document_type_id BIGINT NOT NULL,
    file_path VARCHAR(255) NOT NULL,      -- storage path
    file_name VARCHAR(255) NOT NULL,      -- original filename
    file_size INT NOT NULL,               -- bytes
    document_number VARCHAR(100) NULL,    -- nomor KTP, nomor kontrak, etc
    issued_date DATE NULL,
    expiry_date DATE NULL,
    notes TEXT NULL,
    uploaded_by BIGINT NOT NULL,          -- user_id
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (user_id),
    INDEX (expiry_date)
);
```

### Use Cases

| UC | Aktor | Aksi |
|----|-------|------|
| M6-1 | Admin | CRUD document types |
| M6-2 | Admin/Employee | Upload dokumen karyawan |
| M6-3 | Admin | Lihat semua dokumen karyawan |
| M6-4 | System | Alert dokumen yang akan expired (30 hari sebelum) |
| M6-5 | Admin | Checklist kelengkapan dokumen per karyawan |
| M6-6 | Employee | Lihat & download dokumen sendiri di portal |

### Effort: ~2-3 hari

---

## M7: Multi-Branch

### Problem
`CompanyProfile::first()` — hardcoded single company. Tidak bisa handle multi cabang.

### Database

```sql
CREATE TABLE branches (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_profile_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NULL UNIQUE,
    address TEXT NULL,
    phone VARCHAR(20) NULL,
    lat DECIMAL(10,7) NULL,               -- for geofencing per branch
    lng DECIMAL(10,7) NULL,
    radius_meters INT DEFAULT 100,        -- geofence radius per branch
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Extend users
ALTER TABLE users ADD COLUMN branch_id BIGINT NULL REFERENCES branches(id);

-- Extend presences (track which branch they scanned at)
ALTER TABLE presences ADD COLUMN branch_id BIGINT NULL REFERENCES branches(id);
```

### Impact
- **Geofencing**: sekarang pakai `config('app.office_lat')` global. Setelah ini, per-branch coordinates
- **Reports**: filter per cabang
- **Salary**: bisa beda salary structure per cabang
- **Schedule**: bisa beda schedule per cabang
- **Admin access**: admin bisa di-scope ke cabang tertentu

### Invasiveness: HIGH
Ini affect hampir semua query yang sekarang global. Butuh:
1. Global scope `BranchScope` yang auto-filter by user's branch
2. Admin yang bisa lihat cross-branch vs single-branch
3. Migration semua existing data ke default branch

### Effort: ~5-7 hari (karena refactor existing code)

---

## M8: Reporting & Dashboard

### Problem
Tidak ada dashboard. Tidak ada analytics. Semua data ada tapi tidak di-visualize.

### Dashboard Cards (Admin)

```
┌──────────────────────────────────────────────────────────────┐
│  TODAY                          │  THIS MONTH               │
│  ┌─────────┐ ┌─────────┐       │  ┌─────────┐ ┌─────────┐  │
│  │ Hadir   │ │ Belum   │       │  │Total    │ │ Total   │  │
│  │  12     │ │ Absen 3 │       │  │Gaji     │ │ Lembur  │  │
│  └─────────┘ └─────────┘       │  │45.2 jt  │ │ 3.1 jt  │  │
│  ┌─────────┐ ┌─────────┐       │  └─────────┘ └─────────┘  │
│  │ Telat   │ │ Di luar │       │  ┌─────────┐ ┌─────────┐  │
│  │   2     │ │ radius 1│       │  │ Kasbon  │ │ Potongan│  │
│  └─────────┘ └─────────┘       │  │ 8.5 jt  │ │ 1.2 jt  │  │
│                                 │  └─────────┘ └─────────┘  │
├─────────────────────────────────┴────────────────────────────┤
│  ATTENDANCE TREND (12 months)                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  ████████████████████████████████████████████████     │    │
│  │  Chart: avg attendance rate per month                │    │
│  └──────────────────────────────────────────────────────┘    │
├──────────────────────────────────────────────────────────────┤
│  TOP LATECOMERS         │  LEAVE CALENDAR (this week)       │
│  1. Farhan  - 8x        │  Mon: Ahmad (Cuti)                │
│  2. Agung   - 5x        │  Tue: -                           │
│  3. Risma   - 3x        │  Wed: Exka (Sakit)                │
└──────────────────────────┴───────────────────────────────────┘
```

### Reports

| Report | Grouping | Export |
|--------|----------|--------|
| Attendance Summary | per employee, per month, per department | Excel, PDF |
| Late Report | per employee, trend | Excel |
| Overtime Report | per employee, per month | Excel |
| Salary Report | per month, per department | Excel, PDF |
| Loan Outstanding | per employee | Excel, PDF |
| Leave Usage | per employee, per type, per department | Excel |
| Tax Summary (PPh 21) | per year, per employee | Excel (for SPT) |
| BPJS Summary | per month | Excel (for BPJS submission) |
| Headcount | active vs resigned, per department | Excel |

### Implementation
- Dashboard: Blade + Chart.js (atau ApexCharts)
- Reports: dedicated controller, reuse existing export infrastructure (Maatwebsite Excel)
- Cache dashboard stats per hari (invalidate on data change)

### Routes
```
GET /admin/dashboard              — override default Backpack dashboard
GET /admin/report/attendance      — filter: month, department, employee
GET /admin/report/salary          — filter: month, department
GET /admin/report/leave           — filter: year, department, type
GET /admin/report/loan            — filter: status, employee
GET /admin/report/tax             — filter: year
GET /admin/report/bpjs            — filter: month
```

### Effort: ~5-7 hari

---

## M9: Recruitment (Optional)

### Scope
Basic recruitment pipeline: job posting → applicant → interview → hire → auto-create user.

### Database
```sql
CREATE TABLE job_postings (
    id, title, department_id, position_id, description,
    requirements, salary_range_min, salary_range_max,
    status ENUM('draft','open','closed'), open_date, close_date
);

CREATE TABLE applicants (
    id, job_posting_id, name, email, phone,
    resume_path, cover_letter, source,
    status ENUM('applied','screening','interview','offered','hired','rejected'),
    notes, applied_at
);

CREATE TABLE interviews (
    id, applicant_id, interviewer_id (user),
    scheduled_at, type ENUM('phone','onsite','video'),
    result ENUM('pass','fail','pending'), notes
);
```

### Effort: ~5-7 hari
### Priority: LOW — biasanya recruitment pakai tools terpisah (LinkedIn, JobStreet, dll)

---

## M10: Performance Management (Optional)

### Scope
KPI setting → periodic review → rating.

### Database
```sql
CREATE TABLE review_periods (
    id, name, start_date, end_date,
    status ENUM('setup','active','review','closed')
);

CREATE TABLE kpis (
    id, user_id, review_period_id, title, description,
    target_value, actual_value, weight DECIMAL(5,2),
    score DECIMAL(5,2) NULL, status
);

CREATE TABLE performance_reviews (
    id, user_id, review_period_id, reviewer_id,
    self_rating, manager_rating, final_rating,
    strengths TEXT, improvements TEXT, status
);
```

### Effort: ~7-10 hari
### Priority: LOW — biasanya perusahaan kecil belum butuh formal KPI system

---

## M11: Training & Development (Optional)

### Scope
Track training history, certifications, skills.

### Database
```sql
CREATE TABLE trainings (
    id, name, provider, description,
    start_date, end_date, cost, max_participants
);

CREATE TABLE training_participants (
    id, training_id, user_id, status ENUM('registered','attended','completed','absent'),
    certificate_path, score
);

CREATE TABLE certifications (
    id, user_id, name, issuer, issued_date, expiry_date, credential_id, file_path
);
```

### Effort: ~3-5 hari
### Priority: LOW

---

## Summary: Effort & Priority Matrix

```
                     HIGH IMPACT
                         │
     M2: Leave ●─────────┼──────────● M5: Tax
     (5-7 days)          │          (7-10 days)
                         │
     M4: Self-Service ●──┼──────● M8: Reporting
     (5-7 days)          │       (5-7 days)
                         │
  ───────────────────────┼─────────────────────── EFFORT
                         │
     M0: Foundation ●────┼────● M7: Multi-Branch
     (3-4 days)          │    (5-7 days)
                         │
     M1: Org ●───────────┼──● M3: Notifikasi
     (2-3 days)          │   (3-4 days)
                         │
     M6: Documents ●─────┼
     (2-3 days)          │
                         │
                     LOW IMPACT
```

| Module | Effort | Priority | Depends On |
|--------|--------|----------|------------|
| **M0: Foundation** | 3-4 hari | 🔴 CRITICAL | - |
| **M1: Org Structure** | 2-3 hari | 🟠 HIGH | M0 |
| **M2: Leave Management** | 5-7 hari | 🔴 CRITICAL | M0 |
| **M3: Notification** | 3-4 hari | 🟠 HIGH | M0 |
| **M4: Self-Service** | 5-7 hari | 🟠 HIGH | M0, M1, M2 |
| **M5: Tax & BPJS** | 7-10 hari | 🟡 MEDIUM | M0 |
| **M6: Documents** | 2-3 hari | 🟡 MEDIUM | M4 |
| **M7: Multi-Branch** | 5-7 hari | 🟡 MEDIUM | M0, M1 |
| **M8: Reporting** | 5-7 hari | 🟡 MEDIUM | M0-M5 |
| **M9: Recruitment** | 5-7 hari | ⚪ LOW | M0, M1 |
| **M10: Performance** | 7-10 hari | ⚪ LOW | M0, M1 |
| **M11: Training** | 3-5 hari | ⚪ LOW | M0 |

**Total estimated: ~55-75 hari kerja** untuk full HRIS
**MVP (M0+M1+M2+M3+M4): ~20-25 hari kerja** untuk usable employee self-service with leave management

---

## Recommended Phased Rollout

### Phase A — Foundation + Quick Wins (Week 1-2)
```
M0: Roles & Permissions + Approval Engine + Audit Trail
M1: Org Structure (Department, Position)
M3: Basic Notifications (database channel)
```
Deliverable: Admin bisa assign role, departemen terstruktur, audit trail aktif.

### Phase B — Leave + Self-Service (Week 3-5)
```
M2: Leave Management (full)
M4: Employee Self-Service Portal
```
Deliverable: Karyawan bisa login, lihat gaji, ajukan cuti, cuti tidak dihitung absen.

### Phase C — Compliance (Week 6-8)
```
M5: PPh 21 + BPJS calculation
M6: Employee Documents
M8: Reporting & Dashboard
```
Deliverable: Payroll lengkap dengan pajak, dokumen tersimpan, dashboard analytics.

### Phase D — Scale (Week 9+)
```
M7: Multi-Branch (if needed)
M9-M11: Recruitment, Performance, Training (as needed)
```
