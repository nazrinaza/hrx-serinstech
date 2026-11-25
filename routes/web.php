<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SelfServiceController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/company/switch', [CompanyController::class, 'switch'])->name('company.switch');

    // Account (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Self service — every signed-in user
    Route::prefix('me')->name('me.')->controller(SelfServiceController::class)->group(function () {
        Route::get('/profile', 'profile')->name('profile');
        Route::get('/leave', 'leave')->name('leave');
        Route::post('/leave', 'storeLeave')->name('leave.store');
        Route::post('/leave/{leaveRequest}/cancel', 'cancelLeave')->name('leave.cancel');
        Route::get('/payslips', 'payslips')->name('payslips');
        Route::get('/payslips/{payslip}/pdf', 'payslipPdf')->name('payslips.pdf');
    });

    // Approvals — whoever is the approver (HR sees all pending)
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{leaveRequest}', [ApprovalController::class, 'decide'])->name('approvals.decide');
    Route::get('/leave-attachments/{leaveRequest}', [ApprovalController::class, 'attachment'])->name('leave.attachment');

    // HR
    Route::middleware('role:hr')->group(function () {
        Route::get('/employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
        Route::post('/employees/import', [EmployeeController::class, 'import'])->name('employees.import.store');
        Route::get('/employees/import/template', [EmployeeController::class, 'importTemplate'])->name('employees.import.template');
        Route::resource('employees', EmployeeController::class)->except('destroy');
        Route::post('/employees/{employee}/salaries', [EmployeeController::class, 'storeSalary'])->name('employees.salaries.store');
        Route::post('/employees/{employee}/pay-items', [EmployeeController::class, 'storePayItem'])->name('employees.pay-items.store');
        Route::post('/employees/{employee}/pay-items/{item}/end', [EmployeeController::class, 'endPayItem'])->name('employees.pay-items.end');
        Route::post('/employees/{employee}/ytd-opening', [EmployeeController::class, 'storeYtdOpening'])->name('employees.ytd.store');
        Route::post('/employees/{employee}/login', [EmployeeController::class, 'createLogin'])->name('employees.login');
        Route::post('/employees/{employee}/entitlements/{entitlement}', [EmployeeController::class, 'adjustEntitlement'])->name('employees.entitlements.adjust');

        Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
        Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::post('/leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
        Route::get('/leave/balances', [LeaveController::class, 'balances'])->name('leave.balances');

        Route::get('/setup/{kind}', [SetupController::class, 'index'])->name('setup.index');
        Route::post('/setup/{kind}', [SetupController::class, 'store'])->name('setup.store');
        Route::put('/setup/{kind}/{id}', [SetupController::class, 'update'])->name('setup.update');
        Route::delete('/setup/{kind}/{id}', [SetupController::class, 'destroy'])->name('setup.destroy');
    });

    // Payroll
    Route::middleware('role:payroll')->prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{run}', 'show')->name('show');
        Route::post('/{run}/recalculate', 'recalculate')->name('recalculate');
        Route::post('/{run}/finalize', 'finalize')->name('finalize');
        Route::post('/{run}/reopen', 'reopen')->name('reopen');
        Route::post('/{run}/publish', 'publish')->name('publish');
        Route::delete('/{run}', 'destroy')->name('destroy');
        Route::get('/{run}/bank-file', 'bankFile')->name('bank-file');
        Route::get('/{run}/statutory', 'statutoryReport')->name('statutory');
        Route::get('/payslips/{payslip}', 'payslip')->name('payslip');
        Route::get('/payslips/{payslip}/pdf', 'payslipPdf')->name('payslip.pdf');
        Route::post('/payslips/{payslip}/lines', 'addLine')->name('payslip.lines.store');
        Route::delete('/payslip-lines/{line}', 'removeLine')->name('payslip.lines.destroy');
    });

    // Company admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Super admin: all companies
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
});

require __DIR__.'/auth.php';
