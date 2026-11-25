<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\MemoRead;
use App\Models\User;
use App\Notifications\MemoPublished;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemoController extends Controller
{
    // ---- Staff -------------------------------------------------------------

    /** All live memos the signed-in person can see. */
    public function feed(Request $request, CurrentCompany $current)
    {
        $memos = $this->visibleQuery($request, $current)->live()->newestFirst()->with('author')->paginate(15);
        $reads = MemoRead::where('user_id', $request->user()->id)->whereIn('memo_id', $memos->pluck('id'))->get()->keyBy('memo_id');

        return view('memos.feed', compact('memos', 'reads'));
    }

    public function read(Request $request, Memo $memo, CurrentCompany $current)
    {
        $this->authorizeView($request, $memo, $current);
        $read = MemoRead::firstOrCreate(['memo_id' => $memo->id, 'user_id' => $request->user()->id]);

        return view('memos.read', ['memo' => $memo->load('author'), 'read' => $read]);
    }

    public function acknowledge(Request $request, Memo $memo, CurrentCompany $current)
    {
        $this->authorizeView($request, $memo, $current);
        $read = MemoRead::firstOrCreate(['memo_id' => $memo->id, 'user_id' => $request->user()->id]);
        if (! $read->acknowledged_at) {
            $read->update(['acknowledged_at' => now()]);
        }

        return back()->with('status', 'Thanks — memo acknowledged.');
    }

    public function attachment(Request $request, Memo $memo, CurrentCompany $current)
    {
        $this->authorizeView($request, $memo, $current);
        abort_unless($memo->attachment_path && Storage::exists($memo->attachment_path), 404);

        return Storage::download($memo->attachment_path, $memo->attachment_name);
    }

    // ---- HR management -----------------------------------------------------

    public function index()
    {
        $memos = Memo::withCount([
            'reads',
            'reads as acknowledged_count' => fn ($q) => $q->whereNotNull('acknowledged_at'),
        ])->orderByDesc('publish_at')->paginate(20);

        return view('memos.index', ['memos' => $memos, 'departments' => Department::pluck('name', 'id')]);
    }

    public function create()
    {
        return view('memos.form', [
            'memo' => new Memo(['priority' => 'normal', 'publish_at' => now()]),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $memo = Memo::create($data);

        $emailed = $this->maybeEmail($request, $memo);

        return redirect()->route('memos.index')->with('status', 'Memo saved'.($emailed ? " and emailed to {$emailed} staff." : '.'));
    }

    public function edit(Memo $memo)
    {
        return view('memos.form', ['memo' => $memo, 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(Request $request, Memo $memo)
    {
        $data = $this->validated($request, $memo);
        if ($request->boolean('remove_attachment') && $memo->attachment_path) {
            Storage::delete($memo->attachment_path);
            $data += ['attachment_path' => null, 'attachment_name' => null];
        }
        $memo->update($data);

        $emailed = $this->maybeEmail($request, $memo);

        return redirect()->route('memos.index')->with('status', 'Memo updated'.($emailed ? " and emailed to {$emailed} staff." : '.'));
    }

    public function destroy(Memo $memo)
    {
        if ($memo->attachment_path) {
            Storage::delete($memo->attachment_path);
        }
        $memo->delete();

        return redirect()->route('memos.index')->with('status', 'Memo deleted.');
    }

    /** Who has (and hasn't) read or acknowledged a memo. */
    public function readers(Memo $memo)
    {
        $reads = $memo->reads()->get()->keyBy('user_id');

        $audience = $this->audience($memo)->map(fn (User $u) => [
            'user' => $u,
            'read' => $reads->get($u->id),
        ])->sortBy(fn ($r) => [$r['read']?->acknowledged_at ? 2 : ($r['read'] ? 1 : 0), $r['user']->name]);

        return view('memos.readers', compact('memo', 'audience'));
    }

    // ---- helpers -----------------------------------------------------------

    private function visibleQuery(Request $request, CurrentCompany $current)
    {
        // HR sees every memo in the company; everyone else sees memos for all staff or their department.
        if ($current->roleIs(User::HR_ROLES)) {
            return Memo::query();
        }

        return Memo::visibleTo($request->user()->employeeIn($current->get()));
    }

    private function authorizeView(Request $request, Memo $memo, CurrentCompany $current): void
    {
        if ($current->roleIs(User::HR_ROLES)) {
            return;
        }
        $visible = $this->visibleQuery($request, $current)->live()->whereKey($memo->id)->exists();
        abort_unless($visible, 404);
    }

    /** Users in the current company who are meant to see this memo. */
    private function audience(Memo $memo): Collection
    {
        $company = app(CurrentCompany::class)->get();
        $users = $company->users()->orderBy('name')->get();
        if ($memo->isForEveryone()) {
            return $users;
        }

        $inDepts = Employee::whereIn('department_id', $memo->department_ids)->whereNotNull('user_id')->pluck('user_id');

        return $users->whereIn('id', $inDepts)->values();
    }

    private function maybeEmail(Request $request, Memo $memo): int
    {
        if (! $request->boolean('send_email') || $memo->publish_at->isFuture()) {
            return 0;
        }
        $recipients = $this->audience($memo);
        Notification::send($recipients, new MemoPublished($memo));

        return $recipients->count();
    }

    private function validated(Request $request, ?Memo $memo = null): array
    {
        $companyId = app(CurrentCompany::class)->id();
        foreach (['is_pinned', 'requires_ack'] as $flag) {
            $request->merge([$flag => $request->boolean($flag)]);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
            'priority' => 'required|in:normal,important',
            'is_pinned' => 'boolean',
            'requires_ack' => 'boolean',
            'publish_at' => 'required|date',
            'expires_at' => 'nullable|date|after:publish_at',
            'audience' => 'required|in:all,departments',
            'department_ids' => 'array|required_if:audience,departments',
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ], ['department_ids.required_if' => 'Pick at least one department, or choose "All staff".']);

        $data['department_ids'] = $data['audience'] === 'departments'
            ? array_values(array_map('intval', $data['department_ids']))
            : null;
        unset($data['audience'], $data['attachment']);

        if ($file = $request->file('attachment')) {
            if ($memo?->attachment_path) {
                Storage::delete($memo->attachment_path);
            }
            $data['attachment_path'] = $file->store('memo-attachments');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        return $data;
    }
}
