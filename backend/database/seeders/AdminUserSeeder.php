<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = 'admin';
        $name = 'Property Custodian';

        $existingUser = User::where('username', $username)->first();

        if ($existingUser) {
            if ($existingUser->role !== 'admin') {
                throw new RuntimeException('The configured admin username is already assigned to a non-admin user.');
            }

            return;
        }

        if (! $this->command) {
            throw new RuntimeException('Run this seeder through `php artisan db:seed` to securely enter the admin password.');
        }

        $password = $this->command->secret('Enter a password for the admin account (minimum 8 characters)');
        $passwordConfirmation = $this->command->secret('Confirm the admin password');

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('The admin password must be at least 8 characters long.');
        }

        if (! is_string($passwordConfirmation) || ! hash_equals($password, $passwordConfirmation)) {
            throw new RuntimeException('The admin password confirmation does not match.');
        }

        $admin = new User([
            'username' => $username,
            'name' => $name,
            'password' => $password,
        ]);
        $admin->role = 'admin';
        $admin->save();
    }
}
