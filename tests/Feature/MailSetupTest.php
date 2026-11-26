<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSetupTest extends TestCase
{
    public function test_resend_mailer_is_available_when_a_key_is_set(): void
    {
        config(['services.resend.key' => 're_test_dummy']);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());
    }

    public function test_mail_test_command_sends_one_email(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('hrx:mail-test', ['email' => 'someone@serinstech.test'])->assertSuccessful();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('someone@serinstech.test', $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_mail_test_command_stops_when_resend_key_is_missing(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => null]);

        $this->artisan('hrx:mail-test', ['email' => 'someone@serinstech.test'])
            ->expectsOutputToContain('RESEND_API_KEY is empty')
            ->assertFailed();
    }

    public function test_mail_test_command_rejects_a_bad_address(): void
    {
        $this->artisan('hrx:mail-test', ['email' => 'not-an-email'])->assertFailed();
    }
}
