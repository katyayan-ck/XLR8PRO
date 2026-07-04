<?php

namespace App\Services;

use App\Models\Utilities\Settings\SystemSetting;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

/**
 * SystemSettingExportImportService
 * 
 * Handles export and import of system settings
 * Supports: JSON, CSV, Excel
 */
class SystemSettingExportImportService
{
    /**
     * Export all settings as JSON
     */
    public function exportJson(): string
    {
        $settings = SystemSetting::allForExport();
        return json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export all settings as CSV
     */
    public function exportCsv(): string
    {
        $settings = SystemSetting::allForExport();

        $csv = "Topic,Group,Key,Label,Value,Type,Input Type,Description,Help Text\n";

        foreach ($settings as $setting) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $this->escapeCsv($setting['topic']),
                $this->escapeCsv($setting['group']),
                $this->escapeCsv($setting['key']),
                $this->escapeCsv($setting['label']),
                $this->escapeCsv($setting['value']),
                $this->escapeCsv($setting['type']),
                $this->escapeCsv($setting['input_type']),
                $this->escapeCsv($setting['description']),
                $this->escapeCsv($setting['help_text']),
            );
        }

        return $csv;
    }

    /**
     * Import settings from JSON string
     */
    public function importJson(string $json): array
    {
        $settings = json_decode($json, true);

        if (!is_array($settings)) {
            throw new \InvalidArgumentException('Invalid JSON format');
        }

        return $this->importSettings($settings);
    }

    /**
     * Import settings from CSV string
     */
    public function importCsv(string $csv): array
    {
        $lines = array_filter(explode("\n", $csv));
        $headers = null;
        $settings = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $headers = str_getcsv($line);
                continue;
            }

            $values = str_getcsv($line);
            if (count($values) !== count($headers)) {
                continue;
            }

            $setting = array_combine($headers, $values);
            $settings[] = $setting;
        }

        return $this->importSettings($settings);
    }

    /**
     * Import settings from array
     */
    public function importSettings(array $settings): array
    {
        $result = [
            'imported' => 0,
            'updated' => 0,
            'failed' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($settings as $setting) {
                try {
                    // Ensure minimum required fields
                    if (empty($setting['key'])) {
                        $result['failed'][] = $setting;
                        $result['errors'][] = 'Missing key field';
                        continue;
                    }

                    // Check if setting exists
                    $existing = SystemSetting::where('key', $setting['key'])->first();

                    $data = [
                        'value' => $setting['value'] ?? '',
                        'default_value' => $setting['default_value'] ?? $setting['value'] ?? '',
                        'topic' => $setting['topic'] ?? null,
                        'group' => $setting['group'] ?? null,
                        'label' => $setting['label'] ?? null,
                        'type' => $setting['type'] ?? 'string',
                        'input_type' => $setting['input_type'] ?? 'text',
                        'description' => $setting['description'] ?? null,
                        'help_text' => $setting['help_text'] ?? null,
                        'validation_rules' => $setting['validation_rules'] ?? null,
                        'options' => isset($setting['options']) ?
                            (is_array($setting['options']) ? json_encode($setting['options']) : $setting['options'])
                            : null,
                        'iseditable' => $setting['is_editable'] ?? true,
                        'is_visible' => $setting['is_visible'] ?? true,
                    ];

                    if ($existing) {
                        $existing->update($data);
                        $result['updated']++;
                    } else {
                        $data['key'] = $setting['key'];
                        SystemSetting::create($data);
                        $result['imported']++;
                    }
                } catch (\Exception $e) {
                    $result['failed'][] = $setting;
                    $result['errors'][] = $e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Export to Excel file
     */
    public function exportExcel(): string
    {
        // Requires: composer require maatwebsite/excel
        $settings = SystemSetting::allForExport();

        // Return as array for Excel export
        return json_encode($settings);
    }

    /**
     * Get export templates
     */
    public function getTemplates(): array
    {
        return [
            'json' => [
                'extension' => 'json',
                'mime' => 'application/json',
                'method' => 'exportJson',
            ],
            'csv' => [
                'extension' => 'csv',
                'mime' => 'text/csv',
                'method' => 'exportCsv',
            ],
        ];
    }

    /**
     * Escape CSV values
     */
    private function escapeCsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Generate sample/template data for new imports
     */
    public static function getTemplateData(): array
    {
        return [
            [
                'topic' => 'site',
                'group' => 'Basic Settings',
                'key' => 'site.name',
                'label' => 'Site Name',
                'value' => 'My Dealership CRM',
                'default_value' => 'My Dealership CRM',
                'type' => 'string',
                'input_type' => 'text',
                'description' => 'Name of the website',
                'help_text' => 'Enter the main site name',
                'validation_rules' => 'required|string|max:255',
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'topic' => 'site',
                'group' => 'Basic Settings',
                'key' => 'site.slogan',
                'label' => 'Site Slogan',
                'value' => 'Drive Your Success',
                'default_value' => '',
                'type' => 'string',
                'input_type' => 'text',
                'description' => 'Tagline or slogan',
                'help_text' => 'Enter a catchy slogan',
                'validation_rules' => 'string|max:255',
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'topic' => 'site',
                'group' => 'Logo Settings',
                'key' => 'site.logo.header',
                'label' => 'Header Logo',
                'value' => '/images/logo-header.png',
                'default_value' => '/images/logo-header.png',
                'type' => 'image',
                'input_type' => 'image',
                'description' => 'Logo displayed in header',
                'help_text' => 'Upload header logo',
                'validation_rules' => 'string',
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'topic' => 'dealership',
                'group' => 'Contact Details',
                'key' => 'dealership.name',
                'label' => 'Dealership Name',
                'value' => 'ABC Motors',
                'default_value' => '',
                'type' => 'string',
                'input_type' => 'text',
                'description' => 'Name of the dealership',
                'help_text' => 'Your dealership name',
                'validation_rules' => 'required|string|max:255',
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'topic' => 'pricing',
                'group' => 'Tax Rates',
                'key' => 'pricing.gst_rate',
                'label' => 'GST Rate (%)',
                'value' => '18',
                'default_value' => '18',
                'type' => 'integer',
                'input_type' => 'number',
                'description' => 'Goods and Services Tax rate',
                'help_text' => 'GST percentage',
                'validation_rules' => 'required|integer|min:0|max:100',
                'is_editable' => true,
                'is_visible' => true,
            ],
        ];
    }
}
