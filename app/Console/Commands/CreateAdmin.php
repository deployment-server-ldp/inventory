<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--username=} {--email=}';

    protected $description = 'Create a Super Admin account (interactive; the password is never passed on the command line)';

    public function handle(): int
    {
        if (! Role::where('name', Role::SUPER_ADMIN)->exists()) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        $name = $this->option('name') ?: $this->ask('Full name');
        $username = $this->option('username') ?: $this->ask('Username');
        $email = $this->option('email') ?: $this->ask('E-mail (optional)', '') ?: null;
        $password = $this->secret('Password (min 10 chars, upper+lower case and a number)');
        $confirm = $this->secret('Confirm password');

        $v = Validator::make(compact('name', 'username', 'email', 'password') + ['password_confirmation' => $confirm], [
            'name' => ['required', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name, 'username' => $username, 'email' => $email, 'password' => $password,
            'role_id' => Role::where('name', Role::SUPER_ADMIN)->value('id'), 'is_active' => true,
        ]);
        ActivityLogger::log('setup.admin_created', "Super Admin '{$user->username}' created from CLI", $user, module: 'admin', user: $user);
        $this->info("Super Admin '{$username}' created. You can now sign in.");

        return self::SUCCESS;
    }
}
