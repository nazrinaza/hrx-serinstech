<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Sends one email immediately (not queued) so mail settings can be checked on the spot:
 *   php artisan hrx:mail-test you@example.com
 */
class MailTestCommand extends Command
{
    protected $signature = 'hrx:mail-test {email : Address to send the test email to}';

    protected $description = 'Send a test email using the current mail settings';

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        if (Validator::make(['e' => $to], ['e' => 'email'])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $this->line("Mailer: <info>{$mailer}</info>   From: <info>{$from}</info>   To: <info>{$to}</info>");

        if ($mailer === 'resend' && blank(config('services.resend.key'))) {
            $this->error('RESEND_API_KEY is empty in .env (run `php artisan optimize` after editing .env).');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').' ('.config('app.url').'). If you can read this, email is working.',
                fn ($m) => $m->to($to)->subject(config('app.name').': test email'),
            );
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());
            $this->line('Common causes: wrong API key, or the From domain is not verified in Resend.');

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Written to storage/logs (MAIL_MAILER=log does not really send).'
            : 'Sent. Check the inbox (and spam folder).');

        return self::SUCCESS;
    }
}
