<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => User::SUPER_ADMIN_EMAIL]);
        $bootstrapPassword = config('admin.super_admin_bootstrap_password');

        if (! $user->exists && blank($bootstrapPassword)) {
            throw new RuntimeException('Set SUPER_ADMIN_BOOTSTRAP_PASSWORD before creating the super-admin account.');
        }

        $user->name = 'Karl Steven A. Maddela';
        if (filled($bootstrapPassword)) {
            $user->password = $bootstrapPassword;
        }
        $user->save();
    }
}
