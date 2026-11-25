<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LeaveRequest $leave) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $l = $this->leave->loadMissing('leaveType');
        $status = ucfirst($l->status);

        return (new MailMessage)
            ->subject("Your leave request was {$l->status}")
            ->line("Your {$l->leaveType->name} request for ".$l->start_date->format('d M Y').' – '.$l->end_date->format('d M Y')." was {$status}.")
            ->when($l->decision_note, fn ($m) => $m->line('Note: '.$l->decision_note))
            ->action('View my leave', route('me.leave'));
    }
}
