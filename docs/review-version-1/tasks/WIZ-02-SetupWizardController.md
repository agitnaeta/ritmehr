# WIZ-02 — SetupWizardController (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Http/Controllers/Admin/SetupWizardController.php` (BARU)
**Bagian dari:** Setup Wizard (menutup RV1-001, Lensa 2)
**Depends:** WIZ-03 (OnboardingService), WIZ-04 (views)

## Tanggung jawab
Orkestrasi wizard 4 langkah: **company → orgunit → admin → import**. Tidak berisi logika bisnis berat (delegasikan ke `OnboardingService`).

## Kerangka
```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    private array $steps = ['company','orgunit','admin','import'];

    public function __construct(private readonly OnboardingService $svc) {}

    public function index()                 { return redirect()->route('setup.step', ['step'=>'company']); }
    public function step(string $step)      { abort_unless(in_array($step,$this->steps),404);
                                              return view("admin.setup.$step", $this->svc->context($step)); }
    public function save(Request $r, string $step) {
        $this->svc->save($step, $r->all());          // validasi + merge ke DB langsung
        $next = $this->svc->nextStep($step);
        return $next ? redirect()->route('setup.step',['step'=>$next])
                     : redirect()->route('setup.finish');
    }
    public function finish()                { $this->svc->markComplete();
                                              return redirect(backpack_url('dashboard'))
                                                  ->with('success','Setup selesai.'); }
}
```
Guard akses: hanya user dgn permission `user.create` / super_admin (samakan pola `abort_unless(backpack_user()->can(...))`).

## Verifikasi
1. `/admin/setup` redirect ke step `company`.
2. Submit tiap step maju ke step berikut; step terakhir → dashboard.
3. `phpunit` hijau; tambahkan Feature test alur wizard end-to-end.
