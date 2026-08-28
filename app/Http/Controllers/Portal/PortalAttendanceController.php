<?php

namespace App\Http\Controllers\Portal;

use App\Models\Presence;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PresenceService;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * M22 — Self-Attendance (Camera Location Mode).
 *
 * Lets an employee clock in/out from their own phone with a selfie + live
 * geolocation, validated against the same branch-aware geofence the QR flow
 * uses. The identity is ALWAYS the authenticated session user — this controller
 * never accepts a user id from the request, so nobody can clock in as someone
 * else.
 *
 * When the scan lands outside the allowed radius the presence is still recorded
 * but flagged `pending` for a manager to approve (Q3), rather than blocked.
 */
class PortalAttendanceController extends Controller
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly StorageManager $storage,
        private readonly NotificationService $notifications,
    ) {
    }

    private function me(): User
    {
        return backpack_user();
    }

    /** Camera Mode must be the active attendance mode to reach this flow. */
    private function ensureCameraMode(): void
    {
        if (setting('attendance_mode', 'qr') !== 'camera') {
            abort(redirect()
                ->to(route('portal.dashboard'))
                ->with('error', 'Mode absensi mandiri (kamera) sedang tidak aktif. Hubungi admin.'));
        }
    }

    // ── Check-in page ──────────────────────────────────────

    public function create()
    {
        $this->ensureCameraMode();

        $user   = $this->me();
        $branch = $user->branch;

        // Reference point + radius for the live map — branch first, else global.
        if ($branch && $branch->hasGeofence()) {
            $center = ['lat' => (float) $branch->lat, 'lng' => (float) $branch->lng, 'radius' => max(1, (int) $branch->radius_meters), 'label' => $branch->name];
        } else {
            $center = [
                'lat'    => (float) setting('office_lat', config('app.office_lat')),
                'lng'    => (float) setting('office_lng', config('app.office_lng')),
                'radius' => max(1, (int) setting('office_radius', config('app.office_radius', 100))),
                'label'  => setting('app_name', config('app.name')),
            ];
        }

        // Has the employee already clocked in today? (drives the button label)
        $today = Presence::where('user_id', $user->id)
            ->whereDate('created_at', now(PresenceService::TIME_ZONE)->toDateString())
            ->first();

        return view('portal.attendance_checkin', [
            'user'          => $user,
            'center'        => $center,
            'requireSelfie' => (bool) setting('camera_require_selfie', true),
            'nextAction'    => ($today && ! $today->out && $today->in) ? 'out' : 'in',
        ]);
    }

    // ── Store (POST) ───────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->ensureCameraMode();

        $data = $request->validate([
            'lat'      => ['required', 'numeric', 'between:-90,90'],
            'lng'      => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'selfie'   => ['nullable', 'string'], // data URL (image/jpeg;base64,...)
        ]);

        $user = $this->me(); // identity from session — never from the request body

        $requireSelfie = (bool) setting('camera_require_selfie', true);
        if ($requireSelfie && ! $request->filled('selfie')) {
            return response()->json(['message' => 'Foto selfie wajib diambil.'], 422);
        }

        // Record (login/logout auto-decided) using the SAME service as QR.
        $presence = $this->presence->record($user);

        // Persist the selfie proof via the pluggable storage backend (M16).
        if ($request->filled('selfie')) {
            if ($path = $this->storeSelfie($data['selfie'], $user)) {
                $presence->selfie_path = $path;
            }
        }

        // Geofence — reuse the existing branch-aware calculation.
        $this->presence->updateCoordinate($presence, $data['lat'], $data['lng']);

        $presence->source   = 'camera';
        $presence->accuracy = $data['accuracy'] ?? null;

        // Q3 — outside the radius: keep the record but require manager approval.
        if ($presence->outside) {
            $presence->approval_status = 'pending';
            $presence->approval_note   = 'Absen di luar radius kantor — menunggu persetujuan manajer.';
        } else {
            $presence->approval_status = 'approved';
        }

        $presence->saveQuietly();

        if ($presence->outside) {
            $this->notifyManagers($user, $presence);
        }

        $isOut = (bool) $presence->out;

        return response()->json([
            'ok'       => true,
            'message'  => $isOut ? 'Absen keluar tercatat.' : 'Absen masuk tercatat.',
            'outside'  => (bool) $presence->outside,
            'status'   => $presence->approval_status,
            'time'     => $isOut ? $presence->out : $presence->in,
            'selfie'   => $presence->selfieUrl(),
        ]);
    }

    /**
     * Decode a base64 data URL and store it as a JPEG. Returns the stored path
     * or null when the payload is not a usable image.
     */
    private function storeSelfie(string $dataUrl, User $user): ?string
    {
        if (! preg_match('/^data:image\/(jpe?g|png);base64,/', $dataUrl)) {
            return null;
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode(strtr($base64, ' ', '+'), true);

        if ($binary === false || strlen($binary) < 100) {
            return null;
        }

        $path = 'presences/selfie/' . $user->id . '_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.jpg';
        $this->storage->disk()->put($path, $binary);

        return $path;
    }

    /** Notify managers/HR that an out-of-radius scan needs review. */
    private function notifyManagers(User $user, Presence $presence): void
    {
        $payload = [
            'title'     => 'Absensi menunggu persetujuan',
            'body'      => $user->name . ' absen di luar radius kantor pada ' .
                \Carbon\Carbon::parse($presence->in)->format('d M Y H:i') . '. Perlu ditinjau.',
            'user_name' => $user->name,
        ];

        // Guard-agnostic role lookup (whereHas on roles.name), same as other
        // fan-outs in the app — no coupling to the permission guard.
        $this->notifications->notifyRole('manager', 'attendance_pending_approval', $payload);
        $this->notifications->notifyRole('hr_admin', 'attendance_pending_approval', $payload);
    }

    // ── Selfie proof stream ────────────────────────────────

    /**
     * Stream a selfie from the private disk after an access check. Selfies are
     * face photos (personal data), so they are never served as static files:
     * the owner can see their own, and anyone with presence.view can see any.
     */
    public function selfie(\App\Models\Presence $presence)
    {
        $me = backpack_user();
        $owns = $me && (int) $presence->user_id === (int) $me->id;
        $canView = $me && $me->can('presence.view');

        abort_unless($owns || $canView, 403);
        abort_unless($presence->selfie_path, 404, 'Tidak ada foto.');

        $disk = $this->storage->disk();
        abort_unless($disk->exists($presence->selfie_path), 404, 'Foto tidak ditemukan.');

        return $disk->response($presence->selfie_path);
    }
}
