<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Person;
use App\Models\Admin\PersonBankingDetail;

class PersonBankingDetailCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(PersonBankingDetail::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/person-banking-detail');
        CRUD::setEntityNameStrings('person banking detail', 'person banking details');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.person-banking-detail.list');
    }

    public function index()
    {
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
                'is_verified'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $bankings->map(function ($banking, $index) {
            $mapped = $banking->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_primary'] = $banking->is_primary;
            $mapped['is_verified'] = $banking->is_verified;
            $mapped['person_name'] = $banking->person
                ? $banking->person->first_name . ' ' . $banking->person->last_name
                : '—';

            $editUrl = backpack_url("person-banking-detail/{$banking->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.person-banking-detail.list', [
            'title' => 'All Person Banking Details',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',           'headerName' => 'S.No'],
                    ['field' => 'person_name',         'headerName' => 'Person'],
                    ['field' => 'bank_name',           'headerName' => 'Bank Name'],
                    ['field' => 'account_holder_name', 'headerName' => 'Account Holder'],
                    ['field' => 'account_number',      'headerName' => 'Account Number'],
                    ['field' => 'ifsc_code',           'headerName' => 'IFSC Code'],
                    ['field' => 'branch_name',         'headerName' => 'Branch Name'],     // ← Added
                    ['field' => 'swift_code',          'headerName' => 'Swift Code'],      // ← Added
                    ['field' => 'account_type',        'headerName' => 'Account Type'],
                    ['field' => 'is_primary',          'headerName' => 'Primary'],
                    ['field' => 'is_verified',         'headerName' => 'Verified'],
                    ['field' => 'action',              'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.person-banking-detail.create');

        return view('admin.person-banking-detail.create', [
            'title'   => 'Add New Banking Detail',
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id'           => 'required|exists:xlr8_admin_person,id',
            'bank_name'           => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255|regex:/^[a-zA-Z\s.]+$/u', // Letters, space, dot only
            'account_number'      => 'required|numeric|digits_between:8,20',           // Only numbers, 8-20 digits
            'ifsc_code'           => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/', // Strict IFSC format
            'account_type'        => 'required|in:savings,current,fd,rd,other',
            'branch_name'         => 'nullable|string|max:255',
            'swift_code'          => 'nullable|string|max:50',
            'is_primary'          => 'boolean',
            'is_verified'         => 'boolean',
        ]);

        PersonBankingDetail::create($validated);

        \Alert::success('Banking Detail created successfully!')->flash();
        return redirect(backpack_url('person-banking-detail'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.person-banking-detail.edit');

        $banking = PersonBankingDetail::with('person')->findOrFail($id);

        return view('admin.person-banking-detail.edit', [
            'title'   => 'Edit Banking Detail',
            'banking' => $banking,
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $banking = PersonBankingDetail::findOrFail($id);

        $validated = $request->validate([
            'person_id'           => 'required|exists:xlr8_admin_person,id',
            'bank_name'           => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255|regex:/^[a-zA-Z\s.]+$/u',
            'account_number'      => 'required|numeric|digits_between:8,20',
            'ifsc_code'           => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'account_type'        => 'required|in:savings,current,fd,rd,other',
            'branch_name'         => 'nullable|string|max:255',
            'swift_code'          => 'nullable|string|max:50',
            'is_primary'          => 'boolean',
            'is_verified'         => 'boolean',
        ]);

        $banking->update($validated);

        \Alert::success('Banking Detail updated successfully!')->flash();
        return redirect(backpack_url('person-banking-detail'));
    }
}
