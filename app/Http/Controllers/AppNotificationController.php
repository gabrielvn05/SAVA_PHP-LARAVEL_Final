<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function __construct(private readonly AppNotificationService $notifications) {}

    public function open(AppNotification $appNotification): RedirectResponse
    {
        abort_unless($appNotification->user_id === auth()->id(), 403);

        $appNotification->markAsRead();

        $target = $appNotification->action_url ?: route('dashboard');

        return redirect()->to($target);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->marcarTodasLeidas($request->user());

        return back();
    }
}
