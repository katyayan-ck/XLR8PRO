<?php

namespace App\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;

class SpareImportController extends Controller
{

    // Spares Import Page
    public function spares()
    {
        return view('admin.import.spare');
    }
}
