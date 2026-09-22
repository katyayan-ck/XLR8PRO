<?php

namespace Tests\Unit\Services;

use App\Models\Utilities\Settings\SystemSetting;
use App\Services\DateFormatService;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DateFormatServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DateFormatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DateFormatService(new SystemSettingService);
    }

    public function test_format_uses_the_configured_site_setting(): void
    {
        SystemSetting::ensure('display.date_format', 'd-M-Y', 'string', 'Date Display Format');

        $this->assertSame('23-Sep-2026', $this->service->format('2026-09-23'));
    }

    public function test_format_falls_back_to_default_when_setting_missing(): void
    {
        SystemSetting::where('key', 'display.date_format')->delete();
        \Illuminate\Support\Facades\Cache::forget('setting.display.date_format');

        $this->assertSame('23-Sep-2026', $this->service->format('2026-09-23'));
    }

    public function test_format_respects_a_changed_site_setting(): void
    {
        SystemSetting::ensure('display.date_format', 'd-M-Y', 'string', 'Date Display Format');
        SystemSetting::set('display.date_format', 'Y-m-d');

        $this->assertSame('2026-09-23', $this->service->format('2026-09-23'));
    }

    public function test_format_returns_fallback_for_empty_input(): void
    {
        $this->assertSame('N/A', $this->service->format(null));
        $this->assertSame('N/A', $this->service->format(''));
        $this->assertSame('—', $this->service->format(null, '—'));
    }

    public function test_format_returns_fallback_for_unparseable_input(): void
    {
        $this->assertSame('N/A', $this->service->format('not-a-real-date-string-at-all'));
    }

    public function test_format_accepts_a_carbon_instance(): void
    {
        SystemSetting::ensure('display.date_format', 'd-M-Y', 'string', 'Date Display Format');

        $this->assertSame('23-Sep-2026', $this->service->format(\Carbon\Carbon::parse('2026-09-23')));
    }
}
