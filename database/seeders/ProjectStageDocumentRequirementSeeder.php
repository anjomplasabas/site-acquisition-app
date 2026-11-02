<?php

namespace Database\Seeders;

use App\Models\StageDocumentRequirement;
use Illuminate\Database\Seeder;

class ProjectStageDocumentRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            ['name' => 'NTP', 'sort_order' => 1],
            ['name' => 'MOA', 'sort_order' => 2],
        ];

        foreach ($requirements as $requirement) {
            StageDocumentRequirement::firstOrCreate(
                ['module' => 'project', 'stage' => 'Planning', 'name' => $requirement['name']],
                array_merge($requirement, ['is_required' => true, 'is_active' => true])
            );
        }
    }
}
