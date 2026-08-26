<?php
// Shared browser-test helper for M18 recruitment UX tests.
// Usage: php tests/browser/_m18_helper.php <action> [args]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$a = array_slice($argv, 2);

switch ($action) {
    case 'seed_opening': // <stamp> [title-prefix] -> id|slug
        $prefix = $a[1] ?? 'M18 Role';
        $o = \App\Models\JobOpening::create([
            'title' => $prefix . ' ' . $a[0], 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        echo $o->id . '|' . $o->slug;
        break;

    case 'app_id': // <openingId>
        echo \App\Models\Applicant::where('job_opening_id', (int) $a[0])->value('id');
        break;

    case 'iv_count': // <applicantId>
        echo \App\Models\Interview::where('applicant_id', (int) $a[0])->count();
        break;

    case 'stage': // <applicantId>
        echo \App\Models\Applicant::find((int) $a[0])->stage;
        break;

    case 'stage_count_rejected': // <openingId>
        echo \App\Models\Applicant::where('job_opening_id', (int) $a[0])->where('stage', 'rejected')->count();
        break;

    case 'enrich_ai': // <applicantId>
        $ap = \App\Models\Applicant::find((int) $a[0]);
        $ap->ai_score = 91;
        $ap->ai_model = 'gpt-test';
        $ap->ai_reasoning = ['summary' => 'Sangat cocok', 'criteria' => [
            ['name' => 'Laravel', 'score' => 95, 'reason' => 'ahli', 'evidence' => '6 tahun'],
        ]];
        $ap->save();
        // Create a stage transition so the drawer timeline has content.
        app(\App\Services\RecruitmentService::class)->moveStage($ap, 'screening');
        echo 'ai';
        break;

    case 'timeline_count': // <applicantId>
        echo \App\Models\ApplicantStageLog::where('applicant_id', (int) $a[0])->count();
        break;

    case 'cleanup_hired': // <openingId> <stamp> — also removes provisioned users
        $ids = \App\Models\Applicant::where('job_opening_id', (int) $a[0])->pluck('id');
        $uids = \App\Models\Applicant::where('job_opening_id', (int) $a[0])->whereNotNull('hired_user_id')->pluck('hired_user_id');
        \App\Models\User::whereIn('id', $uids)->delete();
        \App\Models\Interview::whereIn('applicant_id', $ids)->delete();
        \App\Models\Applicant::where('job_opening_id', (int) $a[0])->delete();
        \App\Models\Candidate::where('email', 'like', '%' . $a[1] . '@ex.test')->delete();
        \App\Models\JobOpening::where('id', (int) $a[0])->delete();
        echo 'cleaned';
        break;

    case 'cleanup': // <openingId> <stamp>
        $ids = \App\Models\Applicant::where('job_opening_id', (int) $a[0])->pluck('id');
        \App\Models\Interview::whereIn('applicant_id', $ids)->delete();
        \App\Models\Applicant::where('job_opening_id', (int) $a[0])->delete();
        \App\Models\Candidate::where('email', 'like', '%' . $a[1] . '@ex.test')->delete();
        \App\Models\JobOpening::where('id', (int) $a[0])->delete();
        echo 'cleaned';
        break;

    default:
        echo 'unknown';
}
