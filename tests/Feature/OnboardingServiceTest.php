<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * WIZ-03 — OnboardingService: tiap step merge langsung ke DB + flag selesai.
 */
class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(OnboardingService::class);
    }

    public function test_company_step_upserts_profile(): void
    {
        $this->svc->save('company', ['name' => 'PT Uji', 'address' => 'Jakarta', 'phone' => '021']);

        $this->assertDatabaseHas('company_profiles', ['name' => 'PT Uji', 'address' => 'Jakarta']);
        $this->assertSame(1, CompanyProfile::count());

        // save lagi → update, bukan duplikat
        $this->svc->save('company', ['name' => 'PT Uji Baru']);
        $this->assertSame(1, CompanyProfile::count());
        $this->assertDatabaseHas('company_profiles', ['name' => 'PT Uji Baru']);
    }

    public function test_company_step_requires_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->save('company', ['name' => '']);
    }

    public function test_orgunit_step_creates_departments_and_branches(): void
    {
        CompanyProfile::create(['name' => 'PT Uji']);

        $this->svc->save('orgunit', [
            'departments' => ['Teknologi', 'HRD', ''],
            'branches'    => ['Kantor Pusat'],
        ]);

        $this->assertDatabaseHas('departments', ['name' => 'Teknologi']);
        $this->assertDatabaseHas('departments', ['name' => 'HRD']);
        $this->assertDatabaseHas('branches', ['name' => 'Kantor Pusat']);
        $this->assertSame(2, Department::count()); // baris kosong diabaikan
    }

    public function test_orgunit_step_requires_at_least_one_each(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->save('orgunit', ['departments' => [], 'branches' => []]);
    }

    public function test_complete_flag_toggles(): void
    {
        $this->assertFalse($this->svc->isComplete());
        $this->svc->markComplete();
        $this->assertTrue($this->svc->isComplete());
    }

    public function test_next_step_sequence(): void
    {
        $this->assertSame('orgunit', $this->svc->nextStep('company'));
        $this->assertSame('admin', $this->svc->nextStep('orgunit'));
        $this->assertSame('import', $this->svc->nextStep('admin'));
        $this->assertNull($this->svc->nextStep('import'));
    }
}
