<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('pay_date');
            $table->string('status', 12)->default('draft'); // draft | finalized
            $table->boolean('is_published')->default(false); // visible to employees
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'year', 'month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);      // full monthly basic
            $table->decimal('days_worked_ratio', 6, 4)->default(1); // proration for joiners / leavers
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->decimal('epf_wages', 12, 2)->default(0);
            $table->decimal('epf_employee', 10, 2)->default(0);
            $table->decimal('epf_employer', 10, 2)->default(0);
            $table->decimal('socso_employee', 10, 2)->default(0);
            $table->decimal('socso_employer', 10, 2)->default(0);
            $table->decimal('eis_employee', 10, 2)->default(0);
            $table->decimal('eis_employer', 10, 2)->default(0);
            $table->decimal('pcb', 10, 2)->default(0);
            $table->decimal('pcb_taxable', 12, 2)->default(0); // remuneration subject to PCB this month
            $table->decimal('hrdf', 10, 2)->default(0);
            $table->json('employee_snapshot')->nullable(); // name, IC, bank… as at run time
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 10); // earning | deduction
            $table->decimal('amount', 12, 2);
            $table->boolean('is_manual')->default(false); // one-off line added on the draft run
            $table->json('flags')->nullable(); // {epf, socso, eis, pcb, hrdf} applicability
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
