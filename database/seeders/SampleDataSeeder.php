<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    public function run()
    {
        $sql = file_get_contents(database_path('seeders/sample_data.sql'));
        DB::unprepared($sql);
        
        $this->command->info('Sample data seeded successfully!');
    }
}
