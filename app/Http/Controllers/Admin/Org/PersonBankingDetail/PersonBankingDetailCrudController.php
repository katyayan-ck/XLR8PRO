<?php

namespace App\Http\Controllers\Admin\Org\PersonBankingDetail;

use App\Http\Requests\PersonBankingDetailRequest;
use App\Models\Admin\Person;
use App\Models\Admin\PersonBankingDetail;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * NOTE: this controller's queries/validation reference `person_id`, `swift_code`,
 * and `is_primary`, and validate `account_type` against savings/current/fd/rd/other
 * — none of which match the real `xlr8_admin_person_banking_details` schema
 * (real columns: `person_code`, `micr_code`, no `is_primary` column at all, and
 * `account_type` is actually an enum of Primary/Secondary/Joint/Trust). This is a
 * pre-existing bug, preserved exactly — see known-bugs-report.md BUG-021.
 */
class PersonBankingDetailCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "person_banking_detail.*" permission exists — reuses
     * "person.*" since this is a sub-resource of Person.
     */
    public function setup()
    {
        CRUD::setModel(PersonBankingDetail::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/person-banking-detail');
        CRUD::setEntityNameStrings('person banking detail', 'person banking details');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('person.view')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        $this->crud->setListView('admin.person-banking-detail.list');
    }

    public function index()
    {
        if (! backpack_user()->can('person.view')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        $this->crud->setListView('admin.person-banking-detail.list');

        $bankings = PersonBankingDetail::with('person')
            ->select([
                'id',
                'person_id',
                'bank_name',
                'account_holder_name',
                'account_number',
                'ifsc_code',
                'account_type',
                'branch_name',
                'swift_code',
                'is_primary',
                'is_verified',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $bankings->map(function ($banking, $index) {
            $mapped = $banking->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_primary'] = $banking->is_primary;
            $mapped['is_verified'] = $banking->is_verified;
            $mapped['person_name'] = $banking->person
                ? $banking->person->first_name.' '.$banking->person->last_name
                : '—';

            $editUrl = backpack_url("person-banking-detail/{$banking->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.person-banking-detail.list', [
            'title' => 'All Person Banking Details',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',           'headerName' => 'S.No.'],
                    ['field' => 'person_name',         'headerName' => 'Person'],
                    ['field' => 'bank_name',           'headerName' => 'Bank Name'],
                    ['field' => 'account_holder_name', 'headerName' => 'Account Holder'],
                    ['field' => 'account_number',      'headerName' => 'Account Number'],
                    ['field' => 'ifsc_code',           'headerName' => 'IFSC Code'],
                    ['field' => 'branch_name',         'headerName' => 'Branch Name'],
                    ['field' => 'swift_code',          'headerName' => 'Swift Code'],
                    ['field' => 'account_type',        'headerName' => 'Account Type'],
                    ['field' => 'is_primary',          'headerName' => 'Primary'],
                    ['field' => 'is_verified',         'headerName' => 'Verified'],
                    ['field' => 'action',              'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('person.create')) {
            abort(403, 'Unauthorized. You do not have permission to create person banking details.');
        }

        $this->crud->setCreateView('admin.person-banking-detail.create');

        return view('admin.person-banking-detail.create', [
            'title' => 'Add New Banking Detail',
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(PersonBankingDetailRequest $request)
    {
        if (! backpack_user()->can('person.create')) {
            abort(403, 'Unauthorized. You do not have permission to create person banking details.');
        }

        $validated = $request->validated();

        PersonBankingDetail::create($validated);

        \Alert::success('Banking Detail created successfully!')->flash();

        return redirect(backpack_url('person-banking-detail'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('person.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit person banking details.');
        }

        $this->crud->setEditView('admin.person-banking-detail.edit');

        $banking = PersonBankingDetail::with('person')->findOrFail($id);

        return view('admin.person-banking-detail.edit', [
            'title' => 'Edit Banking Detail',
            'banking' => $banking,
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(PersonBankingDetailRequest $request, $id)
    {
        if (! backpack_user()->can('person.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit person banking details.');
        }

        $banking = PersonBankingDetail::findOrFail($id);

        $validated = $request->validated();

        $banking->update($validated);

        \Alert::success('Banking Detail updated successfully!')->flash();

        return redirect(backpack_url('person-banking-detail'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('person.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete person banking details.');
        }

        return $this->crud->delete($id);
    }
}
