<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\PayItem;

/**
 * Starter data every new company gets: Employment Act leave types and common pay items.
 * Expects the CurrentCompany to already be set to $company.
 */
class CompanyDefaults
{
    public static function apply(Company $company): void
    {
        $leave = [
            ['Annual leave', 'AL', true, 8, 'annual_ea', null, 8],
            ['Sick leave', 'SL', true, 14, 'sick_ea', null, 0],
            ['Hospitalisation leave', 'HL', true, 60, 'fixed', null, 0],
            ['Maternity leave', 'ML', true, 98, 'fixed', 'female', 0],
            ['Paternity leave', 'PL', true, 7, 'fixed', 'male', 0],
            ['Compassionate leave', 'CL', true, 3, 'fixed', null, 0],
            ['Unpaid leave', 'UL', false, 0, 'fixed', null, 0],
        ];
        foreach ($leave as [$name, $code, $paid, $days, $rule, $gender, $cf]) {
            LeaveType::create([
                'company_id' => $company->id, 'name' => $name, 'code' => $code, 'is_paid' => $paid,
                'default_days' => $days, 'entitlement_rule' => $rule, 'gender' => $gender,
                'max_carry_forward' => $cf, 'requires_attachment' => in_array($code, ['SL', 'HL'], true),
            ]);
        }

        // [name, type, epf, socso, eis, pcb, hrdf]
        $items = [
            ['Fixed allowance', 'earning', true, true, true, true, true],
            ['Transport allowance', 'earning', false, true, true, true, false],
            ['Phone allowance', 'earning', false, true, true, true, false],
            ['Overtime', 'earning', false, true, true, true, false],
            ['Commission', 'earning', true, true, true, true, true],
            ['Expense claim reimbursement', 'earning', false, false, false, false, false],
            ['Salary advance repayment', 'deduction', false, false, false, false, false],
            ['Loan repayment', 'deduction', false, false, false, false, false],
        ];
        foreach ($items as [$name, $type, $epf, $socso, $eis, $pcb, $hrdf]) {
            PayItem::create([
                'company_id' => $company->id, 'name' => $name, 'type' => $type,
                'epf_applicable' => $epf, 'socso_applicable' => $socso, 'eis_applicable' => $eis,
                'pcb_applicable' => $pcb, 'hrdf_applicable' => $hrdf,
            ]);
        }
    }
}
