<?php
// M22 browser-test helper — set attendance settings, seed a demo presence,
// and read the last presence. Bootstraps the framework so it can run via
// `php tests/browser/_m22_helper.php <cmd> [args]`, avoiding the
// double-escaping trap of `tinker --execute` from Node.

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'set-mode':
        // args: mode lat lng radius
        $svc = app(App\Services\SettingService::class);
        $svc->set('attendance_mode', $argv[2] ?? 'camera');
        $svc->set('camera_require_selfie', '1');
        $svc->set('office_lat', $argv[3] ?? '-6.2012');
        $svc->set('office_lng', $argv[4] ?? '106.8169');
        $svc->set('office_radius', $argv[5] ?? '100');
        echo 'ok';
        break;

    case 'last-presence':
        // args: email
        $p = App\Models\Presence::whereHas('user', fn ($q) => $q->where('email', $argv[2] ?? ''))
            ->latest('id')->first();
        if (! $p) { echo 'none'; break; }
        echo $p->source . '|' . $p->approval_status . '|' . ($p->selfie_path ? 'selfie' : 'nofoto');
        break;

    case 'seed-camera-presence':
        // args: email  — creates an approved camera presence with a selfie file.
        $u = App\Models\User::where('email', $argv[2] ?? '')->first();
        if (! $u) { echo 'no-user'; break; }
        // Write a real valid JPEG so the Show page has a proper image.
        $rel = 'presences/selfie/demo_' . $u->id . '.jpg';
        $img = imagecreatetruecolor(360, 480);
        imagefill($img, 0, 0, imagecolorallocate($img, 37, 99, 235));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, 180, 180, 150, 150, $white);
        imagestring($img, 5, 120, 300, 'SELFIE BUKTI', $white);
        ob_start();
        imagejpeg($img, null, 85);
        $binary = ob_get_clean();
        imagedestroy($img);
        Illuminate\Support\Facades\Storage::disk('local')->put($rel, $binary);
        $p = new App\Models\Presence();
        $p->user_id = $u->id;
        $p->in = now()->format('Y-m-d H:i:s');
        $p->source = 'camera';
        $p->lat = (string) ($u->branch->lat ?? '-6.1753924');
        $p->lng = (string) ($u->branch->lng ?? '106.8271528');
        $p->accuracy = 8;
        $p->outside = 0;
        $p->approval_status = 'approved';
        $p->branch_id = $u->branch_id;
        $p->selfie_path = $rel;
        $p->save();
        echo 'presence_id=' . $p->id;
        break;

    case 'seed-pending-presence':
        // args: email — pending out-of-radius camera presence for approval screen.
        $u = App\Models\User::where('email', $argv[2] ?? '')->first();
        if (! $u) { echo 'no-user'; break; }
        $rel = 'presences/selfie/pending_' . $u->id . '.jpg';
        $img = imagecreatetruecolor(360, 480);
        imagefill($img, 0, 0, imagecolorallocate($img, 220, 38, 38));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, 180, 180, 150, 150, $white);
        imagestring($img, 5, 110, 300, 'LUAR RADIUS', $white);
        ob_start(); imagejpeg($img, null, 85); $binary = ob_get_clean(); imagedestroy($img);
        Illuminate\Support\Facades\Storage::disk('local')->put($rel, $binary);
        $p = new App\Models\Presence();
        $p->user_id = $u->id;
        $p->in = now()->format('Y-m-d H:i:s');
        $p->source = 'camera';
        $p->lat = '-6.3012'; $p->lng = '106.9169';
        $p->accuracy = 15; $p->outside = 1;
        $p->approval_status = 'pending';
        $p->approval_note = 'Absen di luar radius kantor — menunggu persetujuan manajer.';
        $p->branch_id = $u->branch_id;
        $p->selfie_path = $rel;
        $p->save();
        echo 'pending_id=' . $p->id;
        break;

    default:
        echo 'unknown';
}
