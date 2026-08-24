<?php

namespace App\Http\Controllers\Portal;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Notification;
use App\Models\Presence;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Employee self-service portal (/my/*).
 *
 * Every query here is scoped to the authenticated user. There is no route in
 * this controller that accepts a user id from the request.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService,
        private readonly NotificationService $notifications,
    ) {
    }

    private function me(): User
    {
        return backpack_user();
    }

    // ── Dashboard ──────────────────────────────────────────

    public function dashboard()
    {
        $user = $this->me();
        $monthStart = now()->startOfMonth();

        $presences = Presence::where('user_id', $user->id)
            ->whereYear('in', $monthStart->year)
            ->whereMonth('in', $monthStart->month)
            ->get();

        $latestSalary = SalaryRecap::where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return view('portal.dashboard', [
            'user'           => $user,
            'presentDays'    => $presences->count(),
            'lateDays'       => $presences->where('is_late', true)->count(),
            'overtimeDays'   => $presences->where('is_overtime', true)->count(),
            'latestSalary'   => $latestSalary,
            'balances'       => $this->currentBalances($user),
            'pendingLeave'   => LeaveRequest::where('user_id', $user->id)->pending()->count(),
            'outstandingLoan' => $this->outstandingLoan($user),
            'unreadCount'    => $this->notifications->unreadCount($user),
        ]);
    }

    // ── Attendance ─────────────────────────────────────────

    public function attendance(Request $request)
    {
        $user = $this->me();
        $month = $request->input('month')
            ? Carbon::parse($request->input('month') . '-01')
            : now()->startOfMonth();

        $presences = Presence::where('user_id', $user->id)
            ->whereYear('in', $month->year)
            ->whereMonth('in', $month->month)
            ->orderBy('in')
            ->get();

        return view('portal.attendance', [
            'month'     => $month,
            'presences' => $presences,
            'summary'   => [
                'present'  => $presences->count(),
                'late'     => $presences->where('is_late', true)->count(),
                'overtime' => $presences->where('is_overtime', true)->count(),
                'outside'  => $presences->where('outside', true)->count(),
            ],
        ]);
    }

    // ── Salary ─────────────────────────────────────────────

    public function salaryIndex()
    {
        return view('portal.salary_index', [
            'recaps' => SalaryRecap::where('user_id', $this->me()->id)
                ->orderByDesc('id')
                ->paginate(24),
        ]);
    }

    public function salaryShow(int $id)
    {
        $recap = $this->ownedSalaryRecap($id);

        return view('portal.salary_show', [
            'recap' => $recap,
            'user'  => $this->me(),
        ]);
    }

    /**
     * Scoping by user_id in the query (not after fetching) means an id
     * belonging to someone else simply 404s.
     */
    private function ownedSalaryRecap(int $id): SalaryRecap
    {
        return SalaryRecap::where('id', $id)
            ->where('user_id', $this->me()->id)
            ->firstOrFail();
    }

    // ── Leave ──────────────────────────────────────────────

    public function leaveIndex()
    {
        $user = $this->me();

        return view('portal.leave_index', [
            'requests' => LeaveRequest::with(['leaveType', 'approval'])
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->paginate(20),
            'balances' => $this->currentBalances($user),
        ]);
    }

    public function leaveCreate()
    {
        return view('portal.leave_create', [
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
            'balances'   => $this->currentBalances($this->me()),
        ]);
    }

    public function leaveStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date',
            'reason'        => 'nullable|string|max:1000',
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'start_date.required'    => 'Tanggal mulai wajib diisi.',
            'end_date.required'      => 'Tanggal selesai wajib diisi.',
            'attachment.max'         => 'Ukuran lampiran maksimal 5 MB.',
        ]);

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('leave-attachments', 'public')
            : null;

        try {
            $this->leaveService->requestLeave(
                $this->me(),
                LeaveType::findOrFail($data['leave_type_id']),
                $data['start_date'],
                $data['end_date'],
                $data['reason'] ?? null,
                $path
            );
        } catch (\DomainException | \RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.leave.index')
            ->with('success', 'Pengajuan cuti terkirim dan menunggu persetujuan.');
    }

    public function leaveCancel(int $id): RedirectResponse
    {
        $leaveRequest = LeaveRequest::where('id', $id)
            ->where('user_id', $this->me()->id)
            ->firstOrFail();

        try {
            $this->leaveService->cancel($leaveRequest, $this->me());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    // ── Loans ──────────────────────────────────────────────

    public function loanIndex()
    {
        $user = $this->me();

        return view('portal.loan_index', [
            'loans'       => Loan::where('user_id', $user->id)->orderByDesc('id')->get(),
            'payments'    => LoanPayment::where('user_id', $user->id)->orderByDesc('id')->get(),
            'outstanding' => $this->outstandingLoan($user),
        ]);
    }

    // ── Profile ────────────────────────────────────────────

    public function profile()
    {
        return view('portal.profile', ['user' => $this->me()->load(['department', 'position', 'manager'])]);
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        $user = $this->me();

        $data = $request->validate([
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'email'   => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.unique'   => 'Email sudah dipakai akun lain.',
        ]);

        // Employees may only edit contact details — name, salary, department
        // and employment status stay with HR.
        $update = [
            'phone'   => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'email'   => $data['email'],
        ];

        if ($request->hasFile('image')) {
            $update['image'] = str_replace(
                'public/',
                '',
                $request->file('image')->store('uploads', 'public')
            );
        }

        $user->update($update);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.min'              => 'Password baru minimal 8 karakter.',
            'password.confirmed'        => 'Konfirmasi password tidak cocok.',
        ]);

        $user = $this->me();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return back()->with('error', 'Password saat ini salah.');
        }

        $user->update(['password' => $request->input('password')]);

        return back()->with('success', 'Password berhasil diubah.');
    }

    // ── Notifications ──────────────────────────────────────

    public function notifications()
    {
        return view('portal.notifications', [
            'notifications' => Notification::forUser($this->me()->id)
                ->latest()
                ->paginate(25),
        ]);
    }

    public function notificationRead(int $id): RedirectResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $this->me()->id)
            ->firstOrFail();

        $this->notifications->markAsRead($notification);

        return back();
    }

    public function notificationsMarkAllRead(): RedirectResponse
    {
        $this->notifications->markAllRead($this->me());

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    // ── Shared helpers ─────────────────────────────────────

    private function currentBalances(User $user)
    {
        return LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->where('year', (int) now()->year)
            ->get()
            ->filter(fn (LeaveBalance $b) => $b->leaveType !== null);
    }

    /**
     * Total borrowed minus total repaid.
     */
    private function outstandingLoan(User $user): int
    {
        $borrowed = (int) Loan::where('user_id', $user->id)->sum('amount');
        $repaid = (int) LoanPayment::where('user_id', $user->id)->sum('amount');

        return max(0, $borrowed - $repaid);
    }
}
