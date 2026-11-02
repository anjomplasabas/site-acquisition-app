<?php

use App\Models\ProjectStage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const KEYS = ['project_status', 'rfco_status', 'acceptance_status'];

    private const RENAMES = ['ongoing' => 'On-going', 'on-hold' => 'On-hold'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameValues(self::RENAMES);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameValues(array_flip(self::RENAMES));
    }

    /** @param  array<string, string>  $renames */
    private function renameValues(array $renames): void
    {
        foreach (ProjectStage::query()->whereNotNull('field_values')->cursor() as $stage) {
            $fieldValues = $stage->field_values;
            $changed = false;

            foreach (self::KEYS as $key) {
                if (isset($fieldValues[$key], $renames[$fieldValues[$key]])) {
                    $fieldValues[$key] = $renames[$fieldValues[$key]];
                    $changed = true;
                }
            }

            if ($changed) {
                $stage->update(['field_values' => $fieldValues]);
            }
        }
    }
};
