<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_no')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            // Statutory employer numbers
            $table->string('epf_employer_no')->nullable();
            $table->string('socso_employer_no')->nullable();
            $table->string('tax_employer_no')->nullable(); // LHDN "E" number
            $table->decimal('hrdf_rate', 4, 2)->default(0); // 0, 0.5 or 1 (%)
            // ISO weekday numbers (1 = Mon … 7 = Sun) that are working days
            $table->json('work_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // owner | admin | hr | manager | employee
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->foreignId('current_company_id')->nullable()->after('is_super_admin')
                ->constrained('companies')->nullOnDelete();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
            $table->dropColumn('is_super_admin');
        });
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
