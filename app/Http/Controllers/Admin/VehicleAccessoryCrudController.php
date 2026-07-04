<?php 

// app/Http/Controllers/Admin/VehicleAccessoryCrudController.php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Vehicle\Accessory;
use App\Models\ImportLog;
use App\Models\ExportLog;
use App\Imports\VehicleAccessoriesImport;
use App\Exports\VehicleAccessoriesExport;
use App\Services\Vehicle\AccessoryImportService;
use App\Services\Vehicle\AccessoryExportService;

class VehicleAccessoryCrudController extends CrudController
{
    public function setup(): void
    {
        CRUD::setModel(Accessory::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/vehicle-accessory');
        CRUD::setEntityNameStrings('vehicle accessory', 'vehicle accessories');
    }

    protected function setupListOperation(): void
    {
        $this->crud->setListView('admin.vehicle-accessory.list');

        CRUD::column('part_no')->label('Part No');
        CRUD::column('item')->label('Item');
        CRUD::column('display_name')->label('Display');
        CRUD::column('ndp')->label('NDP');
        CRUD::column('mrp')->label('MRP');
        CRUD::column('status')->label('Active');

        $this->crud->addButtonFromView('top', 'vehicle_accessory_import', 'vehicle_accessory_import', 'beginning');
        $this->crud->addButtonFromView('top', 'vehicle_accessory_export', 'vehicle_accessory_export', 'beginning');
    }

    public function showImportForm()
    {
        return view('admin.vehicle-accessory.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        Excel::import(
            new VehicleAccessoriesImport(app(AccessoryImportService::class), backpack_user()->id ?? 1),
            $request->file('file')
        );

        return redirect()->route('vehicle-accessory.index')->with('success', 'Accessories imported successfully.');
    }

    public function showExportForm()
    {
        return view('admin.vehicle-accessory.export');
    }

    public function export(Request $request)
    {
        $filename = 'vehicle_accessories_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

        return Excel::download(
            new VehicleAccessoriesExport(app(AccessoryExportService::class), true),
            $filename
        );
    }

    public function downloadTemplate()
    {
        return response()->download(storage_path('app/templates/vehicle_accessories_template.xlsx'));
    }

    public function importHistory()
    {
        $logs = ImportLog::where('importtype', 'custom')->latest('id')->paginate(20);
        return view('admin.vehicle-accessory.import-history', compact('logs'));
    }

    public function exportHistory()
    {
        $logs = ExportLog::where('exporttype', 'custom')->latest('id')->paginate(20);
        return view('admin.vehicle-accessory.export-history', compact('logs'));
    }
}