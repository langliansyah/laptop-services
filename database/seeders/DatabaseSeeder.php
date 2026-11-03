<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('service-desk.admin_email');
        $password = config('service-desk.admin_password');

        if (! $email || ! $password) {
            $this->command?->warn('Admin belum dibuat. Isi ADMIN_EMAIL dan ADMIN_PASSWORD di .env, lalu jalankan php artisan db:seed.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'role' => 'admin', 'password' => Hash::make($password)],
        );
        $this->command?->info("Akun admin siap: {$email}");
    }
}
