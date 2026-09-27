<?php

use App\Providers\AppServiceProvider;
use App\Providers\KeywordValueServiceProvider;
use App\Providers\PlatformServiceProvider;
use App\Providers\SystemSettingServiceProvider;

return [
    AppServiceProvider::class,
    KeywordValueServiceProvider::class,
    SystemSettingServiceProvider::class,
    PlatformServiceProvider::class,
];
