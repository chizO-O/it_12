<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use App\Notifications\InquiryActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Agent, UserRole::UnitManager], true), 403);

        $query = Inquiry::query()->with(['buyer:id,name', 'agent:id,name', 'lot.property']);
        if ($user->role === UserRole::Buyer) {
            $query->where('buyer_id', $user->id);
        } elseif ($user->role === UserRole::Agent) {
            $query->where('agent_id', $user->id);
        } elseif ($user->role === UserRole::UnitManager) {
            $query->whereNull('agent_id');
        }

        $inquiries = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15);
        $unread = [];
        if ($user->role !== UserRole::UnitManager) {
            $unread = DB::table('messages')->selectRaw('inquiry_id, COUNT(*) AS total')
                ->whereIn('inquiry_id', $inquiries->getCollection()->pluck('id'))
                ->where('sender_id', '!=', $user->id)->whereNull('read_at')
                ->groupBy('inquiry_id')->pluck('total', 'inquiry_id')->all();
        }

        return view('inquiries.index', [
            'inquiries' => $inquiries,
            'unread' => $unread,
            'notifications' => $user->unreadNotifications()->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Property $property, Lot $lot): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::Buyer && $lot->property_id === $property->id, 403);
        $input = $request->validate(['message' => ['required', 'string', 'min:1', 'max:2000']]);

        [$inquiry, $created] = DB::transaction(function () use ($request, $property, $lot, $input) {
            $currentLot = Lot::query()->lockForUpdate()->findOrFail($lot->id);
            $existing = Inquiry::query()->where('buyer_id', $request->user()->id)->where('lot_id', $lot->id)->first();
            if ($existing) {
                return [$existing, false];
            }
            // Availability is checked in the database transaction, not based on a stale SVG/map view.
            if ($currentLot->status !== LotStatus::Available) {
                abort(409, 'This lot is no longer available for new inquiries.');
            }
            $agent = $currentLot->assignedAgent;
            if ($agent && ($agent->role !== UserRole::Agent || !$agent->hasVerifiedEmail())) {
                $agent = null;
            }
            $id = DB::table('inquiries')->insertOrIgnore([
                'buyer_id' => $request->user()->id,
                'lot_id' => $currentLot->id,
                'agent_id' => $agent?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $inquiry = Inquiry::query()->where('buyer_id', $request->user()->id)->where('lot_id', $lot->id)->firstOrFail();
            if ($id === 0) {
                return [$inquiry, false];
            }
            $inquiry->messages()->create(['sender_id' => $request->user()->id, 'body' => trim($input['message'])]);
            return [$inquiry, true];
        });

        if ($created) {
            if ($inquiry->agent_id) {
                $inquiry->agent?->notify(new InquiryActivity($inquiry->id, 'New lot inquiry assigned to you.'));
            } else {
                Notification::send(User::where('role', UserRole::UnitManager->value)->whereNotNull('email_verified_at')->get(), new InquiryActivity($inquiry->id, 'A buyer inquiry is waiting for an Agent to be assigned to its lot.'));
            }
        }

        return redirect()->route('inquiries.show', $inquiry)
            ->with('status', $created ? 'Your inquiry has been created.' : 'You already have a conversation about this lot.');
    }

    public function show(Request $request, Inquiry $inquiry): View
    {
        $this->authorizeParticipant($request->user(), $inquiry);
        $inquiry->load(['lot.property', 'buyer:id,name', 'agent:id,name']);
        $messages = $inquiry->messages()->with('sender:id,name')->latest('id')->paginate(50);
        $inquiry->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        $activeReservation = $inquiry->reservations()
            ->whereIn('status', ['pending', 'approved'])->latest('id')->first();
        $latestReservation = $activeReservation ?? $inquiry->reservations()->latest('id')->first();

        return view('inquiries.show', [
            'inquiry' => $inquiry,
            'messages' => $messages,
            'activeReservation' => $activeReservation,
            'latestReservation' => $latestReservation,
        ]);
    }

    public function message(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $input = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $recipient = DB::transaction(function () use ($request, $inquiry, $input) {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
            $this->authorizeParticipant($request->user(), $locked);
            $locked->messages()->create(['sender_id' => $request->user()->id, 'body' => trim($input['body'])]);
            $locked->touch();
            return $request->user()->id === $locked->buyer_id ? $locked->agent : $locked->buyer;
        });

        $recipient?->notify(new InquiryActivity($inquiry->id, 'New message about a property inquiry.'));
        return redirect()->route('inquiries.show', $inquiry)->with('status', 'Message sent.');
    }

    public function updates(Request $request, Inquiry $inquiry): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $inquiry);
        $input = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $messages = $inquiry->messages()->with('sender:id,name')->where('id', '>', $input['after'] ?? 0)
            ->oldest('id')->limit(100)->get();
        $inquiry->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['messages' => $messages->map(fn ($message) => [
            'id' => $message->id,
            'body' => $message->body,
            'sender' => $message->sender?->name ?? 'User',
            'mine' => $message->sender_id === $request->user()->id,
            'sent_at' => $message->created_at->format('M j, g:i A'),
        ])]);
    }

    private function authorizeParticipant(User $user, Inquiry $inquiry): void
    {
        abort_unless(
            ($user->role === UserRole::Buyer && $inquiry->buyer_id === $user->id)
            || ($user->role === UserRole::Agent && $inquiry->agent_id === $user->id),
            403
        );
    }
}
