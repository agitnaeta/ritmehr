# WIZ-02 — SetupWizardController (baru)

**Status:** [x] DONE — commit: `(uncommitted)` · flow company→...→dashboard terverifikasi via UI asli
**File:** `app/Http/Controllers/Admin/SetupWizardController.php` (BARU)
**Referensi desain:** [`../mockup/setup-wizard.html`](../mockup/setup-wizard.html)
**Bagian dari:** Setup Wizard (RV1-001) · **Depends:** WIZ-03 (service), WIZ-04 (views)

## Tanggung jawab
Orkestrasi 4 langkah `company → orgunit → admin → import` (urutan sesuai stepper mockup). Logika bisnis didelegasikan ke `OnboardingService` (WIZ-03). Controller hanya routing antar step + guard akses.

## Kerangka
```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    private const STEPS = ['company','orgunit','admin','import'];

    public function __construct(private readonly OnboardingService $svc)
    {
        $this->middleware(function ($req, $next) {
            abort_unless(backpack_user()?->can('user.create'), 403); // hanya admin/HR
            return $next($req);
        });
    }

    public function index() { return redirect()->route('setup.step', ['step' => 'company']); }

    public function step(string $step)
    {
        $i = array_search($step, self::STEPS, true);
        return view("admin.setup.$step", array_merge(
            $this->svc->context($step),
            ['step' => $step, 'steps' => self::STEPS, 'stepIndex' => $i]
        ));
    }

    public function save(Request $r, string $step)
    {
        $this->svc->save($step, $r->all());                 // validasi + merge ke DB
        $next = self::STEPS[array_search($step, self::STEPS, true) + 1] ?? null;
        return $next ? redirect()->route('setup.step', ['step' => $next])
                     : redirect()->route('setup.finish');
    }

    public function finish()
    {
        $this->svc->markComplete();
        return redirect(backpack_url('dashboard'))->with('success', 'Penyiapan selesai. Selamat datang!');
    }
}
```

## Cek per file (verifikasi)
- [ ] Non-admin (employee) buka `/admin/setup` → 403.
- [ ] `save('company', ...)` valid → redirect ke `setup/orgunit`.
- [ ] `save('import', ...)` → redirect `setup.finish` → dashboard, flash sukses.
- [ ] Feature test alur wizard end-to-end hijau.
