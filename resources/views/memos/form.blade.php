<x-app-layout>
    @php
        $new = ! $memo->exists;
        $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
        $selected = old('department_ids', $memo->department_ids ?? []);
    @endphp
    <x-slot name="title">{{ $new ? 'New memo' : 'Edit memo' }}</x-slot>
    <x-slot name="header"><h1>{{ $new ? 'New memo' : 'Edit memo' }}</h1></x-slot>

    <form method="POST" action="{{ $new ? route('memos.store') : route('memos.update', $memo) }}" enctype="multipart/form-data"
          class="grid gap-6 lg:grid-cols-3" x-data="{ audience: '{{ old('audience', $memo->isForEveryone() ? 'all' : 'departments') }}' }">
        @csrf @unless ($new) @method('PUT') @endunless

        <div class="card card-body space-y-4 lg:col-span-2">
            <div><label class="label">Title</label><input name="title" class="input" value="{{ old('title', $memo->title) }}" required maxlength="255"></div>
            <div><label class="label">Message</label><textarea name="body" rows="12" class="input" required>{{ old('body', $memo->body) }}</textarea>
                <p class="help">Plain text. Line breaks are kept.</p></div>
            <div>
                <label class="label">Attachment (optional)</label>
                @if ($memo->attachment_path)
                    <p class="text-sm mb-1">Current: {{ $memo->attachment_name }}
                        <label class="ml-3 inline-flex items-center gap-1 text-red-700"><input type="checkbox" name="remove_attachment" value="1" class="rounded border-slate"> remove</label></p>
                @endif
                <input type="file" name="attachment" class="text-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <p class="help">PDF, image, Word or Excel, up to 5 MB.</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-body space-y-3">
                <h2>Who sees it</h2>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="audience" value="all" x-model="audience" class="text-ember focus:ring-ember"> All staff</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="audience" value="departments" x-model="audience" class="text-ember focus:ring-ember"> Selected departments only</label>
                <div x-show="audience === 'departments'" x-cloak class="pl-6 space-y-1">
                    @forelse ($departments as $d)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="department_ids[]" value="{{ $d->id }}" class="rounded border-slate text-ember focus:ring-ember" @checked(in_array($d->id, $selected))> {{ $d->name }}</label>
                    @empty
                        <p class="help">No departments set up yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card card-body space-y-3">
                <h2>Options</h2>
                <div><label class="label">Priority</label>
                    <select name="priority" class="input"><option value="normal" @selected(old('priority', $memo->priority) === 'normal')>Normal</option><option value="important" @selected(old('priority', $memo->priority) === 'important')>Important (highlighted)</option></select></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked(old('is_pinned', $memo->is_pinned))> Pin to the top of the dashboard</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_ack" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked(old('requires_ack', $memo->requires_ack))> Staff must click "I have read &amp; understood"</label>
                <div><label class="label">Publish from</label><input type="datetime-local" name="publish_at" class="input" value="{{ old('publish_at', $dt($memo->publish_at)) }}" required>
                    <p class="help">Set a future time to schedule it.</p></div>
                <div><label class="label">Hide after (optional)</label><input type="datetime-local" name="expires_at" class="input" value="{{ old('expires_at', $dt($memo->expires_at)) }}"></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_email" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked(old('send_email', $new))> Email it to the audience now</label>
                <p class="help">Emails only go out if the memo is already published. Staff need a login to receive them.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary">{{ $new ? 'Publish memo' : 'Save changes' }}</button>
                <a href="{{ route('memos.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</x-app-layout>
