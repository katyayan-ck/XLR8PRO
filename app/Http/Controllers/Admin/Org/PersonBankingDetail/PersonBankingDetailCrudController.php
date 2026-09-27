<?php

namespace App\Http\Controllers\Admin\Org\PersonBankingDetail;

use App\Models\Admin\PersonBankingDetail;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * List-only screen (DEC-037): banking details are created and edited on the Person screen through
 * Person\PersonBankingService; the old id-based create / edit forms were retired (BUG-021, DEC-070).
 */
class PersonBankingDetailCrudController extends CrudController
{
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }

    public function search()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        return $this->traitShowDetailsRow($id);
    }

    /**
     * No dedicated "ORG_PBNK_*" permission exists — reuses "ORG_PRSN_*"
     * since this is a sub-resource of Person.
     */
    public function setup()
    {
        CRUD::setModel(PersonBankingDetail::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/person-banking-detail');
        CRUD::setEntityNameStrings('person banking detail', 'person banking details');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        $this->crud->setListView('admin.org.person-banking-detail.list');
    }

    public function index()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person banking details.');
        }

        $this->crud->setListView('admin.org.person-banking-detail.list');

        $bankings = PersonBankingDetail::with('person')
            ->select([
                'id',
                'person_code',
                'bank_name',
                'account_holder_name',
                'account_number',
                'ifsc_code',
                'account_type',
                'branch_name',
                'is_verified',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $bankings->map(function ($banking, $index) {
            $mapped = $banking->toArray();
            $mapped['serial_no'] = $index + 1;
            // "Primary" is encoded in account_type (see PersonBankingDetail::makePrimary()).
            $mapped['is_primary'] = $banking->account_type === 'Primary';
            $mapped['is_verified'] = $banking->is_verified;
            $mapped['person_name'] = $banking->person
                ? $banking->person->first_name.' '.$banking->person->last_name
                : '—';

            // Standalone create/edit is retired (DEC-037, BUG-154): edit on the Person screen,
            // whose inline banking editing works.
            $mapped['action'] = $banking->person
                ? '<div class="d-flex gap-2 justify-content-center"><a href="'.backpack_url("org/person/{$banking->person->id}/edit")
                    .'" class="btn btn-sm btn-outline-primary py-1 px-2" title="Open person">Open person</a></div>'
                : '';

            return $mapped;
        })->values();

        return view('admin.org.person-banking-detail.list', [
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
                    ['field' => 'account_type',        'headerName' => 'Account Type'],
                    ['field' => 'is_primary',          'headerName' => 'Primary'],
                    ['field' => 'is_verified',         'headerName' => 'Verified'],
                    ['field' => 'action',              'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('ORG_PRSN_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete person banking details.');
        }

        return $this->crud->delete($id);
    }
}
