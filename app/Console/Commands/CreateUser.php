<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

/**
 * Регистрации нет: учётные записи заводятся только здесь (как awg:user в awgkeys).
 */
#[Signature('sports:user
    {email : Login email; an existing account gets a new password}
    {--name= : Display name (default: the part of the email before @)}
    {--password= : Password. Omit to type it in hidden or to generate one}'
)]
#[Description('Create a web/API account or reset its password')]
class CreateUser extends Command
{
    private const int GENERATED_PASSWORD_LENGTH = 16;

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $user = User::firstOrNew(['email' => $email]);

        $password = $this->option('password');
        $generated = false;

        if ($password === null && $this->input->isInteractive()) {
            $password = password('Password (empty — generate)');
        }

        if (blank($password)) {
            $password = Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false);
            $generated = true;
        }

        $user->name = $this->option('name') ?? $user->name ?? Str::before($email, '@');
        $user->password = $password;
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        if ($generated) {
            $this->components->info("The account {$email} is ready, password: {$password}");
        } else {
            $this->components->info("The account {$email} is ready.");
        }

        return self::SUCCESS;
    }
}
