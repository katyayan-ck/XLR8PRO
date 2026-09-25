<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ImportController extends Controller
{
    // Admin Import Page
    public function admin()
    {
        return view('admin.import.admin');
    }

    // Sales Import Page
    public function sales()
    {
        return view('admin.import.sales');
    }

    // Service Import Page
    public function service()
    {
        return view('admin.import.service');
    }

    // Spares Import Page
    public function spares()
    {
        return view('admin.import.spare');
    }
}
