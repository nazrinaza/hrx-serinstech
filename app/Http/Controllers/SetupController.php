<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\PayItem;
use App\Models\Position;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One controller for the simple company look-up lists.
 * Each kind declares its model, columns and validation rules.
 */
class SetupController extends Controller
{
    public const KINDS = [
        'departments' => ['title' => 'Departments', 'model' => Department::class],
        'positions' => ['title' => 'Positions', 'model' => Position::class],
        'holidays' => ['title' => 'Public holidays', 'model' => Holiday::class],
        'pay-items' => ['title' => 'Allowances & deductions', 'model' => PayItem::class],
        'leave-types' => ['title' => 'Leave types', 'model' => LeaveType::class],
    ];

    public function index(string $kind)
    {
        $def = $this->def($kind);
        $query = $def['model']::query();
        $query = match ($kind) {
            'holidays' => $query->whereYear('date', '>=', now()->year - 1)->orderBy('date'),
            'pay-items' => $query->orderBy('type')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return view('setup.'.$kind, ['kind' => $kind, 'title' => $def['title'], 'rows' => $query->get()]);
    }

    public function store(Request $request, string $kind)
    {
        $def = $this->def($kind);
        $def['model']::create($this->validated($request, $kind));

        return back()->with('status', 'Added.');
    }

    public function update(Request $request, string $kind, int $id)
    {
        $row = $this->def($kind)['model']::findOrFail($id);
        $row->update($this->validated($request, $kind, $id));

        return back()->with('status', 'Saved.');
    }

    public function destroy(string $kind, int $id)
    {
        $row = $this->def($kind)['model']::findOrFail($id);
        try {
            $row->delete();
        } catch (QueryException) {
            return back()->withErrors(['row' => 'This item is in use and cannot be deleted.']);
        }

        return back()->with('status', 'Deleted.');
    }

    private function def(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }

    private function validated(Request $request, string $kind, ?int $id = null): array
    {
        $companyId = app(\App\Support\CurrentCompany::class)->id();
        $unique = fn (string $table, string $col) => Rule::unique($table, $col)
            ->where('company_id', $companyId)->ignore($id);

        $rules = match ($kind) {
            'departments' => ['name' => ['required', 'string', 'max:255', $unique('departments', 'name')]],
            'positions' => ['name' => ['required', 'string', 'max:255', $unique('positions', 'name')]],
            'holidays' => [
                'date' => ['required', 'date', $unique('holidays', 'date')],
                'name' => 'required|string|max:255',
            ],
            'pay-items' => [
                'name' => ['required', 'string', 'max:255', $unique('pay_items', 'name')],
                'type' => 'required|in:earning,deduction',
                'epf_applicable' => 'boolean', 'socso_applicable' => 'boolean', 'eis_applicable' => 'boolean',
                'pcb_applicable' => 'boolean', 'hrdf_applicable' => 'boolean',
            ],
            'leave-types' => [
                'name' => 'required|string|max:255',
                'code' => ['required', 'string', 'max:10', $unique('leave_types', 'code')],
                'is_paid' => 'boolean',
                'default_days' => 'required|numeric|min:0|max:366',
                'entitlement_rule' => 'required|in:fixed,annual_ea,sick_ea',
                'gender' => 'nullable|in:male,female',
                'max_carry_forward' => 'required|numeric|min:0|max:366',
                'requires_attachment' => 'boolean',
                'is_active' => 'boolean',
            ],
        };

        // Unchecked checkboxes are absent from the request: treat them as false.
        foreach ($rules as $field => $rule) {
            if ($rule === 'boolean') {
                $request->merge([$field => $request->boolean($field)]);
            }
        }

        return $request->validate($rules);
    }
}
