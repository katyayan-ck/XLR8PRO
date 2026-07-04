<?php

namespace App\Http\Controllers\Admin;

use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;

class OrgDemoController extends CrudController
{
    public function index()
    {
        return view('vendor.backpack.ui.org-demo', [
            'branches'     => OrgService::branches(),
            'locations'    => OrgService::locations(),
            'departments'  => OrgService::departments(),
            'divisions'    => OrgService::divisions(),
            'verticals'    => OrgService::verticals(),
            'segments'     => OrgService::segments(),
            'subSegments'  => OrgService::subSegments(),
            'models'       => OrgService::models(),
            'variants'     => OrgService::variants(),

            // Filtered examples
            'churuLocations' => OrgService::locations('CHR'),
            'salesDivisions' => OrgService::divisions('SLS'),

            // User examples
            'usersByPost'    => OrgService::usersByPost('SLS_CNS_CHR_003', 'CHR'),
            'usersByDesig'   => OrgService::usersByDesignation('CNS', 'CHR'),
        ]);
    }
}