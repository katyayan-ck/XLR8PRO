<?php return array (
  'backpack/basset' => 
  array (
    'aliases' => 
    array (
      'Basset' => 'Backpack\\Basset\\Facades\\Basset',
    ),
    'providers' => 
    array (
      0 => 'Backpack\\Basset\\BassetServiceProvider',
    ),
  ),
  'backpack/crud' => 
  array (
    'aliases' => 
    array (
      'CRUD' => 'Backpack\\CRUD\\app\\Library\\CrudPanel\\CrudPanelFacade',
      'Widget' => 'Backpack\\CRUD\\app\\Library\\Widget',
    ),
    'providers' => 
    array (
      0 => 'Backpack\\CRUD\\BackpackServiceProvider',
    ),
  ),
  'backpack/generators' => 
  array (
    'providers' => 
    array (
      0 => 'Backpack\\Generators\\GeneratorsServiceProvider',
    ),
  ),
  'backpack/theme-tabler' => 
  array (
    'providers' => 
    array (
      0 => 'Backpack\\ThemeTabler\\AddonServiceProvider',
    ),
  ),
  'creativeorange/gravatar' => 
  array (
    'aliases' => 
    array (
      'Gravatar' => 'Creativeorange\\Gravatar\\Facades\\Gravatar',
    ),
    'providers' => 
    array (
      0 => 'Creativeorange\\Gravatar\\GravatarServiceProvider',
    ),
  ),
  'darkaonline/l5-swagger' => 
  array (
    'aliases' => 
    array (
      'L5Swagger' => 'L5Swagger\\L5SwaggerFacade',
    ),
    'providers' => 
    array (
      0 => 'L5Swagger\\L5SwaggerServiceProvider',
    ),
  ),
  'kalnoy/nestedset' => 
  array (
    'providers' => 
    array (
      0 => 'Kalnoy\\Nestedset\\NestedSetServiceProvider',
    ),
  ),
  'laravel/pail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Pail\\PailServiceProvider',
    ),
  ),
  'laravel/sail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sail\\SailServiceProvider',
    ),
  ),
  'laravel/sanctum' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'maatwebsite/excel' => 
  array (
    'aliases' => 
    array (
      'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
    ),
    'providers' => 
    array (
      0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/collision' => 
  array (
    'providers' => 
    array (
      0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'owen-it/laravel-auditing' => 
  array (
    'providers' => 
    array (
      0 => 'OwenIt\\Auditing\\AuditingServiceProvider',
    ),
  ),
  'prologue/alerts' => 
  array (
    'aliases' => 
    array (
      'Alert' => 'Prologue\\Alerts\\Facades\\Alert',
    ),
    'providers' => 
    array (
      0 => 'Prologue\\Alerts\\AlertsServiceProvider',
    ),
  ),
  'revolution/laravel-google-sheets' => 
  array (
    'providers' => 
    array (
      0 => 'Revolution\\Google\\Sheets\\Providers\\SheetsServiceProvider',
      1 => 'Revolution\\Google\\Client\\Providers\\GoogleServiceProvider',
    ),
    'google/apiclient-services' => 
    array (
      0 => 'Drive',
      1 => 'Sheets',
    ),
  ),
  'spatie/laravel-medialibrary' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider',
    ),
  ),
  'spatie/laravel-permission' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Permission\\PermissionServiceProvider',
    ),
  ),
  'spatie/laravel-translatable' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Translatable\\TranslatableServiceProvider',
    ),
  ),
);