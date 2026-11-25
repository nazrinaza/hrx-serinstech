<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_no', 30);
            // Personal
            $table->string('full_name');
            $table->string('ic_no', 20)->nullable();       // MyKad
            $table->string('passport_no', 30)->nullable();
            $table->string('citizenship', 20)->default('citizen'); // citizen | pr | foreigner
            $table->string('gender', 10)->nullable();      // male | female
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 20)->default('single'); // single | married | divorced | widowed
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            // Job
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 20)->default('permanent'); // permanent | contract | probation | intern
            $table->date('join_date');
            $table->date('resign_date')->nullable();
            // Bank
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 40)->nullable();
            // Statutory
            $table->string('epf_no', 30)->nullable();
            $table->string('socso_no', 30)->nullable();
            $table->string('tax_no', 30)->nullable();
            $table->decimal('epf_employee_rate', 5, 2)->nullable(); // override (voluntary higher rate)
            $table->boolean('is_tax_resident')->default(true);
            $table->boolean('spouse_working')->default(true);
            $table->unsignedTinyInteger('children_count')->default(0); // children qualifying for tax relief
            $table->boolean('is_disabled')->default(false);
            $table->boolean('spouse_disabled')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'employee_no']);
        });

        // Salary history: the row with the latest effective_from <= date is the salary on that date.
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('basic_salary', 12, 2);
            $table->string('reason')->nullable(); // New hire, Increment, Promotion…
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from']);
        });

        // Company catalogue of allowances / deductions
        Schema::create('pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10); // earning | deduction
            $table->boolean('epf_applicable')->default(true);
            $table->boolean('socso_applicable')->default(true);
            $table->boolean('eis_applicable')->default(true);
            $table->boolean('pcb_applicable')->default(true);
            $table->boolean('hrdf_applicable')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // Recurring allowances / deductions per employee
        Schema::create('employee_pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_items');
        Schema::dropIfExists('pay_items');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employees');
    }
};
