<?php

namespace App\Http\Controllers\Admin;

use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * WIZ-02 — Setup Wizard onboarding (4 langkah).
 *
 * Orkestrasi step company → orgunit → admin → import. Logika bisnis di
 * OnboardingService (WIZ-03). View di resources/views/admin/setup/* (WIZ-04).
 */
class SetupWizardController extends Controller
{
    private const STEPS = ['company', 'orgunit', 'admin', 'import'];

    public function __construct(private readonly OnboardingService $svc)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(backpack_user()?->can('user.create'), 403,
                'Anda tidak berhak mengakses penyiapan awal.');

            return $next($request);
        });
    }

    public function index()
    {
        return redirect()->route('setup.step', ['step' => 'company']);
    }

    public function step(string $step)
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        return view("admin.setup.$step", array_merge(
            $this->svc->context($step),
            ['step' => $step, 'steps' => self::STEPS, 'stepIndex' => array_search($step, self::STEPS, true)]
        ));
    }

    public function save(Request $request, string $step)
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        // Step import: proses file Excel (reuse UserImport) bila ada.
        if ($step === 'import' && $request->hasFile('file')) {
            $request->validate(['file' => 'file|mimes:xlsx,xls,csv|max:2048']);
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\UserImport, $request->file('file'));
        } else {
            $this->svc->save($step, $request->all());
        }

        $next = $this->svc->nextStep($step);

        return $next
            ? redirect()->route('setup.step', ['step' => $next])
            : redirect()->route('setup.finish');
    }

    public function finish()
    {
        $this->svc->markComplete();

        return redirect(backpack_url('dashboard'))
            ->with('success', 'Penyiapan awal selesai. Selamat datang di RitmeHR!');
    }
}
