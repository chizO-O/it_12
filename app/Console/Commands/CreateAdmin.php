<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'grove:create-admin {--name= : Administrator name} {--email= : Administrator email}';

    protected $description = 'Create the first GroveMap administrator using a privately entered password';

    public function handle(): int
    {
        if (User::where('role', UserRole::Admin->value)->exists()) {
            $this->error('An Admin already exists. Use the employee account page to create additional employees.');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => strtolower(trim($this->option('email') ?: $this->ask('Email'))),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new UniqueUserEmail],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->info('Admin created. Sign in and verify your email to access the Office dashboard.');
        event(new Registered($admin));

        return self::SUCCESS;
    }
}
