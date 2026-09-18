<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DefaultInstallationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            DefaultConnectionSeeder::class,
            DefaultRbacSeeder::class,
            DefaultMenuSeeder::class,
        ]);
    }
}
