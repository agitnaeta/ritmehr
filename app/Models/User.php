<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CrudTrait;
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use \App\Traits\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'schedule_id',
        'manager_id',
        'department_id',
        'branch_id',
        'position_id',
        'employee_id',
        'join_date',
        'employment_status',
        'phone',
        'address',
        'image',
    ];

    /**
     * Default attribute values.
     *
     * UM-05 — Bahasa (locale) default 'id' (Indonesia) untuk record baru via
     * kode/Eloquent, selaras dengan config('app.locale'). Kolom DB juga di-set
     * DEFAULT 'id' via migration 2026_08_29_100001.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'id',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PROBATION = 'probation';
    public const STATUS_RESIGNED = 'resigned';
    public const STATUS_TERMINATED = 'terminated';

    /**
     * Statuses that still count as "on the payroll".
     */
    public const EMPLOYED_STATUSES = [self::STATUS_ACTIVE, self::STATUS_PROBATION];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'join_date' => 'date',
    ];


    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function presence(){
        return $this->hasMany(Presence::class,'user_id','id');
    }

    public function salary(){
        return $this->hasOne(Salary::class,'user_id','id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Departments this user heads. Combined with manager_id this is how the
     * approval engine works out who can sign off on what.
     */
    public function headedDepartments()
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function taxProfile()
    {
        return $this->hasOne(EmployeeTaxProfile::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Our own notification records. Deliberately not named `notifications()`
     * — that name belongs to Laravel's Notifiable trait.
     */
    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    /**
     * Only staff still on the payroll. Resigned/terminated people keep their
     * historical records but must be excluded from payroll and headcount.
     */
    public function scopeEmployed($query)
    {
        return $query->whereIn('employment_status', self::EMPLOYED_STATUSES);
    }

    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeInBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Batasi ke karyawan yang boleh dilihat $viewer.
     *
     * Pemegang `user.view_all` (super_admin, hr_admin) melihat semuanya.
     * Selebihnya — manager — hanya melihat bawahan langsungnya plus dirinya
     * sendiri. "Tim" di sini berarti `manager_id`, bukan sub-pohon departemen.
     *
     * Definisinya sengaja ditaruh di satu tempat: daftar karyawan, daftar
     * presensi, dan angka dashboard semuanya harus menyempit dengan cara yang
     * sama, kalau tidak salah satunya akan bocor.
     */
    public function scopeVisibleTo($query, ?self $viewer)
    {
        if (! $viewer || $viewer->can('user.view_all')) {
            return $query;
        }

        return $query->where(function ($q) use ($viewer) {
            $q->where('manager_id', $viewer->id)->orWhere('id', $viewer->id);
        });
    }

    // ── Helpers ────────────────────────────────────────────

    public function isEmployed(): bool
    {
        return in_array($this->employment_status, self::EMPLOYED_STATUSES, true);
    }

    /**
     * Months of service as of $asOf, used for prorated THR and leave quota.
     */
    public function monthsOfService(?\Carbon\Carbon $asOf = null): int
    {
        if (! $this->join_date) {
            return 0;
        }

        $asOf ??= now();

        return max(0, $this->join_date->copy()->startOfDay()->diffInMonths($asOf));
    }

    public function employmentStatusLabel(): string
    {
        return match ($this->employment_status) {
            self::STATUS_ACTIVE     => 'Aktif',
            self::STATUS_PROBATION  => 'Masa Percobaan',
            self::STATUS_RESIGNED   => 'Resign',
            self::STATUS_TERMINATED => 'Diberhentikan',
            default                 => (string) $this->employment_status,
        };
    }
}

