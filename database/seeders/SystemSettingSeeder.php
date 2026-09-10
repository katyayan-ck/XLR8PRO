<?php

namespace Database\Seeders;

use App\Models\Core\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Site Settings
        SystemSetting::ensure('site.name', 'VDMS CRM', 'string', 'Site Name', 'Name of your application');
        SystemSetting::ensure('site.logo', 'logo.png', 'string', 'Site Logo', 'Logo filename');
        SystemSetting::ensure('site.slogan', 'Drive Your Success', 'string', 'Site Slogan', 'Application slogan');

        // Dealership Settings
        SystemSetting::ensure('dealership.name', 'ABC Motors', 'string', 'Dealership Name', 'Name of dealership');
        SystemSetting::ensure('dealership.phone', '91-8800000000', 'string', 'Dealership Phone', 'Contact phone number');
        SystemSetting::ensure('dealership.email', 'info@abcmotors.com', 'string', 'Dealership Email', 'Contact email address');

        // Pricing Settings
        SystemSetting::ensure('pricing.gst_rate', '18', 'float', 'GST Rate', 'Goods and Service Tax rate');
        SystemSetting::ensure('pricing.tds_rate', '1', 'float', 'TDS Rate', 'Tax Deducted at Source rate');

        // Feature Flags
        SystemSetting::ensure('feature.live_chat', 'true', 'boolean', 'Enable Live Chat', 'Enable or disable live chat feature');
        SystemSetting::ensure('feature.notifications', 'true', 'boolean', 'Enable Notifications', 'Enable or disable notifications');
    }
}
