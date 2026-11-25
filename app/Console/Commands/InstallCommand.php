<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first super-admin login on a fresh server. Run it yourself over SSH:
 *   php artisan hrx:install
 * After signing in, the super admin creates the first company in the app.
 */
class InstallCommand extends Command
{
    protected $signature = 'hrx:install';

    protected $description = 'Create the first super-admin user';

    public function handle(): int
    {
        if (User::where('is_super_admin', true)->exists() && ! $this->confirm('A super admin already exists. Create another?')) {
            return self::SUCCESS;
        }

        $name = text('Full name', required: true);
        $email = text('Email', required: true, validate: fn ($v) => Validator::make(['e' => $v], ['e' => 'email|unique:users,email'])->fails() ? 'Enter a new, valid email.' : null);
        $password = password('Password (min 12 characters)', required: true, validate: fn ($v) => strlen($v) < 12 ? 'Use at least 12 characters.' : null);

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $this->info("Super admin {$email} created. Sign in at ".config('app.url').' and create your first company.');

        return self::SUCCESS;
    }
}
