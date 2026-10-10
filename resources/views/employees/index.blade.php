<x-workspace title="Employee accounts">
    <div>
        <p class="eyebrow">Office · Account administration</p><h1 class="mt-3 text-3xl font-semibold text-emerald-950">Employee accounts</h1>
        <p class="mt-3 text-sm text-stone-600">Invite your team and assign the role appropriate to their work.</p>
        <div class="mt-7"><x-feedback /></div>
        <div class="grid items-start gap-7 xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <section class="rounded-2xl border border-stone-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-emerald-950">Create employee account</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">The employee will receive an email link to set their own password, then verify their email after signing in.</p>
                <form method="POST" action="{{ route('employees.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <x-form-field name="name" label="Full name" autocomplete="name" maxlength="255" />
                    <x-form-field name="email" label="Employee email" type="email" autocomplete="email" maxlength="255" />
                    <div><label for="role" class="mb-2 block text-sm font-medium">Role</label><select id="role" name="role" class="form-input" required>
                        @foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role', 'agent') === $role->value)>{{ $role->label() }}</option>@endforeach
                    </select>@error('role')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <button class="btn-primary w-full">Create & send setup link</button>
                </form>
            </section>
            <section class="min-w-0 overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <div class="border-b border-stone-200 p-6"><h2 class="text-lg font-semibold text-emerald-950">Your team</h2><p class="mt-1 text-sm text-stone-500">{{ $employees->total() }} employee {{ Str::plural('account', $employees->total()) }}</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Employee accounts, assigned roles, and account status</caption>
                        <thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500"><tr><th scope="col" class="px-6 py-4">Employee</th><th scope="col" class="px-4 py-4">Role</th><th scope="col" class="px-4 py-4">Status</th><th scope="col" class="px-4 py-4">Action</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse($employees as $employee)
                                <tr>
                                    <td class="px-6 py-5"><p class="font-medium">{{ $employee->name }}</p><p class="mt-1 text-xs text-stone-500">{{ $employee->email }}</p></td>
                                    <td class="px-4 py-5">
                                        @if($employee->is(auth()->user()))
                                            {{ $employee->role->label() }}
                                            <span class="mt-1 block text-xs text-stone-500">Your account</span>
                                        @else
                                            <form method="POST" action="{{ route('employees.role', $employee) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label for="employee-role-{{ $employee->id }}" class="sr-only">Role for {{ $employee->name }}</label>
                                                <select id="employee-role-{{ $employee->id }}" name="role" class="form-input min-w-40" required>
                                                    @foreach($roles as $role)
                                                        <option value="{{ $role->value }}" @selected($employee->role === $role)>{{ $role->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="text-link text-xs">Save role</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="px-4 py-5 text-xs">{{ $employee->password === null ? 'Setup pending' : ($employee->hasVerifiedEmail() ? 'Active' : 'Verification pending') }}</td>
                                    <td class="px-4 py-5">
                                        @if($employee->password === null)
                                            <form method="POST" action="{{ route('employees.invite', $employee) }}">@csrf<button class="text-link text-xs">Resend invitation</button></form>
                                        @else
                                            <span class="text-stone-400" aria-label="No action needed">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-stone-500">No employee accounts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($employees->hasPages())<div class="border-t border-stone-200 p-6">{{ $employees->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-workspace>
