<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company announcement shown on staff dashboards. */
class Memo extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'title', 'body', 'priority', 'is_pinned', 'publish_at', 'expires_at', 'department_ids',
        'requires_ack', 'attachment_path', 'attachment_name', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'requires_ack' => 'boolean',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'department_ids' => 'array',
        ];
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reads(): HasMany { return $this->hasMany(MemoRead::class); }

    /** Published and not yet expired. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('publish_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Memos this person may see: everyone-memos, plus memos for their department. */
    public function scopeVisibleTo(Builder $q, ?Employee $employee): Builder
    {
        return $q->where(function ($q) use ($employee) {
            $q->whereNull('department_ids');
            if ($employee?->department_id) {
                $q->orWhereJsonContains('department_ids', $employee->department_id);
            }
        });
    }

    public function scopeNewestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('is_pinned')->orderByDesc('publish_at');
    }

    public function isForEveryone(): bool
    {
        return empty($this->department_ids);
    }

    public function status(): string
    {
        return match (true) {
            $this->publish_at->isFuture() => 'scheduled',
            $this->expires_at?->isPast() ?? false => 'expired',
            default => 'live',
        };
    }
}
