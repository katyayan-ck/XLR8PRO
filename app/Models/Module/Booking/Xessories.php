<?php



namespace App\Models\Module\Booking;

use App\Models\BaseModel;


use Illuminate\Database\Eloquent\SoftDeletes;

use DataTables, Auth;



class Xessories extends BaseModel

{

    /**

     * The database table used by the model.

     *

     * @var string

     */

    use SoftDeletes;

    protected $table = 'xlr8_booking_accessories';
    protected $transformations = [
        'segment' => 'strtoupper',
        'model' => 'strtoupper',
        'variant' => 'strtoupper',
        'item' => 'strtoupper',
        'part_no' => 'strtoupper',

        // 'col2' => function ($value) { // Custom transformation to trim and replace spaces
        //     return str_replace(' ', '_', trim($value));
        // },
    ];

    protected $validations = [
        //'email' => ['regex:/^.+@.+\..+$/'], // Ensure email matches a simple pattern
    ];


    /**

     * The attributes to be fillable from the model.

     *

     * A dirty hack to allow fields to be fillable by calling empty fillable array

     *

     * @var array

     */



    protected $fillable = [];

    protected $guarded = ['id'];

    /**

     * The attributes excluded from the model's JSON form.

     *

     * @var array

     */

    // public function state()

    // 	{

    // 		return $this->belongsTo('App\Models\States','state_id');

    // 	}



    // public function users()

    // 	{

    // 		return $this->hasMany('App\Models\BranchUser','branch_id');

    // 	}


    public function itemName()
    {
        return $this->belongsTo('App\Models\XessoriesItems', 'item_id')->select('id', 'name');
    }
}
