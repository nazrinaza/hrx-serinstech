<?php

namespace App\Payroll;

use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;

class PayslipPdf
{
    public static function download(Payslip $payslip)
    {
        $payslip->loadMissing('run', 'lines', 'company');
        $company = $payslip->company;
        $logo = $company->logo_path && is_file(storage_path('app/public/'.$company->logo_path))
            ? storage_path('app/public/'.$company->logo_path)
            : public_path('images/logo-light.png');

        $name = sprintf('payslip-%04d-%02d-%s.pdf', $payslip->run->year, $payslip->run->month,
            str($payslip->employee_snapshot['employee_no'] ?? $payslip->employee_id)->slug());

        return Pdf::loadView('payroll.payslip-pdf', [
            'payslip' => $payslip,
            'company' => $company,
            'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents($logo)),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->download($name);
    }
}
