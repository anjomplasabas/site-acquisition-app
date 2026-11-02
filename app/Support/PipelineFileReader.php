<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class PipelineFileReader
{
    /** @var array<int, string> */
    public const IMPORTABLE_FIELDS = [
        'pipeline_id', 'pipeline_name', 'endorsement_date',
        'assigned_saq_email', 'assigned_saq_name',
        'site_profile_id', 'site_name', 'site_type', 'developer',
        'total_unit_count', 'street', 'barangay', 'city', 'province',
        'contact_name', 'contact_designation', 'contact_email', 'contact_mobile_number',
        'floors_surveyed', 'actual_unit_count', 'has_roof_access',
        'equipment_notes', 'remarks', 'building_network_system',
        'indoor_outlet_capacity', 'isp_count_per_unit',
        'telco_room_location', 'electrical_room_location',
        'lobby_booth_allowed', 'lobby_standee_allowed',
        'lobby_flyers_stand_allowed', 'roaming_sales_rep_allowed',
        'telco_room_fee', 'marketing_paraphernalia', 'brochure_posting_location',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'building_name' => 'site_name',
        'name' => 'pipeline_name',
        'municipality' => 'city',
        'saq_in_charge' => 'assigned_saq_name',
        'date_of_visit' => 'endorsement_date',
        'contact_person' => 'contact_name',
        'contact_number' => 'contact_mobile_number',
        'email' => 'contact_email',
        'address' => 'street',
        'no_of_residential_flrs' => 'floors_surveyed',
        'total_units_of_bldg' => 'total_unit_count',
        'total_units_per_floors' => 'actual_unit_count',
        'bldg_network_system' => 'building_network_system',
        'no_of_isp_allowed' => 'isp_count_per_unit',
        'telco_rm_location' => 'telco_room_location',
        'ee_rm_location' => 'electrical_room_location',
        'booth' => 'lobby_booth_allowed',
        'standee' => 'lobby_standee_allowed',
        'flyers_stand' => 'lobby_flyers_stand_allowed',
        'roaming_sales_rep' => 'roaming_sales_rep_allowed',
        'telco_rm_fee' => 'telco_room_fee',
        'designation' => 'contact_designation',
    ];

    /**
     * @return array<int, array{header: string, samples: array<int, string>}>
     */
    public function extractColumns(string $path, string $extension, int $sampleCount = 3): array
    {
        $rawRows = $this->readRawRows($path, $extension, 15);

        if (empty($rawRows)) {
            return [];
        }

        $headerRowIndex = $this->detectHeaderRow($rawRows);
        $headers = $rawRows[$headerRowIndex];
        $dataRows = array_slice($rawRows, $headerRowIndex + 1, $sampleCount);

        $columns = [];

        foreach ($headers as $colIndex => $header) {
            $header = is_string($header) ? trim($header) : '';

            if (blank($header)) {
                continue;
            }

            $samples = array_values(array_filter(
                array_map(fn ($row) => trim((string) ($row[$colIndex] ?? '')), $dataRows),
                fn ($v) => $v !== ''
            ));

            $columns[] = [
                'header' => $header,
                'samples' => array_slice($samples, 0, $sampleCount),
            ];
        }

        return $columns;
    }

    /**
     * @param  array<int, array{header: string}>  $columns
     * @return array<string, string|null>
     */
    public function suggestMapping(array $columns): array
    {
        $suggestions = [];

        foreach ($columns as ['header' => $header]) {
            $normalized = $this->normalizeKey($header);

            if (in_array($normalized, self::IMPORTABLE_FIELDS, true)) {
                $suggestions[$header] = $normalized;
            } elseif (isset(self::ALIASES[$normalized])) {
                $suggestions[$header] = self::ALIASES[$normalized];
            } else {
                $suggestions[$header] = null;
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, string>  $mapping  header → field name
     * @return array<int, array{row_number: int, data: array<string, string>}>
     */
    public function readRows(string $path, string $extension, array $mapping): array
    {
        $rawRows = $this->readRawRows($path, $extension, PHP_INT_MAX);

        if (empty($rawRows)) {
            return [];
        }

        $headerRowIndex = $this->detectHeaderRow($rawRows);
        $headers = $rawRows[$headerRowIndex];
        $dataRows = array_slice($rawRows, $headerRowIndex + 1);

        $rows = [];

        foreach ($dataRows as $rowOffset => $rawRow) {
            $data = [];

            foreach ($headers as $colIndex => $header) {
                $header = is_string($header) ? trim($header) : '';

                if (blank($header)) {
                    continue;
                }

                $field = $mapping[$header] ?? null;

                if (blank($field)) {
                    continue;
                }

                $data[$field] = trim((string) ($rawRow[$colIndex] ?? ''));
            }

            if (collect($data)->filter()->isEmpty()) {
                continue;
            }

            $rows[] = [
                'row_number' => $headerRowIndex + $rowOffset + 2,
                'data' => $data,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRawRows(string $path, string $extension, int $limit): array
    {
        return match (strtolower($extension)) {
            'xlsx', 'xls' => $this->readXlsxRawRows($path, $limit),
            default => $this->readCsvRawRows($path, $limit),
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readCsvRawRows(string $path, int $limit): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];
        $count = 0;

        while ($count < $limit && ($line = fgetcsv($handle)) !== false) {
            $rows[] = $line;
            $count++;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readXlsxRawRows(string $path, int $limit): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        $allRows = $worksheet->toArray('', false, true, false);

        if ($limit === PHP_INT_MAX) {
            return $allRows;
        }

        return array_slice($allRows, 0, $limit);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function detectHeaderRow(array $rows): int
    {
        $best = 0;
        $bestCount = 0;

        foreach ($rows as $index => $row) {
            $count = count(array_filter(
                $row,
                fn ($v) => is_string($v) && filled($v) && ! is_numeric($v)
            ));

            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $index;
            }
        }

        return $best;
    }

    private function normalizeKey(string $key): string
    {
        return str($key)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
