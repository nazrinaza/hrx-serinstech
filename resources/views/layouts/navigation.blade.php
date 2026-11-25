@php
    $role = $currentRole ?? null;
    $isHr = in_array($role, \App\Models\User::HR_ROLES, true);
    $isPayroll = in_array($role, \App\Models\User::PAYROLL_ROLES, true);
    $isAdmin = in_array($role, \App\Models\User::ADMIN_ROLES, true);
    $item = fn ($route, $pattern = null) => 'nav-item'.(request()->routeIs($pattern ?? $route) ? ' active' : '');
@endphp
<nav class="p-3 space-y-0.5 overflow-y-auto h-[calc(100vh-4rem)]">
    <a href="{{ route('dashboard') }}" class="{{ $item('dashboard') }}">Dashboard</a>
    <a href="{{ route('memos.feed') }}" class="{{ $item('memos.feed', 'memos.feed') }}">Company memos</a>

    <div class="nav-section">My self service</div>
    <a href="{{ route('me.profile') }}" class="{{ $item('me.profile') }}">My profile</a>
    <a href="{{ route('me.leave') }}" class="{{ $item('me.leave') }}">My leave</a>
    <a href="{{ route('me.payslips') }}" class="{{ $item('me.payslips') }}">My payslips</a>
    <a href="{{ route('approvals.index') }}" class="{{ $item('approvals.*') }}">Approvals</a>

    @if ($isHr)
        <div class="nav-section">HR</div>
        <a href="{{ route('employees.index') }}" class="{{ $item('employees.index', 'employees.*') }}">Employees</a>
        <a href="{{ route('leave.index') }}" class="{{ $item('leave.index', 'leave.index') }}">Leave requests</a>
        <a href="{{ route('leave.balances') }}" class="{{ $item('leave.balances') }}">Leave balances</a>
        <a href="{{ route('memos.index') }}" class="{{ $item('memos.index', 'memos.index') }}">Manage memos</a>
    @endif

    @if ($isPayroll)
        <div class="nav-section">Payroll</div>
        <a href="{{ route('payroll.index') }}" class="{{ $item('payroll.index', 'payroll.*') }}">Payroll runs</a>
    @endif

    @if ($isHr)
        <div class="nav-section">Setup</div>
        @foreach (\App\Http\Controllers\SetupController::KINDS as $kind => $def)
            <a href="{{ route('setup.index', $kind) }}" class="{{ 'nav-item'.(request()->route('kind') === $kind ? ' active' : '') }}">{{ $def['title'] }}</a>
        @endforeach
    @endif

    @if ($isAdmin)
        <a href="{{ route('company.edit') }}" class="{{ $item('company.edit') }}">Company settings</a>
        <a href="{{ route('users.index') }}" class="{{ $item('users.*') }}">Users &amp; roles</a>
    @endif
    @if (auth()->user()->is_super_admin)
        <a href="{{ route('companies.index') }}" class="{{ $item('companies.*') }}">All companies</a>
    @endif
</nav>
