<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant (or revoke) admin rights for a user by email.
 *
 *   php artisan user:make-admin you@example.com
 *   php artisan user:make-admin you@example.com --revoke
 */
class MakeUserAdmin extends Command
{
    protected $signature = 'user:make-admin {email} {--revoke : Remove admin rights instead of granting them}';

    protected $description = 'Grant or revoke admin rights for a user.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->is_admin = ! $this->option('revoke');
        $user->save();

        $this->info(sprintf(
            '%s is %s an admin.',
            $user->email,
            $user->is_admin ? 'now' : 'no longer',
        ));

        return self::SUCCESS;
    }
}
