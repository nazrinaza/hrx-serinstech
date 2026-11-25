{{-- Params: $action, $types, optional $employees (HR form) --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="card card-body space-y-3" x-data="{ start: '{{ old('start_date') }}', end: '{{ old('end_date') }}' }">
    @csrf
    <h2>{{ isset($employees) ? 'Apply leave for an employee' : 'Request leave' }}</h2>
    @isset($employees)
        <div><label class="label">Employee</label>
            <select name="employee_id" class="input" required><option value="">Choose…</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach</select></div>
    @endisset
    <div><label class="label">Leave type</label>
        <select name="leave_type_id" class="input" required>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('leave_type_id') == $t->id)>{{ $t->name }}{{ $t->requires_attachment ? ' (document needed)' : '' }}</option>@endforeach</select></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="label">From</label><input type="date" name="start_date" class="input" x-model="start" @change="if (!end || end < start) end = start" required></div>
        <div><label class="label">To</label><input type="date" name="end_date" class="input" x-model="end" :min="start" required></div>
    </div>
    <div x-show="start && start === end"><label class="label">Half day?</label>
        <select name="half_day" class="input"><option value="">Full day</option><option value="am">Morning (AM)</option><option value="pm">Afternoon (PM)</option></select></div>
    <div><label class="label">Reason</label><textarea name="reason" rows="2" class="input">{{ old('reason') }}</textarea></div>
    <div><label class="label">Supporting document</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="text-sm"><p class="help">PDF or image, up to 4 MB (e.g. medical certificate).</p></div>
    <button class="btn-primary">Submit</button>
</form>
