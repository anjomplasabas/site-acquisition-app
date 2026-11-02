<?php

namespace Database\Seeders;

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
        $this->call(RoleSeeder::class);
        $this->call(DepartmentSeeder::class);

        $this->call(DemoUserSeeder::class);
        $this->call(PipelineStageDocumentRequirementSeeder::class);
        $this->call(ProjectStageDocumentRequirementSeeder::class);
    }
}
