<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;

class ReportingHierarchyCrudController extends CrudController
{
    use ReorderOperation;

    public function setup()
    {
        $this->crud->setModel(\App\Models\Core\ReportingHierarchy::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/reporting-hierarchy');
        $this->crud->setEntityNameStrings('reporting hierarchy', 'reporting hierarchies');
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns(['topic', 'user.name', 'supervisor.name', 'combo_json']);
    }

    protected function setupReorderOperation()
    {
        $this->crud->set('reorder.label', 'user.name');
        $this->crud->set('reorder.max_level', 0); // Unlimited
    }
}
