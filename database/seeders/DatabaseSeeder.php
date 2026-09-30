<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * PRODUCTION-SAFE seed: roles, permissions and base master data only.
 * No users are created here — use `php artisan app:create-admin` or the /setup page.
 * Sample/test data lives in DemoDataSeeder and must be run explicitly.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, MasterDataSeeder::class]);
    }
}
