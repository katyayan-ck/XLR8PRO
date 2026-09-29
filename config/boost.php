<?php

/*
|--------------------------------------------------------------------------
| Laravel Boost — project overrides (DEC-086)
|--------------------------------------------------------------------------
| Only the keys set here override the package defaults (vendor/laravel/boost/config/boost.php).
|
| The generic Boost / Laravel guideline sections are excluded from CLAUDE.md / AGENTS.md, which every agent session
| loads, and replaced by the compact project version in .ai/guidelines/30-laravel-tools.md (about 8 KB fewer tokens
| per session). "deployments" is Laravel Cloud guidance; this app is not deployed there.
*/

return [

    'guidelines' => [
        'exclude' => ['boost', 'foundation', 'laravel/core', 'laravel/v12', 'deployments'],
    ],

    // Skills this project does not use (every installed skill's description is loaded in each session): the admin is
    // Backpack / Tabler, not Tailwind; deployment is not on Laravel Cloud.
    'skills' => [
        'exclude' => ['tailwindcss-development', 'deploying-to-cloud'],
    ],

];
