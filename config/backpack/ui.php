<?php

return [

    'view_namespace' => 'backpack.theme-tabler::',
    'view_namespace_fallback' => 'backpack.theme-tabler::',

    // Aligned to the site-wide dd-MMM-YYYY date standard (see
    // .ai/rules/conventions.md section 13 and App\Services\DateFormatService)
    // - isoFormat() uses moment.js-style tokens, hence DD-MMM-YYYY here
    // rather than the PHP/Carbon d-M-Y token used by DateFormatService.
    'default_date_format' => 'DD-MMM-YYYY',
    'default_datetime_format' => 'DD-MMM-YYYY, HH:mm',

    'html_direction' => 'ltr',

    'project_name' => 'Xceler8 DMS',

    'meta_robots_content' => 'noindex, nofollow',

    'home_link' => '',

    'project_logo' => '<img src="/images/Logo-108x75.png" alt="Xceler8" style="height:30px;">',

    'breadcrumbs' => true,

    'developer_name' => 'Bikaner Motors',

    'developer_link' => 'https://www.bikanermotors.com/',

    'show_powered_by' => true,

    'show_getting_started' => env('APP_ENV') == 'local',

    'styles' => [

    ],

    'mix_styles' => [
    ],

    'vite_styles' => [
    ],

    'scripts' => [

    ],

    'mix_scripts' => [
    ],

    'vite_scripts' => [
    ],

    'classes' => [

        'table' => null,

        'tableWrapper' => null,
    ],

];
