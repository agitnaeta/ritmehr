<?php

namespace App\Http\Controllers\Admin;

use App\Services\Notifications\WahaAdminService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * M03b — In-app WhatsApp connection (scan QR + status + logout) that proxies to
 * the WAHA container server-side, so the admin never opens the WAHA dashboard
 * and WAHA credentials never reach the browser.
 */
class WahaController extends Controller
{
    private function guard(): void
    {
        $user = backpack_user();
        abort_unless($user && $user->hasRole('super_admin'), 403, 'Hanya super admin yang dapat mengelola koneksi WhatsApp.');
    }

    public function index()
    {
        $this->guard();

        return view('admin.whatsapp.index', [
            'configured' => WahaAdminService::fromSettings() !== null,
            'enabled'    => (bool) setting('whatsapp_enabled', true),
        ]);
    }

    public function status()
    {
        $this->guard();

        $svc = WahaAdminService::fromSettings();
        if (! $svc) {
            return response()->json([
                'reachable' => false,
                'state'     => 'NOT_CONFIGURED',
                'connected' => false,
                'me'        => null,
                'error'     => 'WAHA belum dikonfigurasi. Atur dulu di Pengaturan → Notifikasi.',
            ]);
        }

        return response()->json($svc->status());
    }

    public function start()
    {
        $this->guard();

        $svc = WahaAdminService::fromSettings();
        if (! $svc) {
            return response()->json(['ok' => false, 'error' => 'WAHA belum dikonfigurasi.'], 422);
        }

        return response()->json(['ok' => $svc->start()]);
    }

    public function qr()
    {
        $this->guard();

        $svc = WahaAdminService::fromSettings();
        $qr = $svc?->qr();

        if (! $qr) {
            abort(404, 'QR tidak tersedia.');
        }

        return response($qr['body'], 200)
            ->header('Content-Type', $qr['contentType'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function logout()
    {
        $this->guard();

        $svc = WahaAdminService::fromSettings();
        if (! $svc) {
            return response()->json(['ok' => false, 'error' => 'WAHA belum dikonfigurasi.'], 422);
        }

        return response()->json(['ok' => $svc->logout()]);
    }
}
