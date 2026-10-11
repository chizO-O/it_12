<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class AssignInquiryAgent
{
    public function forArea(string $area): ?User
    {
        return User::query()
            ->where('role', UserRole::Agent->value)
            ->where('service_area', $area)
            ->whereNotNull('email_verified_at')
            ->withCount('assignedInquiries')
            ->orderBy('assigned_inquiries_count')
            ->orderBy('id')
            ->first();
    }
}
