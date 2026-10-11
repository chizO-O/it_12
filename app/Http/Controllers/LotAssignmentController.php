<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use App\Notifications\InquiryActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LotAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('assignments.index', [
            'lots' => Lot::with(['property:id,name,location', 'assignedAgent:id,name,unit_manager_id'])
                ->withCount(['inquiries as awaiting_assignment_count' => fn ($query) => $query->whereNull('agent_id')])
                ->where('status', LotStatus::Available->value)
                ->orderBy('property_id')->orderBy('lot_no')->paginate(20),
            'agents' => User::where('role', UserRole::Agent->value)
                ->where('unit_manager_id', $request->user()->id)
                ->whereNotNull('email_verified_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Lot $lot): RedirectResponse
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);
        $manager = $request->user();
        $agent = isset($data['agent_id']) ? User::findOrFail($data['agent_id']) : null;
        if ($agent) {
            abort_unless($agent->role === UserRole::Agent
                && $agent->unit_manager_id === $manager->id
                && $agent->hasVerifiedEmail(), 403);
        }
        $notified = DB::transaction(function () use ($manager, $lot, $agent) {
            // Same lock order as inventory and reservation operations.
            Property::whereKey($lot->property_id)->lockForUpdate()->firstOrFail();
            $locked = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== LotStatus::Available) {
                throw ValidationException::withMessages(['agent_id' => 'Only Available lots may be reassigned.']);
            }
            if ($locked->assigned_agent_id) {
                $previous = User::find($locked->assigned_agent_id);
                abort_unless($previous?->unit_manager_id === $manager->id, 403);
            }
            if (Inquiry::where('lot_id', $locked->id)->whereNotNull('agent_id')
                ->when($agent, fn ($query) => $query->where('agent_id', '!=', $agent->id))->exists()
                || (!$agent && Inquiry::where('lot_id', $locked->id)->whereNotNull('agent_id')->exists())) {
                throw ValidationException::withMessages(['agent_id' => 'An existing conversation belongs to another Agent. Do not transfer private conversations automatically.']);
            }
            $locked->assigned_agent_id = $agent?->id;
            $locked->assigned_by_user_id = $agent ? $manager->id : null;
            $locked->agent_assigned_at = $agent ? now() : null;
            $locked->save();

            $pending = Inquiry::where('lot_id', $locked->id)->whereNull('agent_id')->lockForUpdate()->get();
            if ($agent) {
                foreach ($pending as $inquiry) {
                    $inquiry->agent_id = $agent->id;
                    $inquiry->save();
                }
            }
            return $pending->all();
        }, 3);
        foreach ($notified as $inquiry) {
            $agent?->notify(new InquiryActivity($inquiry->id, 'An inquiry about your assigned lot is waiting.'));
            $inquiry->buyer?->notify(new InquiryActivity($inquiry->id, 'An Agent has been assigned to your lot inquiry.'));
        }
        return to_route('assignments.index')->with('status', $agent ? 'Agent assigned to Available lot.' : 'Lot assignment cleared.');
    }
}
