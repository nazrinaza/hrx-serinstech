<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LeaveRequest $leave) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $l = $this->leave->loadMissing('employee', 'leaveType');

        return (new MailMessage)
            ->subject("Leave request: {$l->employee->full_name} ({$l->leaveType->name})")
            ->line("{$l->employee->full_name} has requested {$l->days} day(s) of {$l->leaveType->name}.")
            ->line('Dates: '.$l->start_date->format('d M Y').' – '.$l->end_date->format('d M Y'))
            ->line('Reason: '.($l->reason ?: '—'))
            ->action('Review request', route('approvals.index'));
    }
}
