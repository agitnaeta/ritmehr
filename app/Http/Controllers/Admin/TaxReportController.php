<?php

namespace App\Http\Controllers\Admin;

use App\Models\SalaryRecap;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

class TaxReportController extends Controller
{
    public function __construct(private readonly TaxService $taxService)
    {
    }

    public function annual(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);

        return view('admin.tax.annual_report', [
            'year' => $year,
            'rows' => $this->taxService->generateAnnualTaxReport($year),
        ]);
    }

    public function bpjs(Request $request)
    {
        $month = $request->input('month') ?: now()->format('m-Y');

        return view('admin.tax.bpjs_report', [
            'month' => $month,
            'rows'  => $this->taxService->generateBpjsReport($month),
        ]);
    }

    /**
     * Recompute statutory figures for a whole month. Useful after changing
     * rates or a tax profile.
     */
    public function recalculate(Request $request)
    {
        $month = $request->input('month');

        if (! $month) {
            Alert::error('Bulan rekap wajib diisi.')->flash();

            return back();
        }

        $recaps = SalaryRecap::where('recap_month', $month)->get();

        foreach ($recaps as $recap) {
            $this->taxService->applyToRecap($recap);
        }

        Alert::success("Pajak & BPJS dihitung ulang untuk {$recaps->count()} rekap gaji ({$month}).")->flash();

        return back();
    }
}
