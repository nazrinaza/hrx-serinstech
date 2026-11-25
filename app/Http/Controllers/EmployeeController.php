<?php

namespace App\Http\Controllers;

use App\Leave\LeaveService;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePayItem;
use App\Models\EmployeeSalary;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\PayItem;
use App\Models\Position;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'active');
        $employees = Employee::with('department', 'position', 'manager')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', '%'.$request->q.'%')
                ->orWhere('employee_no', 'like', '%'.$request->q.'%')
                ->orWhere('ic_no', 'like', '%'.$request->q.'%')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'resigned', fn ($q) => $q->whereDate('resign_date', '<', today()))
            ->orderBy('full_name')
            ->paginate(25)->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('employees.form', $this->formData(new Employee([
            'join_date' => today(), 'citizenship' => 'citizen', 'employment_type' => 'permanent',
            'marital_status' => 'single', 'is_tax_resident' => true, 'spouse_working' => true,
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $salary = $request->validate([
            'basic_salary' => 'required|numeric|min:0|max:9999999',
        ])['basic_salary'];

        $employee = DB::transaction(function () use ($data, $salary) {
            $employee = Employee::create($data);
            $employee->salaries()->create([
                'effective_from' => $employee->join_date, 'basic_salary' => $salary, 'reason' => 'New hire',
            ]);

            return $employee;
        });

        return redirect()->route('employees.show', $employee)->with('status', 'Employee added.');
    }

    public function show(Request $request, Employee $employee, LeaveService $leave)
    {
        $employee->load('department', 'position', 'manager', 'user', 'salaries', 'payItems.payItem', 'ytdOpenings');
        $year = (int) $request->input('year', now()->year);

        $balances = LeaveType::where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($t) => $t->appliesTo($employee))
            ->map(fn ($t) => ['type' => $t, 'entitlement' => $leave->entitlement($employee, $t, $year)] + $leave->balance($employee, $t, $year));

        return view('employees.show', [
            'employee' => $employee,
            'tab' => $request->input('tab', 'personal'),
            'year' => $year,
            'balances' => $balances,
            'leaveHistory' => $employee->leaveRequests()->with('leaveType')->latest('start_date')->limit(20)->get(),
            'payItems' => PayItem::orderBy('name')->get(),
            'payslips' => $employee->payslips()->with('run')->latest('id')->limit(12)->get(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.show', $employee)->with('status', 'Employee updated.');
    }

    public function storeSalary(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date', Rule::unique('employee_salaries')->where('employee_id', $employee->id)],
            'basic_salary' => 'required|numeric|min:0|max:9999999',
            'reason' => 'nullable|string|max:255',
        ], ['effective_from.unique' => 'There is already a salary change on that date.']);

        $employee->salaries()->create($data);

        return redirect()->route('employees.show', [$employee, 'tab' => 'pay'])->with('status', 'Salary change recorded.');
    }

    public function storePayItem(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'pay_item_id' => ['required', Rule::exists('pay_items', 'id')->where('company_id', $employee->company_id)],
            'amount' => 'required|numeric|min:0|max:9999999',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $employee->payItems()->create($data);

        return redirect()->route('employees.show', [$employee, 'tab' => 'pay'])->with('status', 'Recurring item added.');
    }

    public function endPayItem(Request $request, Employee $employee, EmployeePayItem $item)
    {
        abort_unless($item->employee_id === $employee->id, 404);
        $date = $request->validate(['effective_to' => 'required|date|after_or_equal:'.$item->effective_from->toDateString()])['effective_to'];
        $item->update(['effective_to' => $date]);

        return redirect()->route('employees.show', [$employee, 'tab' => 'pay'])->with('status', 'Recurring item ended.');
    }

    public function adjustEntitlement(Request $request, Employee $employee, LeaveEntitlement $entitlement)
    {
        abort_unless($entitlement->employee_id === $employee->id, 404);
        $entitlement->update($request->validate([
            'days' => 'required|numeric|min:0|max:366',
            'carried_forward' => 'required|numeric|min:0|max:366',
            'adjustment' => 'required|numeric|min:-366|max:366',
        ]));

        return redirect()->route('employees.show', [$employee, 'tab' => 'leave', 'year' => $entitlement->year])
            ->with('status', 'Leave entitlement updated.');
    }

    public function storeYtdOpening(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'remuneration' => 'required|numeric|min:0',
            'epf' => 'required|numeric|min:0',
            'pcb' => 'required|numeric|min:0',
        ]);
        $employee->ytdOpenings()->updateOrCreate(['year' => $data['year']], $data);

        return redirect()->route('employees.show', [$employee, 'tab' => 'pay'])->with('status', 'Opening balance saved. Recalculate any draft payroll to apply it.');
    }

    /** Give the employee a login (employee role) so they can use self service. */
    public function createLogin(Employee $employee, CurrentCompany $current)
    {
        if ($employee->user_id) {
            return back()->withErrors(['email' => 'This employee already has a login.']);
        }
        if (! $employee->email) {
            return back()->withErrors(['email' => 'Add an email address to the employee first.']);
        }

        $company = $current->get();
        $user = User::firstWhere('email', $employee->email);
        $isNew = ! $user;
        $user ??= User::create([
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => Str::password(32),
        ]);
        if (! $company->users()->whereKey($user->id)->exists()) {
            $company->users()->attach($user->id, ['role' => 'employee']);
        }
        $employee->update(['user_id' => $user->id]);

        if ($isNew) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', $isNew
            ? "Login created and a set-password link was emailed to {$user->email}."
            : "Linked to the existing account {$user->email}.");
    }

    // ---- CSV import -------------------------------------------------------

    private const IMPORT_COLUMNS = [
        'employee_no', 'full_name', 'ic_no', 'passport_no', 'citizenship', 'gender', 'date_of_birth',
        'marital_status', 'email', 'phone', 'department', 'position', 'employment_type', 'join_date',
        'basic_salary', 'bank_name', 'bank_account_no', 'epf_no', 'socso_no', 'tax_no', 'children_count',
        'spouse_working',
    ];

    public function importForm()
    {
        return view('employees.import', ['columns' => self::IMPORT_COLUMNS]);
    }

    public function importTemplate()
    {
        $example = ['E001', 'Ahmad bin Ali', '900101-14-5678', '', 'citizen', 'male', '1990-01-01', 'married',
            'ahmad@example.com', '0123456789', 'Operations', 'Technician', 'permanent', '2026-01-15', '3500',
            'Maybank', '1234567890', '12345678', '', 'SG1234567890', '2', 'yes'];

        return response()->streamDownload(function () use ($example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::IMPORT_COLUMNS);
            fputcsv($out, $example);
            fclose($out);
        }, 'employee-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, CurrentCompany $current)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        if (array_diff(['employee_no', 'full_name', 'join_date', 'basic_salary'], $header)) {
            return back()->withErrors(['file' => 'The file must have at least these columns: employee_no, full_name, join_date, basic_salary. Download the template for the full list.']);
        }

        $rows = [];
        $errors = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
            $row = array_map(fn ($v) => $v === null || trim($v) === '' ? null : trim($v), $row);

            $v = Validator::make($row, [
                'employee_no' => ['required', 'max:30', Rule::unique('employees')->where('company_id', $current->id())],
                'full_name' => 'required|max:255',
                'join_date' => 'required|date',
                'basic_salary' => 'required|numeric|min:0',
                'date_of_birth' => 'nullable|date',
                'email' => 'nullable|email',
                'citizenship' => 'nullable|in:citizen,pr,foreigner',
                'gender' => 'nullable|in:male,female',
                'marital_status' => 'nullable|in:single,married,divorced,widowed',
                'employment_type' => 'nullable|in:permanent,contract,probation,intern',
                'children_count' => 'nullable|integer|min:0|max:30',
            ]);
            if ($v->fails()) {
                $errors[] = "Row {$line}: ".implode(' ', $v->errors()->all());
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        $dupes = collect($rows)->pluck('employee_no')->duplicates();
        if ($dupes->isNotEmpty()) {
            $errors[] = 'Duplicate employee_no in file: '.$dupes->unique()->implode(', ');
        }
        if ($errors) {
            return back()->withErrors(array_slice($errors, 0, 20))->with('status', 'Nothing was imported. Fix the rows below and upload again.');
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                $dept = $r['department'] ?? null ? Department::firstOrCreate(['name' => $r['department']]) : null;
                $pos = $r['position'] ?? null ? Position::firstOrCreate(['name' => $r['position']]) : null;
                $employee = Employee::create([
                    'employee_no' => $r['employee_no'],
                    'full_name' => $r['full_name'],
                    'ic_no' => $r['ic_no'] ?? null,
                    'passport_no' => $r['passport_no'] ?? null,
                    'citizenship' => $r['citizenship'] ?? 'citizen',
                    'gender' => $r['gender'] ?? null,
                    'date_of_birth' => $r['date_of_birth'] ?? null,
                    'marital_status' => $r['marital_status'] ?? 'single',
                    'email' => $r['email'] ?? null,
                    'phone' => $r['phone'] ?? null,
                    'department_id' => $dept?->id,
                    'position_id' => $pos?->id,
                    'employment_type' => $r['employment_type'] ?? 'permanent',
                    'join_date' => $r['join_date'],
                    'bank_name' => $r['bank_name'] ?? null,
                    'bank_account_no' => $r['bank_account_no'] ?? null,
                    'epf_no' => $r['epf_no'] ?? null,
                    'socso_no' => $r['socso_no'] ?? null,
                    'tax_no' => $r['tax_no'] ?? null,
                    'children_count' => (int) ($r['children_count'] ?? 0),
                    'spouse_working' => ! in_array(strtolower((string) ($r['spouse_working'] ?? 'yes')), ['no', 'n', '0', 'false'], true),
                ]);
                $employee->salaries()->create([
                    'effective_from' => $employee->join_date, 'basic_salary' => $r['basic_salary'], 'reason' => 'Imported',
                ]);
            }
        });

        return redirect()->route('employees.index')->with('status', count($rows).' employee(s) imported.');
    }

    // ---- helpers ----------------------------------------------------------

    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'managers' => Employee::active()->when($employee->exists, fn ($q) => $q->whereKeyNot($employee->id))->orderBy('full_name')->get(),
        ];
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        foreach (['is_tax_resident', 'spouse_working', 'is_disabled', 'spouse_disabled'] as $flag) {
            $request->merge([$flag => $request->boolean($flag)]);
        }

        return $request->validate([
            'employee_no' => ['required', 'string', 'max:30', Rule::unique('employees')->where('company_id', $companyId)->ignore($employee?->id)],
            'full_name' => 'required|string|max:255',
            'ic_no' => ['nullable', 'string', 'max:20', 'regex:/^\d{6}-?\d{2}-?\d{4}$/'],
            'passport_no' => 'nullable|string|max:30',
            'citizenship' => 'required|in:citizen,pr,foreigner',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
            'department_id' => ['nullable', $inCompany('departments')],
            'position_id' => ['nullable', $inCompany('positions')],
            'manager_id' => ['nullable', $inCompany('employees'), Rule::notIn(array_filter([$employee?->id]))],
            'employment_type' => 'required|in:permanent,contract,probation,intern',
            'join_date' => 'required|date',
            'resign_date' => 'nullable|date|after_or_equal:join_date',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:40',
            'epf_no' => 'nullable|string|max:30',
            'socso_no' => 'nullable|string|max:30',
            'tax_no' => 'nullable|string|max:30',
            'epf_employee_rate' => 'nullable|numeric|min:0|max:100',
            'is_tax_resident' => 'boolean',
            'spouse_working' => 'boolean',
            'children_count' => 'required|integer|min:0|max:30',
            'is_disabled' => 'boolean',
            'spouse_disabled' => 'boolean',
        ], ['ic_no.regex' => 'IC number must look like 900101-14-5678.']);
    }
}
