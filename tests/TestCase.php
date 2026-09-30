<?php

namespace Tests;

use App\Models\Utilities\Settings\SystemSetting;
use App\Services\OrgService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The per-request memos (W7) are static: a web request or queue job starts fresh, but tests share one PHP process,
     * so each test starts with them empty — otherwise a value set inside a rolled-back test would leak into the next.
     */
    protected function setUp(): void
    {
        parent::setUp();
        OrgService::flushMemo();
        SystemSetting::flushMemo();
    }
}
