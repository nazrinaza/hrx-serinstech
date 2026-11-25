<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->boolean('is_paid')->default(true);
            $table->decimal('default_days', 5, 1)->default(0);
            $table->string('entitlement_rule', 20)->default('fixed'); // fixed | annual_ea | sick_ea
            $table->string('gender', 10)->nullable(); // only for this gender (maternity / paternity)
            $table->decimal('max_carry_forward', 5, 1)->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 1);
            $table->decimal('carried_forward', 5, 1)->default(0);
            $table->decimal('adjustment', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('half_day', 2)->nullable(); // am | pm (single-day requests only)
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->boolean('from_self_service')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_types');
    }
};
