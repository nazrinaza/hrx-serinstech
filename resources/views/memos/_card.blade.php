{{-- One memo card. Params: $memo, $read (MemoRead|null), $compact (bool) --}}
@php $important = $memo->priority === 'important'; $acked = $read?->acknowledged_at; @endphp
<article class="card {{ $important ? 'border-l-4 border-l-ember' : '' }} {{ $memo->is_pinned ? 'ring-1 ring-ember/40' : '' }}">
    <div class="card-body">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($memo->is_pinned)<span class="badge bg-ink text-white">Pinned</span>@endif
            @if ($important)<span class="badge bg-ember text-ink">Important</span>@endif
            @unless ($read)<span class="badge bg-mist text-ink">New</span>@endunless
            <span class="text-ink/50">{{ $memo->publish_at->format('d M Y') }} · {{ $memo->author?->name ?? 'HR' }}</span>
        </div>
        <h3 class="mt-2 text-base font-semibold"><a href="{{ route('memos.read', $memo) }}" class="hover:underline">{{ $memo->title }}</a></h3>
        <div class="mt-1 text-sm text-ink/80 {{ $compact ? 'line-clamp-3' : '' }}">{!! nl2br(e($memo->body)) !!}</div>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
            @if ($memo->attachment_path)
                <a href="{{ route('memos.attachment', $memo) }}" class="underline">📎 {{ $memo->attachment_name }}</a>
            @endif
            @if ($compact)<a href="{{ route('memos.read', $memo) }}" class="underline text-ink/70">Read more</a>@endif
            @if ($memo->requires_ack)
                @if ($acked)
                    <span class="text-green-700 text-xs">✓ Acknowledged {{ $acked->format('d M Y') }}</span>
                @else
                    <form method="POST" action="{{ route('memos.acknowledge', $memo) }}" class="ml-auto">@csrf
                        <button class="btn-primary btn-sm">I have read &amp; understood</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</article>
