# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/Admin/** | .ai/rules/admin.md |
| app/Http/Controllers/Api/** | .ai/rules/api.md |
| app/**, routes/**, config/**, routes/api.php | .ai/rules/architecture.md |
| app/**, routes/**, resources/** | .ai/rules/conventions.md |
| app/Models/**, database/**, app/Services/** | .ai/rules/database.md |
| app/Imports/** | .ai/rules/imports.md |
| app/Models/Admin/Person*, app/Models/Admin/Employee*, app/Models/User.php, app/Services/PersonService.php, app/Http/Controllers/**/Person*, app/Http/Controllers/**/Employee* | .ai/rules/person-user.md |
| app/Services/RBACService.php, app/Services/OrgScopeService.php, app/Services/DataScopeFilter.php, app/Http/Middleware/**, app/Models/IAM/** | .ai/rules/rbac-scopes.md |
| app/Services/**, app/Http/Controllers/**, app/Jobs/** | .ai/rules/services.md |
| app/Services/Vehicle/**, app/Models/Vehicle/**, app/Http/Controllers/Admin/Vehicle/**, app/Http/Controllers/Api/V1/Vehicle/** | .ai/rules/vehicle-pricing.md |
