<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #25343F; }
    .hdr { border-bottom: 3px solid #FF9B51; padding-bottom: 8px; margin-bottom: 12px; }
    .hdr td { vertical-align: top; }
    h1 { font-size: 15px; margin: 0; }
    .muted { color: #5b6b77; }
    table { width: 100%; border-collapse: collapse; }
    .info td { padding: 2px 4px; }
    .box { border: 1px solid #BFC9D1; }
    .box th { background: #EAEFEF; text-align: left; padding: 5px 6px; font-size: 10px; text-transform: uppercase; }
    .box td { padding: 4px 6px; border-top: 1px solid #EAEFEF; }
    .r { text-align: right; }
    .tot td { font-weight: bold; background: #EAEFEF; }
    .net { background: #25343F; color: #fff; padding: 10px 12px; margin-top: 12px; }
    .net .amt { color: #FF9B51; font-size: 18px; font-weight: bold; }
    .foot { margin-top: 18px; font-size: 9px; color: #5b6b77; }
</style></head>
<body>
@php
    $s = $payslip->employee_snapshot;
    $earn = $payslip->lines->where('type', 'earning');
    $ded = $payslip->lines->where('type', 'deduction');
    $stat = ['EPF' => $payslip->epf_employee, 'SOCSO' => $payslip->socso_employee, 'EIS' => $payslip->eis_employee, 'PCB' => $payslip->pcb];
    $m = fn ($v) => number_format((float) $v, 2);
@endphp
<table class="hdr"><tr>
    <td style="width:55%"><img src="{{ $logo }}" style="height:42px"><br>
        <strong>{{ $company->name }}</strong>@if ($company->registration_no) ({{ $company->registration_no }})@endif<br>
        <span class="muted">{!! nl2br(e($company->address)) !!}</span></td>
    <td class="r"><h1>PAYSLIP</h1><div>{{ $payslip->run->label() }}</div><div class="muted">Pay date: {{ $payslip->run->pay_date->format('d M Y') }}</div></td>
</tr></table>

<table class="info" style="margin-bottom:12px">
    <tr><td class="muted" style="width:18%">Name</td><td style="width:32%"><strong>{{ $s['full_name'] }}</strong></td><td class="muted" style="width:18%">Employee no.</td><td>{{ $s['employee_no'] }}</td></tr>
    <tr><td class="muted">IC / Passport</td><td>{{ $s['ic_no'] ?: $s['passport_no'] }}</td><td class="muted">Position</td><td>{{ $s['position'] }}</td></tr>
    <tr><td class="muted">EPF no.</td><td>{{ $s['epf_no'] }}</td><td class="muted">Department</td><td>{{ $s['department'] }}</td></tr>
    <tr><td class="muted">SOCSO no.</td><td>{{ $s['socso_no'] }}</td><td class="muted">Tax no.</td><td>{{ $s['tax_no'] }}</td></tr>
</table>

<table><tr>
    <td style="width:50%; vertical-align:top; padding-right:6px">
        <table class="box"><tr><th>Earnings</th><th class="r">RM</th></tr>
            @foreach ($earn as $l)<tr><td>{{ $l->name }}</td><td class="r">{{ $m($l->amount) }}</td></tr>@endforeach
            <tr class="tot"><td>Gross pay</td><td class="r">{{ $m($payslip->gross_pay) }}</td></tr>
        </table>
    </td>
    <td style="width:50%; vertical-align:top; padding-left:6px">
        <table class="box"><tr><th>Deductions</th><th class="r">RM</th></tr>
            @foreach ($ded as $l)<tr><td>{{ $l->name }}</td><td class="r">{{ $m($l->amount) }}</td></tr>@endforeach
            @foreach ($stat as $n => $v)<tr><td>{{ $n }}</td><td class="r">{{ $m($v) }}</td></tr>@endforeach
            <tr class="tot"><td>Total deductions</td><td class="r">{{ $m($payslip->total_deductions) }}</td></tr>
        </table>
    </td>
</tr></table>

<table class="net"><tr><td>NET PAY<br><span class="muted" style="color:#BFC9D1">{{ $s['bank_name'] }} {{ $s['bank_account_no'] }}</span></td><td class="r amt">RM {{ $m($payslip->net_pay) }}</td></tr></table>

<table class="box" style="margin-top:12px"><tr><th>Employer contributions</th><th class="r">EPF</th><th class="r">SOCSO</th><th class="r">EIS</th><th class="r">HRDF</th></tr>
    <tr><td></td><td class="r">{{ $m($payslip->epf_employer) }}</td><td class="r">{{ $m($payslip->socso_employer) }}</td><td class="r">{{ $m($payslip->eis_employer) }}</td><td class="r">{{ $m($payslip->hrdf) }}</td></tr>
</table>

<p class="foot">This is a computer-generated payslip and does not require a signature.</p>
</body></html>
