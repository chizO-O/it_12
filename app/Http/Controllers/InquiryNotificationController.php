<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InquiryNotificationController extends Controller
{
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('inquiries.index')->with('status', 'Notifications marked as read.');
    }
}
