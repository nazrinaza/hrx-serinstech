<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Earlier-in-the-year totals from before this system (or from a previous employer),
     * so PCB is correct when starting mid-year.
     */
    public function up(): void
    {
        Schema::create('employee_ytd_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('remuneration', 12, 2)->default(0); // taxable pay received
            $table->decimal('epf', 12, 2)->default(0);          // employee EPF paid
            $table->decimal('pcb', 12, 2)->default(0);          // PCB already deducted
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ytd_openings');
    }
};
