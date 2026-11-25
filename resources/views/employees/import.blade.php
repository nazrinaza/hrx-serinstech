<x-app-layout>
    <x-slot name="title">Import employees</x-slot>
    <x-slot name="header"><h1>Import employees from CSV</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('employees.import.store') }}" enctype="multipart/form-data" class="card card-body space-y-4 self-start">
            @csrf
            <ol class="list-decimal pl-5 text-sm space-y-1">
                <li><a class="underline" href="{{ route('employees.import.template') }}">Download the template</a> and fill one row per employee (Excel: <em>Save As → CSV UTF-8</em>).</li>
                <li>Upload it here. If any row has a problem, nothing is imported and you'll see which rows to fix.</li>
            </ol>
            <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
            <button class="btn-primary">Import</button>
        </form>
        <div class="card card-body text-sm">
            <h2 class="mb-2">Columns</h2>
            <p class="mb-2">Required: <code>employee_no, full_name, join_date, basic_salary</code>. Dates as YYYY-MM-DD.</p>
            <p class="text-ink/70">{{ implode(', ', $columns) }}</p>
            <p class="mt-2 text-ink/70">Departments and positions that don't exist yet are created automatically.</p>
        </div>
    </div>
</x-app-layout>
