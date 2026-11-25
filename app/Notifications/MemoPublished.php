<?php

namespace App\Notifications;

use App\Models\Memo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MemoPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Memo $memo) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->memo->loadMissing('company');

        return (new MailMessage)
            ->subject(($m->priority === 'important' ? '[Important] ' : '')."{$m->company->name}: {$m->title}")
            ->line(Str::limit($m->body, 400))
            ->when($m->requires_ack, fn ($mail) => $mail->line('Please read and acknowledge this memo.'))
            ->action('Read the memo', route('memos.read', $m));
    }
}
