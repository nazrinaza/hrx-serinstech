@php
    $field = fn ($label, $value) => ['label' => $label, 'value' => $value];
    $sections = [
        'Personal' => [
            $field('Full name', $employee->full_name),
            $field('IC no.', $employee->ic_no),
            $field('Passport no.', $employee->passport_no),
            $field('Citizenship', ['citizen' => 'Malaysian', 'pr' => 'Permanent resident', 'foreigner' => 'Foreigner'][$employee->citizenship] ?? $employee->citizenship),
            $field('Gender', ucfirst((string) $employee->gender)),
            $field('Date of birth', $employee->date_of_birth?->format('d M Y')),
            $field('Marital status', ucfirst($employee->marital_status)),
            $field('Email', $employee->email),
            $field('Phone', $employee->phone),
            $field('Address', $employee->address),
        ],
        'Job' => [
            $field('Employee no.', $employee->employee_no),
            $field('Department', $employee->department?->name),
            $field('Position', $employee->position?->name),
            $field('Line manager', $employee->manager?->full_name),
            $field('Employment type', ucfirst($employee->employment_type)),
            $field('Joined', $employee->join_date->format('d M Y')),
            $field('Last working day', $employee->resign_date?->format('d M Y')),
        ],
        'Bank & statutory' => [
            $field('Bank', $employee->bank_name),
            $field('Account no.', $employee->bank_account_no),
            $field('EPF no.', $employee->epf_no),
            $field('SOCSO no.', $employee->socso_no),
            $field('Income tax no.', $employee->tax_no),
            $field('Tax resident', $employee->is_tax_resident ? 'Yes' : 'No'),
            $field('Spouse has income', $employee->marital_status === 'married' ? ($employee->spouse_working ? 'Yes' : 'No') : null),
            $field('Children (tax relief)', $employee->children_count),
        ],
    ];
@endphp
<div class="grid gap-6 lg:grid-cols-3">
    @foreach ($sections as $title => $fields)
        <div class="card card-body">
            <h2 class="mb-3">{{ $title }}</h2>
            <dl class="space-y-2 text-sm">
                @foreach ($fields as $f)
                    <div class="grid grid-cols-5 gap-2">
                        <dt class="col-span-2 text-ink/60">{{ $f['label'] }}</dt>
                        <dd class="col-span-3 break-words">{{ filled($f['value']) ? $f['value'] : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</div>
