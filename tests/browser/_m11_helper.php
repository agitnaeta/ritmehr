<?php
// Shared browser-test helper for M11 training tests.
// Usage: php tests/browser/_m11_helper.php <action> [args]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$a = array_slice($argv, 2);

switch ($action) {
    case 'training_id': // <titleLike> -> id of latest matching training
        echo \App\Models\Training::where('title', 'like', '%' . $a[0] . '%')
            ->orderByDesc('id')->value('id');
        break;

    case 'enrollment_id': // <trainingId> <userEmailLike>
        $uid = \App\Models\User::where('email', 'like', '%' . $a[1] . '%')->value('id');
        echo \App\Models\TrainingEnrollment::where('training_id', (int) $a[0])
            ->where('user_id', $uid)->value('id');
        break;

    case 'employee_id': // <emailLike> -> user id (a non-admin employee to enroll)
        echo \App\Models\User::where('email', 'like', '%' . $a[0] . '%')->value('id');
        break;

    case 'status': // <trainingId> <userEmailLike> -> enrollment status
        $uid = \App\Models\User::where('email', 'like', '%' . $a[1] . '%')->value('id');
        echo \App\Models\TrainingEnrollment::where('training_id', (int) $a[0])
            ->where('user_id', $uid)->value('status');
        break;

    case 'cleanup': // <titleLike>
        $ids = \App\Models\Training::where('title', 'like', '%' . $a[0] . '%')->pluck('id');
        \App\Models\TrainingEnrollment::whereIn('training_id', $ids)->delete();
        \App\Models\TrainingQuestion::whereIn('training_id', $ids)->delete();
        \App\Models\TrainingMaterial::whereIn('training_id', $ids)->delete();
        \App\Models\Training::whereIn('id', $ids)->delete();
        echo 'cleaned';
        break;

    default:
        echo 'unknown';
}
