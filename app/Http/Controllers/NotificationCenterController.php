<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        $unreadCount = $request->user()->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        // Always query through the authenticated user's relation, never globally.
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $data = $item->data;

        $target = match (true) {
            isset($data['inquiry_id']) && $request->user()->role === UserRole::UnitManager => route('assignments.index'),
            isset($data['inquiry_id']) => route('inquiries.show', $data['inquiry_id']),
            isset($data['reservation_id']) => route('reservations.show', $data['reservation_id']),
            isset($data['cts_record_id']) => route('cts.show', $data['cts_record_id']),
            isset($data['transaction_id']) && $request->user()->role === UserRole::Agent => route('reservations.index'),
            isset($data['transaction_id']) && $request->user()->role === UserRole::UnitManager => route('reports.index'),
            isset($data['transaction_id']) && $request->user()->role === UserRole::Admin => route('reports.index'),
            isset($data['transaction_id']) => route('sales.show', $data['transaction_id']),
            default => route('notifications.index'),
        };

        // Target routes independently enforce their own role/ownership policies.
        return redirect()->to($target);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return to_route('notifications.index')->with('status', 'Notifications marked as read.');
    }
}
