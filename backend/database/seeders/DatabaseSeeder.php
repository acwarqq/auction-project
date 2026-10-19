<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedTestUsers();
        $this->call(AuctionSeeder::class);
    }

    /**
     * Create the administrator from ADMIN_EMAIL / ADMIN_PASSWORD (backend/.env).
     * Idempotent: existing admin only gets role/status repaired, password untouched.
     */
    private function seedAdmin(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD are not set - admin account was NOT seeded.');

            return;
        }

        // Only fillable attributes (name, email, password) go through mass assignment;
        // the password is hashed automatically by the model's "hashed" cast.
        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'password' => $password],
        );

        // role and status are not fillable - assign them explicitly.
        $admin->role = UserRole::Admin;
        $admin->status = UserStatus::Approved;
        $admin->save();

        $this->command?->info("Admin seeded: {$email}");
    }

    /**
     * Factory test users covering every status. Idempotent by email.
     * Password for all of them: "password".
     */
    private function seedTestUsers(): void
    {
        $users = [
            ['name' => 'Approved User', 'email' => 'approved@example.com', 'state' => 'approved'],
            ['name' => 'Pending User', 'email' => 'pending@example.com', 'state' => null],
            ['name' => 'Rejected User', 'email' => 'rejected@example.com', 'state' => 'rejected'],
            ['name' => 'Banned User', 'email' => 'banned@example.com', 'state' => 'banned'],
            ['name' => 'Second Admin', 'email' => 'admin2@example.com', 'state' => 'admin'],
        ];

        foreach ($users as $user) {
            if (User::where('email', $user['email'])->exists()) {
                continue;
            }

            $factory = User::factory();

            if ($user['state'] !== null) {
                $factory = $factory->{$user['state']}();
            }

            $factory->create([
                'name' => $user['name'],
                'email' => $user['email'],
            ]);
        }

        $this->command?->info('Test users seeded.');
    }
}
