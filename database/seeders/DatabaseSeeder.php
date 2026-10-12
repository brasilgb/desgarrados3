<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Seed editable institutional copy everywhere; add fictional stories only locally. */
    public function run(): void
    {
        $this->call(PortalContentSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
