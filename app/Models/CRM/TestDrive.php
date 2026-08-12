<?php

namespace App\Models\CRM;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestDrive extends Model
{
    use CrudTrait;
    use SoftDeletes;

    protected $table = 'xlr8_crm_testdrive';
    protected $guarded = ['id'];

    // Relationship to Enquiry
    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_no', 'enquiry_no');
    }
}