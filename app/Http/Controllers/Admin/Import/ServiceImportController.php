<?php

namespace App\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;

class ServiceImportController extends Controller
{
    // Service Import Page
    public function service()
    {
        return view('admin.import.service');
    }
}
