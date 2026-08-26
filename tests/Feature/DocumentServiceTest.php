<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\Notification;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documents;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DocumentService::DISK);
        $this->documents = app(DocumentService::class);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function type(array $attrs = []): DocumentType
    {
        return DocumentType::create(array_merge([
            'name'               => 'KTP',
            'code'               => 'ktp',
            'has_expiry'         => false,
            'is_required'        => true,
            'max_file_size_mb'   => 5,
            'allowed_extensions' => 'pdf,jpg,png',
        ], $attrs));
    }

    // ── Storing ────────────────────────────────────────────

    public function test_document_is_stored_on_the_private_disk(): void
    {
        $user = $this->user('Staff');
        $hr = $this->user('HR');
        $type = $this->type();

        $doc = $this->documents->store(
            $user, $type,
            UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
            $hr,
            ['document_number' => '3201234567890001']
        );

        Storage::disk(DocumentService::DISK)->assertExists($doc->file_path);
        $this->assertSame('ktp.pdf', $doc->file_name);
        $this->assertSame('3201234567890001', $doc->document_number);
        $this->assertSame($hr->id, $doc->uploaded_by);
        // Must not be reachable from the public disk.
        $this->assertStringContainsString('employee-documents/' . $user->id, $doc->file_path);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['allowed_extensions' => 'pdf']);

        $this->expectException(\DomainException::class);
        $this->documents->store(
            $user, $type,
            UploadedFile::fake()->create('malware.exe', 10),
            $user
        );
    }

    public function test_oversized_file_is_rejected(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['max_file_size_mb' => 1]);

        $this->expectException(\DomainException::class);
        $this->documents->store(
            $user, $type,
            UploadedFile::fake()->create('big.pdf', 2048),  // 2 MB
            $user
        );
    }

    public function test_expiry_date_is_required_when_the_type_says_so(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['code' => 'kontrak', 'name' => 'Kontrak', 'has_expiry' => true]);

        $this->expectException(\DomainException::class);
        $this->documents->store(
            $user, $type,
            UploadedFile::fake()->create('kontrak.pdf', 50),
            $user
        );
    }

    public function test_deleting_a_document_removes_the_file_too(): void
    {
        $user = $this->user('Staff');
        $doc = $this->documents->store(
            $user, $this->type(),
            UploadedFile::fake()->create('ktp.pdf', 50),
            $user
        );
        $path = $doc->file_path;

        $this->documents->delete($doc);

        Storage::disk(DocumentService::DISK)->assertMissing($path);
        $this->assertDatabaseCount('employee_documents', 0);
    }

    public function test_storage_disk_follows_the_platform_setting(): void
    {
        // Default provider is local.
        $this->assertSame('local', app(\App\Services\StorageManager::class)->provider());

        // Switching provider via the M15 setting changes the active provider.
        $s = app(\App\Services\SettingService::class);
        $s->set('storage_provider', 's3');
        $s->flush();
        $this->assertSame('s3', app(\App\Services\StorageManager::class)->provider());

        // Unknown value falls back to local.
        $s->set('storage_provider', 'bogus');
        $s->flush();
        $this->assertSame('local', app(\App\Services\StorageManager::class)->provider());
    }

    public function test_document_is_stored_on_the_configured_disk(): void
    {
        // Local provider (default) → files land on the local disk.
        Storage::fake('local');

        $user = $this->user('Staff');
        $doc = $this->documents->store(
            $user, $this->type(),
            UploadedFile::fake()->create('ktp.pdf', 50),
            $user
        );

        $this->documents->disk()->assertExists($doc->file_path);
    }

    // ── Completeness ───────────────────────────────────────

    public function test_missing_required_documents_are_reported(): void
    {
        $user = $this->user('Staff');
        $ktp = $this->type(['code' => 'ktp', 'name' => 'KTP', 'is_required' => true]);
        $kontrak = $this->type(['code' => 'kontrak', 'name' => 'Kontrak', 'is_required' => true]);
        $this->type(['code' => 'ijazah', 'name' => 'Ijazah', 'is_required' => false]);

        $this->documents->store($user, $ktp, UploadedFile::fake()->create('ktp.pdf', 10), $user);

        $missing = $this->documents->missingRequiredFor($user);

        $this->assertCount(1, $missing);
        $this->assertSame('Kontrak', $missing->first()->name);
    }

    public function test_completeness_report_flags_incomplete_employees(): void
    {
        $complete = $this->user('Complete');
        $incomplete = $this->user('Incomplete');
        $ktp = $this->type(['code' => 'ktp', 'is_required' => true]);

        $this->documents->store($complete, $ktp, UploadedFile::fake()->create('ktp.pdf', 10), $complete);

        $report = $this->documents->completenessReport();

        $completeRow = $report->firstWhere(fn ($r) => $r['user']->id === $complete->id);
        $incompleteRow = $report->firstWhere(fn ($r) => $r['user']->id === $incomplete->id);

        $this->assertTrue($completeRow['complete']);
        $this->assertFalse($incompleteRow['complete']);
        $this->assertSame(1, $incompleteRow['missing']->count());
    }

    public function test_resigned_staff_are_excluded_from_the_completeness_report(): void
    {
        $this->user('Active');
        $this->user('Gone', ['employment_status' => User::STATUS_RESIGNED]);
        $this->type(['is_required' => true]);

        $report = $this->documents->completenessReport();

        $this->assertCount(1, $report);
        $this->assertSame('Active', $report->first()['user']->name);
    }

    // ── Expiry ─────────────────────────────────────────────

    public function test_expiry_helpers_classify_dates_correctly(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['has_expiry' => true]);

        $expired = $this->documents->store(
            $user, $type, UploadedFile::fake()->create('a.pdf', 10), $user,
            ['expiry_date' => now()->subDay()->toDateString()]
        );

        $soon = $this->documents->store(
            $user, $type, UploadedFile::fake()->create('b.pdf', 10), $user,
            ['expiry_date' => now()->addDays(10)->toDateString()]
        );

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($soon->isExpired());
        $this->assertSame(10, $soon->daysUntilExpiry());
    }

    public function test_expiring_scope_excludes_already_expired_documents(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['has_expiry' => true]);

        $this->documents->store($user, $type, UploadedFile::fake()->create('a.pdf', 10), $user,
            ['expiry_date' => now()->subDays(5)->toDateString()]);
        $this->documents->store($user, $type, UploadedFile::fake()->create('b.pdf', 10), $user,
            ['expiry_date' => now()->addDays(10)->toDateString()]);
        $this->documents->store($user, $type, UploadedFile::fake()->create('c.pdf', 10), $user,
            ['expiry_date' => now()->addDays(90)->toDateString()]);

        $this->assertCount(1, EmployeeDocument::expiringWithin(30)->get());
        $this->assertCount(1, EmployeeDocument::expired()->get());
    }

    public function test_expiry_alerts_reach_both_the_owner_and_hr(): void
    {
        Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $hr = $this->user('HR');
        $hr->assignRole('hr_admin');

        $staff = $this->user('Staff');
        $type = $this->type(['has_expiry' => true]);

        $this->documents->store($staff, $type, UploadedFile::fake()->create('kontrak.pdf', 10), $hr,
            ['expiry_date' => now()->addDays(14)->toDateString()]);

        $sent = $this->documents->notifyExpiring(30);

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id, 'type' => Notification::DOCUMENT_EXPIRING,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id, 'type' => Notification::DOCUMENT_EXPIRING,
        ]);
    }

    public function test_no_alerts_when_nothing_is_expiring(): void
    {
        $user = $this->user('Staff');
        $type = $this->type(['has_expiry' => true]);

        $this->documents->store($user, $type, UploadedFile::fake()->create('a.pdf', 10), $user,
            ['expiry_date' => now()->addYear()->toDateString()]);

        $this->assertSame(0, $this->documents->notifyExpiring(30));
        $this->assertDatabaseCount('notifications', 0);
    }
}
