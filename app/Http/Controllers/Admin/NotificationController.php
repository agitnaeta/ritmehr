<?php

namespace App\Http\Controllers\Admin;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $user = backpack_user();

        $query = Notification::forUser($user->id)->latest();

        if ($request->boolean('unread')) {
            $query->unread();
        }

        return view('admin.notification.index', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'unreadOnly'    => $request->boolean('unread'),
            'unreadCount'   => $this->notifications->unreadCount($user),
        ]);
    }

    /**
     * Mark read and forward to whatever the notification is about.
     */
    public function read(int $id)
    {
        $notification = $this->ownedOrFail($id);
        $this->notifications->markAsRead($notification);

        return redirect($notification->url() ?? url(config('backpack.base.route_prefix') . '/notification'));
    }

    public function markAllRead()
    {
        $this->notifications->markAllRead(backpack_user());

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->unreadCount(backpack_user()),
        ]);
    }

    private function ownedOrFail(int $id): Notification
    {
        $notification = Notification::findOrFail($id);

        abort_unless(
            (int) $notification->user_id === (int) backpack_user()->id,
            403,
            'Notifikasi ini bukan milik Anda.'
        );

        return $notification;
    }
}
