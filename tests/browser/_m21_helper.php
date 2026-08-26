<?php
// Shared browser-test helper for M21 recruitment ranking view.
// Usage: php tests/browser/_m21_helper.php <action> [args]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$a = array_slice($argv, 2);

switch ($action) {
    case 'seed_opening': // <stamp> -> id
        $o = \App\Models\JobOpening::create([
            'title' => 'M21 QA Engineer ' . $a[0], 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        echo $o->id;
        break;

    case 'seed_scored': // <openingId> <name> <ai_score|null> <vector_score|null> -> applicantId
        $ai = ($a[2] === 'null' || $a[2] === '') ? null : (float) $a[2];
        $vec = ($a[3] === 'null' || $a[3] === '') ? null : (float) $a[3];
        $ap = \App\Models\Applicant::create([
            'job_opening_id' => (int) $a[0],
            'name'           => $a[1],
            'email'          => strtolower($a[1]) . uniqid() . '@ex.test',
            'stage'          => 'applied',
            'ai_score'       => $ai,
            'vector_score'   => $vec,
            'ai_reasoning'   => $ai !== null ? [
                'summary'  => 'Ringkasan AI untuk ' . $a[1],
                'criteria' => [
                    ['name' => 'Automation Testing', 'score' => 95, 'reason' => 'Kuat di Cypress', 'evidence' => '5 tahun QA'],
                    ['name' => 'Kepemimpinan', 'score' => 70, 'reason' => 'Pernah lead tim', 'evidence' => 'Lead 3 org'],
                ],
            ] : null,
            'ai_model'       => $ai !== null ? 'gpt-test' : null,
            'ai_scored_at'   => $ai !== null ? now() : null,
        ]);
        echo $ap->id;
        break;

    case 'cleanup': // <openingId> <stamp>
        $ids = \App\Models\Applicant::where('job_opening_id', (int) $a[0])->pluck('id');
        \App\Models\Interview::whereIn('applicant_id', $ids)->delete();
        \App\Models\ApplicantStageLog::whereIn('applicant_id', $ids)->delete();
        \App\Models\Applicant::where('job_opening_id', (int) $a[0])->delete();
        \App\Models\JobOpening::where('id', (int) $a[0])->delete();
        echo 'cleaned';
        break;

    default:
        echo 'unknown';
}
