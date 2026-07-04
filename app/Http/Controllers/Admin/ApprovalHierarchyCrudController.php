<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;

class ApprovalHierarchyCrudController extends CrudController
{
    public function setup()
    {
        $this->crud->setModel(\App\Models\Core\ApprovalHierarchy::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/approval-hierarchy');
        $this->crud->setEntityNameStrings('approval hierarchy', 'approval hierarchies');
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns(['topic', 'approver.name', 'level', 'combo_json', 'powers_json']);
    }
}
