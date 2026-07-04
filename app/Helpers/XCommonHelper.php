<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\EnumCols;
use App\Models\EnumMaster;

//User System Models
use App\User;
use App\Models\X_Designation;
use App\Models\X_Department;
use App\Models\X_Division;
use App\Models\X_Branch;
use App\Models\X_Location;
use App\Models\X_Segment;
use App\Models\X_CustomModel;
use App\Models\X_Vertical;
use App\Models\PinCodes;
use App\Models\XlFeeCollection;
use App\Models\XlSpareClosure;
use Spatie\Permission\Models\IAM\Role;
use Spatie\Permission\Models\IAM\Permission;
use Illuminate\Support\Facades\Log;



/**

 * Class XCommonHelper

 * A helper class providing various static methods to facilitate operations

 * related to EnumCols and EnumMaster models.

 * It includes methods for retrieving, creating, and handling enumerations.

 */

class XCommonHelper

{

    /**
     * Get enum ID by keyword and value, create if $new=true and not exists.
     */
    public static function getEnumIdNew(string $keyword, string $value, bool $new = false): ?int
    {
        $col = EnumCols::where('keyword', $keyword)->first();
        if (!$col) {
            if (!$new) return null;
            $col = new EnumCols();
            $col->keyword = $keyword;
            $col->name = ucwords(str_replace('-', ' ', $keyword));
            $col->status = 1;
            $col->created_by = 1;
            $col->save();
        }
        $enum = EnumMaster::where('master_id', $col->id)
            ->where('value', $value)
            ->whereNull('deleted_at')
            ->first();
        if (!$enum && $new) {
            $enum = new EnumMaster();
            $enum->master_id = $col->id;
            $enum->value = $value;
            $enum->status = 1;
            $enum->created_by = 1;
            $enum->save();
        }
        return $enum ? $enum->id : null;
    }

    /**
     * Get all enum ids for a keyword (optionally active only).
     */
    public static function getAllEnumIds(string $keyword, bool $onlyActive = true): array
    {
        $col = EnumCols::where('keyword', $keyword)->first();
        if (!$col) return [];

        $query = EnumMaster::where('master_id', $col->id)->whereNull('parent_id');
        if ($onlyActive) {
            $query->where('status', 1)->whereNull('deleted_at');
        }
        return $query->pluck('id')->toArray();
    }

    public static function checkOtfNo($otf_no, $excludeId = null)
    {
        $otf_no = trim((string) $otf_no);
        Log::info('Checking OTF number: ' . $otf_no . ' (Type: ' . gettype($otf_no) . ', Length: ' . strlen($otf_no) . ', Exclude ID: ' . ($excludeId ?? 'None'));
        $query = XlFeeCollection::where('otf_no', $otf_no);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $record = $query->first();
        Log::info('Record found: ' . ($record ? json_encode($record->toArray()) : 'None'));
        return $record ? 1 : 0;
    }
    public static function checkInvoiceNo($invoice_no, $excludeId = null)
    {
        $invoice_no = trim((string) $invoice_no);
        Log::info('Checking Invoice number: ' . $invoice_no . ' (Type: ' . gettype($invoice_no) . ', Length: ' . strlen($invoice_no) . ', Exclude ID: ' . ($excludeId ?? 'None'));
        $query = XlFeeCollection::where('inv_no', $invoice_no);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $record = $query->first();
        Log::info('Record found: ' . ($record ? json_encode($record->toArray()) : 'None'));
        return $record ? 1 : 0;
    }
    public static function checkChassisNo($chassis_no, $excludeId = null)
    {
        $chassis_no = trim((string) $chassis_no);
        Log::info('Checking Chassis number: ' . $chassis_no . ' (Type: ' . gettype($chassis_no) . ', Length: ' . strlen($chassis_no) . ', Exclude ID: ' . ($excludeId ?? 'None'));
        $query = XlFeeCollection::where('chassis_no', $chassis_no);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $record = $query->first();
        Log::info('Record found: ' . ($record ? json_encode($record->toArray()) : 'None'));
        return $record ? 1 : 0;
    }



    public static function checkRoNumber($ro_no)
    {
        $ro_no = trim((string) $ro_no); // Ensure string and remove whitespace
        \Log::info('Checking RO number: ' . $ro_no . ' (Type: ' . gettype($ro_no) . ', Length: ' . strlen($ro_no) . ')');
        $record = XlSpareClosure::where('ro_no', $ro_no)->first();
        \Log::info('Record found: ' . ($record ? json_encode($record->toArray()) : 'None'));
        return $record ? 1 : 0;
    }

    public static function getCurrentTimezone()
    {
        return config('app.timezone');
    }

    public static function formatDate($date)
    {
        return Carbon::parse($date)->timezone(self::getCurrentTimezone())->format('Y-m-d H:i:s');
    }

    public static function setUserLocale($locale)
    {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }

    public static function getLocationsByState($state_id)
    {
        // Fetch locations where parent is equal to the provided state_id
        return PinCodes::where('parent', $state_id)->get(['id', 'name']);
    }
    public static function createRole($name, $permissions = [])
    {
        $role = Role::create(['name' => $name]);
        if (count($permissions) > 0) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }
        return $role;
    }

    public static function createPermission($name, $guard_name = 'web')
    {
        return Permission::create(['name' => $name, 'guard_name' => $guard_name]);
    }

    public static function updateUserRoles($user_id, $roles)
    {
        $user = User::find($user_id);
        $user->syncRoles($roles);
    }

    public static function getRolesPermissions($role)
    {
        $permissions = $role->permissions;
        $data = array();
        foreach ($permissions as $permission) {
            $data[] = $permission->name;
        }
        return $data;
    }

    public static function syncRolesPermissions($role, $permissions)
    {
        $role->syncPermissions($permissions);
    }

    public static function updateUserPermissions($user_id, $permissions)
    {
        $user = User::find($user_id);
        $user->syncPermissions($permissions);
    }

    public static function desigsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array(0 => ["name" => "ALL"]);
        $data = array();
        $desigs = self::getDesignations();
        foreach ($ids as $id) {
            if (isset($desigs[$id]))
                $data[$id] = $desigs[$id];
            else
                $data[$id] = ["name" => "Unknown Designation"];
        }
        return $data;
    }

    public static function departsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array(0 => ["name" => "ALL"]);
        $data = array();
        $departs = self::getDepartments();
        foreach ($ids as $id) {
            if (isset($departs[$id]))
                $data[$id] = $departs[$id];
            else
                $data[$id] = ["name" => "Unknown Department"]; //"Unknown Department";
        }
        return $data;
    }

    public static function divisId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array(0 => "ALL");
        $data = array();
        $divis = self::getDivisions();
        foreach ($ids as $id) {
            if (isset($divis[$id]))
                $data[$id] = $divis[$id]['name'];
            else
                $data[$id] = "Unknown Division";
        }
        return $data;
    }

    public static function segmentsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array(0 => "ALL");
        $data = array();
        $segs = self::getSegments();
        foreach ($ids as $id) {
            if (isset($segs[$id]))
                $data[$id] = $segs[$id];
            else
                $data[$id] = "Unknown Segment";
        }
        return $data;
    }

    public static function cmodelsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array(0 => "ALL");
        $data = array();
        $cms = self::getCustomModels();
        foreach ($ids as $id) {
            if (isset($cms[$id]))
                $data[$id] = $cms[$id]['name'];
            else
                $data[$id] = "Unknown Custom Model";
        }
        return $data;
    }

    public static function verticalsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return array("ALL");
        $data = array();
        $verticals = self::getVerticals();
        foreach ($ids as $id) {
            if (isset($verticals[$id]))
                $data[$id] = $verticals[$id];
            else
                $data[$id] = "Unknown Vertical";
        }
        return $data;
    }

    public static function branchesId2Names($ids)
    {
        //print_r("<br>\nReceived Ids :");
        //print_r($ids);
        //print_r("<br>\n ID Count :" . count($ids) . " and value : " . $ids[0]);
        if (count($ids) == 1 && $ids[0] != 0)
            return array(0 => ['name' => "All"]);
        $data = array();
        $branches = self::getBranches();
        foreach ($ids as $id) {
            if (isset($branches[$id]))
                $data[$id] = $branches[$id];
            else
                $data[$id] = ['name' => "Unknown"];
        }
        return $data;
    }

    public static function locsId2Names($ids)
    {
        if (count($ids) == 1 && empty($ids[0]))
            return [0 =>  "ALL"];
        $data = array();
        $locs = self::getLocations();
        foreach ($ids as $id) {
            if (isset($locs[$id]))
                $data[$id] = $locs[$id]['name'];
            else
                $data[$id] = array(999999 => "Unknown");
        }

        return $data;
    }

    public static function getDepartments()
    {

        $data = array();
        $tmp = X_Department::select('id', 'name', 'abbr')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'code' => $arr['abbr']);
        }
        return $data;
    }

    public static function getDivisions($dept = false)
    {
        $depts = self::getDepartments();
        $data = array();
        if ($dept)
            $tmp = X_Division::select('id', 'name', 'dept_id', 'abbr')->where('dept_id', $dept)->where('status', 1)->get()->toArray();
        else
            $tmp = X_Division::select('id', 'name', 'dept_id', 'abbr')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'dept_id' => $arr['dept_id'], 'department' => $depts[$arr['dept_id']]['name'], 'code' => $arr['abbr']);
        }
        return $data;
    }

    public static function getBranches()
    {
        $data = array();
        $tmp = X_Branch::select('id', 'name', 'abbr')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'code' => $arr['abbr']);
        }
        return $data;
    }

    public static function getLocations($branch = false)
    {
        $branches = self::getBranches();
        $data = array();
        if ($branch)
            $tmp = X_Location::select('id', 'name', 'branch_id', 'abbr', 'demibranch')->where('branch_id', $branch)->where('status', 1)->get()->toArray();
        else
            $tmp = X_Location::select('id', 'name', 'branch_id', 'abbr', 'demibranch')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'branch_id' => $arr['branch_id'], 'branch' => $branches[$arr['branch_id']]['name'], 'code' => $arr['abbr'], 'demibranch' => $arr['demibranch']);
        }
        return $data;
    }

    public static function getSpareWH()
    {
        $tmp = X_Location::select('id', 'name')->where('spare_warehouse', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name']);
        }
        return $data;
    }
    public static function getSpareConsumption()
    {
        $tmp = X_Location::select('id', 'name')->where('spare_consumption', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name']);
        }
        return $data;
    }
    public static function getSpareStore()
    {
        $tmp = X_Location::select('id', 'name')->where('spare_store', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name']);
        }
        return $data;
    }

    public static function getServiceBranch()
    {
        $data = [];
        $tmp = X_Location::select('id', 'name')->where('service_branch', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = ['id' => $arr['id'], 'name' => $arr['name']];
        }
        return $data;
    }

    public static function getSegments()
    {
        $data = array();
        $tmp = X_Segment::select('id', 'name')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = $arr['name'];
        }
        return $data;
    }

    public static function getDesignations()
    {
        $data = array();
        $tmp = X_Designation::select('id', 'name', 'abbr')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'code' => $arr['abbr']);
        }
        return $data;
    }

    public static function getVerticals()
    {
        $data = array();
        $tmp = X_Vertical::select('id', 'name')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = $arr['name'];
        }
        return $data;
    }

    public static function getCustomModels($seg = false)
    {
        $segments = self::getSegments();
        $data = array();
        if ($seg)
            $tmp = X_CustomModel::select('id', 'name', 'segment_id')->where('segment_id', $seg)->where('status', 1)->get()->toArray();
        else
            $tmp = X_CustomModel::select('id', 'name', 'segment_id')->where('status', 1)->get()->toArray();
        foreach ($tmp as $arr) {
            $data[$arr['id']] = array('id' => $arr['id'], 'name' => $arr['name'], 'segment_id' => $arr['segment_id'], 'segment' => $segments[$arr['segment_id']]);
        }
        return $data;
    }

    public static function getDivByDept($ids)
    {
        $divs = [];
        $depts = self::getDepartments();
        foreach ($ids as $dept) {
            $tds = X_Division::select('id', 'name', 'dept_id', 'abbr')->where('dept_id', $dept)->where('status', 1)->get()->toArray();
            foreach ($tds as $td) {
                //print_r($td);
                $divs[$td['id']] = array(
                    'id' => $td['id'],
                    'name' => $td['name'],
                    'dept_id' => $td['dept_id'],
                    'code' => $td['abbr'],
                    'department' => $depts[$td['dept_id']]
                );
            }
        }
        return $divs;
    }

    public static function getLocationsByBranch($ids)
    {
        $locs = [];
        $branches = self::getBranches();
        foreach ($ids as $br) {
            $tls = X_Location::select('id', 'name', 'branch_id', 'abbr')->where('branch_id', $br)->where('status', 1)->get()->toArray();

            // print_r($tls);
            foreach ($tls as $tl) {
                //print_r($tl['name']);
                $locs[$tl['id']] = array(
                    'id' => $tl['id'],
                    'name' => $tl['name'],
                    'branch_id' => $tl['branch_id'],
                    'code' => $tl['abbr'],
                    'branch' => $branches[$tl['branch_id']]
                );
            }
        }
        //print_r($locs);
        return $locs;
    }

    public static function getCmodelBySegment($ids)
    {
        $cmodels = [];
        $segments = self::getSegments();
        foreach ($ids as $seg) {
            $tcs = X_CustomModel::select('id', 'name', 'segment_id')->where('segment_id', $seg)->where('status', 1)->get()->toArray();
            foreach ($tcs as $tc) {
                $cmodels[$tc['id']] = array('id' => $tc['id'], 'name' =>  $tc['name'], 'segment_id' => $tc['segment_id'], 'segment' => $segments[$tc['segment_id']]);
            }
        }
        return $cmodels;
    }




    /**
     * Retrieves the column ID based on the given column name. If the column does not exist and
     * the $new parameter is true, a new column will be created.
     *
     * @param string $col_name The name of the column to retrieve.
     * @param bool $new Indicates whether to create a new column if it doesn't exist.
     * @return int|false Returns the column ID or false if not found and $new is false.
     */
    public static function getColId($col_name, $new = false)
    {
        $enum_cols = EnumCols::where('keyword', $col_name)->first();
        if ($enum_cols) {
            return $enum_cols->id;
        } else {
            if ($new == true) {
                $tr = new EnumCols();
                $tr->name = $col_name;
                $tr->save();
                return $tr->id;
            }
            return false;
        }
    }

    public static function getIdByKwVal($kw, $val)
    {
        $kwi = self::getColId($kw);
        if ($kwi) {
            $vr = EnumMaster::where("master_id", $kwi)->where('value', $val)->first();
            if ($vr)
                return $vr->id;
            else
                return false;
        }
        return false;
    }


    /**
     * Retrieves the enumeration ID based on the column ID and value. If the enumeration does not
     * exist and the $new parameter is true, a new enumeration will be created.
     *
     * @param int $col_id The ID of the column associated with the enumeration.
     * @param mixed $value The value of the enumeration to retrieve.
     * @param bool $new Indicates whether to create a new enumeration if it doesn't exist.
     * @return int|false Returns the enumeration ID or false if not found and $new is false.
     */
    public static function getEnumId($col_id, $value, $new = false)
    {
        $enum = EnumMaster::where('value', $value)->where('master_id', $col_id)->first();
        if ($enum) {
            return $enum->id;
        } else {
            if ($new == true) {
                $tr = new EnumMaster();
                $tr->value = $value;
                $tr->master_id = $col_id;
                $tr->save();
                return $tr->id;
            }
            return false;
        }
    }

    /**
     * Retrieves the enumeration ID based on the column ID, value, and parent ID recursively.
     * If the enumeration does not exist and the $new parameter is true, a new enumeration will be created.
     *
     * @param int $col_id The ID of the column associated with the enumeration.
     * @param mixed $value The value of the enumeration to retrieve.
     * @param int $pid The parent ID for recursive retrieval.
     * @param bool $new Indicates whether to create a new enumeration if it doesn't exist.
     * @return int|false Returns the enumeration ID or false if not found and $new is false.
     */
    public static function getEnumIdRecursive($col_id, $value, $pid = 0, $new = false)
    {
        $enum = EnumMaster::where('value', $value)->where('parent_id', $pid)->where('recursion_level', $rlevel)->where('master_id', $col_id)->first();
        if ($enum) {
            return $enum->id;
        } else {
            $pr = EnumMaster::find($pid);
            if ($pr) {
                if ($new == true) {
                    $tr = new EnumMaster();
                    $tr->value = $value;
                    $tr->master_id = $col_id;
                    $tr->parent_id = $pid;
                    $tr->recursion_level = $pr->recursion_level + 1;
                    $tr->save();
                    return $tr->id;
                }
            }
            return false;
        }
    }

    /**
     * Retrieves the value of an enumeration based on its ID.
     *
     * @param int $id The ID of the enumeration to retrieve.
     * @return mixed|false Returns the value of the enumeration or false if not found.
     */
    public static function enumValById($id)
    {
        $enum = EnumMaster::find($id);
        if ($enum) {
            return $enum->value;
        } else {
            return false;
        }
    }


    /**
     * Retrieves the enumeration data as an array based on its ID.
     *
     * @param int $id The ID of the enumeration to retrieve.
     * @return array|false Returns the enumeration data as an array or false if not found.
     */
    public static function dataById($id)
    {
        $enum = EnumMaster::find($id);
        if ($enum) {
            return $enum->toArray();
        } else {
            return false;
        }
    }


    /**
     * Retrieves all enumeration values associated with a given keyword.
     *
     * @param string $kw The keyword to search for in the enumeration columns.
     * @return array|false Returns an array of enumeration values indexed by their IDs or false if not found.
     */
    public static function valsByKW($kw)
    {
        $mid = EnumCols::where('name', $kw)->first()->id;
        if ($mid) {
            $enums = EnumMaster::where('master_id', $mid)->get();
            $data = [];
            foreach ($enums as $enum) {
                $data[$enum->id] = $enum->value;
            }
            return $data;
        } else
            return false;
    }
}
