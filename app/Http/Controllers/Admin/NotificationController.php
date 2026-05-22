<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $adminUser = $request->user('admin');

        return view('admin.notifications.index', [
            'notifications' => $adminUser->notifications()->latest()->paginate(20),
            'unreadCount' => $adminUser->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user('admin')->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Notifiche segnate come lette.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $stored = $request->user('admin')->notifications()->whereKey($notification)->firstOrFail();
        $stored->markAsRead();

        return redirect($stored->data['url'] ?? route('admin.notifications.index'));
    }
}
