# Absensi — Code Improvement Plan

> Generated: 2026-08-06
> Stack: Laravel + Backpack CRUD, Vite, DomPDF, Maatwebsite Excel, Firefly III (ACC)

---

## Phase 1 — Critical Fixes (Breaking Bugs) ✅ DONE

### 1.1 `removeLoanPayment()` Null Crash
**File:** `app/Services/SalaryService.php` (line 221–228)
**Problem:** `SalaryRecapObserver::deleted()` calls `removeLoanPayment()`, which queries `LoanPayment` but never checks for `null`. Deleting a `SalaryRecap` with no loan payment throws a fatal error.
**Fix:**
```php
public function removeLoanPayment(SalaryRecap $salaryRecap)
{
    $loan = LoanPayment::where('salary_recap_id', $salaryRecap->id)->first();
    if (!$loan) {
        return;
    }
    $this->transactionService->deleteRecordPayLoanAcc($loan);
    $loan->delete();
}
```

### 1.2 `User::presense()` — Swapped Keys + Typo
**File:** `app/Models/User.php` (line 55–57)
**Problem:** Foreign/local keys are reversed AND the method name is a typo. Returns wrong data.
```php
// BROKEN
public function presense(){
    return $this->hasMany(Presence::class, 'id', 'user_id');
}
```
**Fix:**
```php
public function presence(){
    return $this->hasMany(Presence::class, 'user_id', 'id');
}
```
> Search codebase for `presense` and rename all references to `presence`.

### 1.3 Export Shows Wrong Payment Status
**File:** `app/Exports/SalaryRecapExport.php` (line 86)
**Problem:** `$row->status` doesn't exist on the model. The field is `paid`. Column always shows "Tidak".
**Fix:**
```php
$row->paid ? 'Ya' : 'Tidak',
```

### 1.4 `$fillable` + `$guarded = []` Conflict
**File:** `app/Models/Presence.php` (line 13 & 26)
**Problem:** `$guarded = []` overrides `$fillable`, allowing mass assignment on ALL fields. Calculated fields like `is_late`, `is_overtime`, `outside` can be overwritten via request input.
**Fix:** Remove `protected $guarded = [];` — let `$fillable` control mass assignment.

---

## Phase 2 — Logic & Data Integrity Bugs ✅ DONE

### 2.1 Double ACC Transaction on Payment
**File:** `app/Http/Controllers/Admin/Operations/SetPaymentOperation.php`
**Problem:** `setPayment()` calls `$recap->save()` (triggers observer → `updateRecordSalaryToACC`), then explicitly calls `updateRecordSalaryToACC()` again on line 81. Double update to external accounting.
**Fix:** Either use `saveQuietly()` in `setPayment()`, or remove the explicit `updateRecordSalaryToACC()` call.

### 2.2 Inconsistent Abstain Count (Missing National Holiday Subtraction)
**File:** `app/Services/SalaryService.php`
**Problem:** `unpaidLeaveDeduction()` subtracts national holidays from workdays before calculating deduction, but `getAbstain()` does NOT subtract national holidays. Abstain count and abstain deduction use different bases.
**Fix:**
```php
public function getAbstain(SalaryRecap $salaryRecap, Salary $salary)
{
    $workDayInMonth = $this->workdayInAMonth($salaryRecap);
    $workDayInMonth -= $this->countOfNationalHoliday($salaryRecap);
    return max(0, $workDayInMonth - $salaryRecap->work_day);
}
```

### 2.3 Mass Assignment via `$request->all()`
**File:** `app/Http/Controllers/Admin/SalaryRecapCrudController.php` (lines 231, 240)
**Problem:** `store()` and `update()` pass `$request->all()` — anyone can inject `paid`, `received`, `acc_id`.
**Fix:** Use `$request->validated()` instead.

### 2.4 `env()` at Runtime
**File:** `app/Services/PresenceService.php` (lines 166–167)
**Problem:** `env('LAT')` / `env('LNG')` return `null` when config is cached.
**Fix:**
1. Add to `config/app.php`:
   ```php
   'office_lat' => env('LAT'),
   'office_lng' => env('LNG'),
   ```
2. Replace with `config('app.office_lat')` / `config('app.office_lng')`.

---

## Phase 3 — Relationship & Query Optimization ✅ DONE

### 3.1 Fix `hasOne` → `belongsTo` Relationships
**Files:** `app/Models/Presence.php`, `app/Models/SalaryRecap.php`
**Problem:** Both use `hasOne(User::class, 'id', 'user_id')` — semantically wrong. Works by accident but breaks `associate()` / `dissociate()` and causes confusing eager-load behavior.
**Fix:**
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

### 3.2 N+1 Queries in Salary Calculation
**File:** `app/Services/SalaryService.php`
**Problem:** `calculateSalaryRecap()` queries user 4 separate times:
- Line 77: `User::find($salaryRecap->user_id)`
- Line 76: `Salary::where('user_id', ...)->first()`
- `deductSalaryByLate()`: `User::with('salary')->find(...)`
- `calculateExtraTimeAmount()`: `User::with('salary')->find(...)`

**Fix:** Load once at the top, pass down:
```php
public function calculateSalaryRecap(SalaryRecap $salaryRecap)
{
    $user = User::with(['salary', 'schedule'])->find($salaryRecap->user_id);
    if (!$user || !$user->salary) {
        return $salaryRecap;
    }
    // ... pass $user and $user->salary to sub-methods
}
```

### 3.3 Shared `AccTransaction` Instance Mutation
**File:** `app/Services/TransactionService.php`
**Problem:** The same `$this->accTransaction` object is reused and mutated across method calls. If `recordSalaryToACC()` and `recordPayLoanACC()` run in the same request, stale fields leak between them.
**Fix:** Create a new `AccTransaction` per operation, or use a factory/clone.

---

## Phase 4 — Architecture & Code Quality ✅ DONE

### 4.1 Introduce Dependency Injection
**Problem:** `new SalaryService()`, `new PresenceService()`, `new Acc()` are instantiated manually everywhere — in observers, controllers, commands.
**Fix:** Register in `AppServiceProvider` and inject via constructor or method injection.

### 4.2 Make `RecalculateSalary` Command Generic
**File:** `app/Console/Commands/RecalculateSalary.php`
**Problem:** Hardcoded user IDs `['14', '12', '10', '8', '5']` and month `'04-2025'`.
**Fix:**
```php
protected $signature = 'salary:recalculate
    {--month= : Recap month in mm-YYYY format (default: current)}
    {--user=* : Specific user IDs (default: all)}';
```

### 4.3 Wrap Salary + ACC Operations in DB Transactions
**Problem:** Salary calculation, ACC sync, and loan payment creation are not atomic. If ACC API fails mid-way, local data is partially updated.
**Fix:** Wrap in `DB::transaction()` and handle ACC API errors with rollback.

### 4.4 Remove Dead Code
- `testSalaryRecap()` in `SalaryService` (line 127)
- `use function Symfony\Component\Translation\t;` in `SalaryRecapCrudController`
- Hardcoded `CompanyProfile::find(1)` — should be configurable

---

## Phase 5 — Minor Cleanup ✅ DONE

| Item | File | Fix |
|------|------|-----|
| `checkIfOffDay()` return type | `PresenceService:63` | Return `bool` instead of `int` from comparison |
| `CompanyProfile::find(1)` | `SalaryRecapCrudController::print()` | Make company_id configurable or use `first()` |
| `$salary` unused param in `getAbstain()` | `SalaryService:122` | Remove or use for validation |
| Inconsistent coding style | Multiple | PSR-12: spaces after commas, before braces |

---

## Priority Order

```
Phase 1  →  immediate (deploy blockers / data corruption)
Phase 2  →  this sprint (money calculation bugs)
Phase 3  →  next sprint (performance + correctness)
Phase 4  →  backlog (maintainability)
Phase 5  →  whenever touched (boy-scout rule)
```
