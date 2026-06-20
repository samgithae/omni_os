<?php

namespace Database\Seeders;

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
        User::query()->updateOrCreate([
            'email' => 'dev-admin@example.test',
        ], [
            'name' => 'Dev Admin',
            'password' => 'password',
        ]);

        $this->call([
            BrandSeeder::class,
            SampleLeadSeeder::class,
        ]);
    }
}
