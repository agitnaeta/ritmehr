<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Notifications\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    /** @var array<int, array{phone: string, message: string}> */
    private array $sentWhatsApp = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Capture WhatsApp sends instead of hitting a provider.
        $spy = new class($this->sentWhatsApp) implements WhatsAppGateway {
            public array $sent = [];

            public function __construct(array &$ignored) {}

            public function send(string $phone, string $message): bool
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];

                return true;
            }
        };

        $this->app->instance(WhatsAppGateway::class, $spy);
        $this->app->forgetInstance(NotificationService::class);
        $this->service = new NotificationService($spy);
        $this->whatsappSpy = $spy;
    }

    private $whatsappSpy;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    public function test_notify_writes_a_database_row_with_rendered_copy(): void
    {
        $user = $this->user('Ahmad');

        $notification = $this->service->notify($user, Notification::LEAVE_APPROVED, [
            'leave_type' => 'Cuti Tahunan',
            'start_date' => '2026-09-01',
            'end_date'   => '2026-09-03',
        ]);

        $this->assertNotNull($notification);
        $this->assertSame('Pengajuan Cuti Disetujui', $notification->title);
        $this->assertStringContainsString('Cuti Tahunan', $notification->body);
        $this->assertStringContainsString('2026-09-01', $notification->body);
        $this->assertNull($notification->read_at);
        $this->assertNotNull($notification->sent_at);
    }

    public function test_database_row_is_written_even_when_only_other_channels_are_enabled(): void
    {
        $user = $this->user('Budi', ['phone' => '08123456789']);

        NotificationPreference::create([
            'user_id'          => $user->id,
            'type'             => Notification::LEAVE_APPROVED,
            'channel_database' => false,
            'channel_whatsapp' => true,
        ]);

        $this->service->notify($user, Notification::LEAVE_APPROVED, []);

        // The row is the audit trail — it exists regardless of preference.
        $this->assertDatabaseCount('notifications', 1);
        $this->assertCount(1, $this->whatsappSpy->sent);
    }

    public function test_whatsapp_is_skipped_when_not_opted_in(): void
    {
        $user = $this->user('Citra', ['phone' => '08123456789']);

        $this->service->notify($user, Notification::LEAVE_APPROVED, []);

        $this->assertCount(0, $this->whatsappSpy->sent, 'default preference is database only');
    }

    public function test_whatsapp_is_skipped_when_the_user_has_no_phone(): void
    {
        $user = $this->user('Dewi');  // no phone

        NotificationPreference::create([
            'user_id'          => $user->id,
            'type'             => Notification::LEAVE_APPROVED,
            'channel_whatsapp' => true,
        ]);

        $this->service->notify($user, Notification::LEAVE_APPROVED, []);

        $this->assertCount(0, $this->whatsappSpy->sent);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_email_channel_sends_when_opted_in(): void
    {
        Mail::fake();
        $user = $this->user('Eka');

        NotificationPreference::create([
            'user_id'       => $user->id,
            'type'          => Notification::SALARY_PAID,
            'channel_email' => true,
        ]);

        $this->service->notify($user, Notification::SALARY_PAID, [
            'period' => '2026-08',
            'amount' => 5_000_000,
        ]);

        Mail::assertSentCount(1);
    }

    public function test_notify_role_reaches_every_holder_and_skips_resigned_staff(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);

        $hr1 = $this->user('HR One');
        $hr2 = $this->user('HR Two');
        $hrGone = $this->user('HR Resigned', ['employment_status' => User::STATUS_RESIGNED]);
        $other = $this->user('Not HR');

        $hr1->assignRole($hrRole);
        $hr2->assignRole($hrRole);
        $hrGone->assignRole($hrRole);

        $reached = $this->service->notifyRole('hr_admin', Notification::LATE_ALERT, ['count' => 3]);

        $this->assertSame(2, $reached);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('notifications', ['user_id' => $hrGone->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $other->id]);
    }

    public function test_unread_count_and_mark_all_read(): void
    {
        $user = $this->user('Fajar');
        $other = $this->user('Other');

        $this->service->notify($user, Notification::LATE_ALERT, ['count' => 1]);
        $this->service->notify($user, Notification::LATE_ALERT, ['count' => 2]);
        $this->service->notify($other, Notification::LATE_ALERT, ['count' => 3]);

        $this->assertSame(2, $this->service->unreadCount($user));

        $this->service->markAllRead($user);

        $this->assertSame(0, $this->service->unreadCount($user));
        // Another user's notifications are untouched.
        $this->assertSame(1, $this->service->unreadCount($other));
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = $this->user('Gita');
        $notification = $this->service->notify($user, Notification::LATE_ALERT, ['count' => 1]);

        $this->service->markAsRead($notification);
        $firstReadAt = $notification->fresh()->read_at;

        $this->service->markAsRead($notification->fresh());

        $this->assertEquals($firstReadAt, $notification->fresh()->read_at);
    }

    public function test_unknown_type_degrades_instead_of_throwing(): void
    {
        $user = $this->user('Hadi');

        $notification = $this->service->notify($user, 'some_type_that_does_not_exist', [
            'title' => 'Custom',
            'body'  => 'Custom body',
        ]);

        $this->assertSame('Custom', $notification->title);
        $this->assertSame('Custom body', $notification->body);
    }

    public function test_deep_link_points_at_the_related_record(): void
    {
        $user = $this->user('Indra');

        $notification = $this->service->notify($user, Notification::APPROVAL_PENDING, [
            'approval_id' => 42,
        ]);

        $this->assertStringContainsString('/approval/42/detail', $notification->url());
    }

    public function test_notifications_are_deleted_with_their_user(): void
    {
        $user = $this->user('Joko');
        $this->service->notify($user, Notification::LATE_ALERT, ['count' => 1]);

        $user->delete();

        $this->assertDatabaseCount('notifications', 0);
    }
}
