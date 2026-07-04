<?php

namespace App\Helpers;


use App\User;
use App\Models\Module\Booking\Xessories;
use Auth;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Support\Facades\DB;
use App\Models\Vehicle\Variant;
use App\Models\Module\Booking\Bookingamount;

use App\Models\Person;

use App\Models\XVehicleMaster;

use App\Models\XSnapShot;

use App\Models\XessoriesItems;

use App\Models\XInsAddons;

use App\Models\XInsExtra;

use App\Models\XInsIRDA;

use App\Models\XRtoData;

use App\Models\XCsd;

use App\Models\XShield;

use App\Models\XRSA;

use App\Models\XSchemeMaster;



use App\Models\XPriceHeads;

use App\Models\XCorp;

use App\Models\XchangeNloyalty;

use App\Models\XVehicleFeatures;

use App\Models\XVehicleSpecifications;

use App\Models\PriceSettings;

use App\Models\XReporting;

use App\Models\XApprovers;

use App\Models\Xl_Reporting;
use App\Models\Roles;
use App\Models\EnumCols;
use App\Models\EnumMaster;

use App\Models\VehicleMeta;



use XCommonHelper;



use Illuminate\Support\Str;

use Carbon\Carbon;



class XpricingHelper

{



    public static function getEnumId($kw, $val)
    {
        $kr = EnumCols::select('id', 'status')->where('keyword', strtoupper($kw))->first();
        if ($kr) {
            $vr = EnumMaster::where('master_id', $kr->id)->where(strtoupper('value'), strtoupper($val))->first();
            if ($vr)
                return $vr->id;
        }
        return false;
    }

    public static function selectGeneralManagers()
    {
        $designation_id = 22584;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectServiceManagers()
    {
        $designation_id = 22620;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectCXMs()
    {
        $designation_id = 22623;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectServiceAdvisors()
    {
        $designation_id = 22619;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectFloorControllers()
    {
        $designation_id = 22617;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectQualityControllers()
    {
        $designation_id = 22626;

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->whereRaw("find_in_set($designation_id, designation)")
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectTechnicians()
    {
        $designation_ids = [22621, 22622]; // Technician and Technician - Trainee

        $users = User::select('id', 'name', 'emp_code', 'mile_id', 'mobile', 'email', 'branch', 'location', 'segment', 'vertical', 'models', 'designation', 'department')
            ->where(function ($query) use ($designation_ids) {
                foreach ($designation_ids as $id) {
                    $query->orWhereRaw("find_in_set(?, designation)", [$id]);
                }
            })
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        return $users;
    }

    public static function selectfsc($withTrashed = false)
    {
        return DB::table('users')
            ->select(
                'id',
                'username',
                'employee_code',
                'person_code'
            )
            ->whereNull('deleted_at')
            ->orderBy('username', 'asc')
            ->get()
            ->map(function ($user) {
                return (array) $user;
            })
            ->toArray();
    }
    public static function selectUsers($withTrashed = false)
    {
        return DB::table('users')
            ->select(
                'id',
                'username',
                'employee_code',
                'person_code'
            )
            ->whereNull('deleted_at')
            ->orderBy('username', 'asc')
            ->get()
            ->toArray();
    }



    public static function getPricelist($seg)

    {

        //self::updatePriceList();

        $plshow = self::getPriceHoldStatus();

        $fMin = strtotime("2021-01-01");

        $fMax = strtotime("2024-11-30");

        $sgid = CommonHelper::getSegID($seg);

        $sar = array("PERSONAL", "COMMERCIAL", "LMM", "TAXI");

        $bar = array("BIKANER", "CHURU", "DELHI", "GURUGRAM");

        $lar = array("SUKHRALI", "EMMAR", "JASOLA", "KALYANI", "KOTA", "THANE", "MUMBAI", "NAGPUR", "PUNE", "RAJKOT", "SARDARSHAHAR");

        $mdata = XVehicleMaster::where('status', 1)->where('segment_id', $sgid)->orderBy('custom_model', 'asc')->orderBy('display_name', 'asc')->orderBy('vin', 'desc')->get();

        //dd($mdata->toArray());

        $vhdata = array();

        foreach ($mdata as $vdata) {

            if (!isset($vhdata[$vdata->display_name . "-" . $vdata->vin])) {

                $vh = array();

                $vh["id"] = $vdata->id;

                $vh["group"] = $vdata->custom_model;

                $vh["model"] = $vdata->display_name;

                $fVal = mt_rand($fMin, $fMax);



                $vh["year"] = date("Y-m-d", $fVal);

                if ($plshow) {

                    $vh["exshowroom"] = $sar[mt_rand(0, 3)];

                    $vh["incidental"] = $bar[mt_rand(0, 3)];

                    $vh["ftag"] = $lar[mt_rand(0, 10)];

                    $vh["trc"] = 0;

                    $vh["rto_tape"] = 0;

                    $vh["rsa"] = 0; //self::getRSA($vdata->display_name);

                    $vh["nildep"]  = 0; //self::getInsurance($vdata->id);

                    //fuel_id  ||  segment_id  ||  permit_id  ||  rto_tape  ||  accessories  ||  accessories_mnp  ||  tcs   invoice  ||  onroad  ||  stock

                    $vh["bhrto"] = $vh["rto"] = 0;

                    $vh["apack"] = 0;

                    $vh["rsa_disc"] = 0;

                    $vh["shield_disc"] = 0;

                    $vh["apack_disc"] = 0;

                    $vh["cash_disc"] = 0;

                    $vh["fame_subsidy"] = 0;

                    $vh["total_disc"] = 0;

                    $vh["tcs"] = 0;

                    $vh["onroad"] = 0;

                    $vh["invoice"] = 0;

                    $vh["exchange"] = array();

                    $vh["loyalty"] = array();

                    //$enl = ExtrasHelper::getXchange($vh["vhid"]);

                    // foreach ($enl as $tel) {

                    //     if ($tel['type'] == "Exchange")

                    //         $vh["exchange"][] = $tel;

                    //     if ($tel['type'] == "Loyalty")

                    //         $vh["loyalty"][] = $tel;

                    // }

                    $vh["corp"] =  array(); //ExtrasHelper::getCorporate($vdata->Vehicle->cm_id);

                    $vh["shield"] =  array(); //ExtrasHelper::getShield($vdata->Vehicle->cm_id);

                    $vh["cond_disc"] = true;
                } else {

                    $vh["exshowroom"] = $vdata->exshowroom;

                    $vh["incidental"] = $vdata->incidental_charges;

                    $vh["ftag"] = $vdata->fastag;

                    $vh["trc"] = $vdata->trc;

                    $vh["rto_tape"] = $vdata->rto_tape;

                    $vh["rsa"] = $vdata->rsa; //self::getRSA($vdata->display_name);

                    $vh["nildep"]  = $vdata->insurance; //self::getInsurance($vdata->id);

                    //fuel_id  ||  segment_id  ||  permit_id  ||  rto_tape  ||  accessories  ||  accessories_mnp  ||  tcs   invoice  ||  onroad  ||  stock

                    $vh["bhrto"] = $vh["rto"] = $vdata->rto;

                    $vh["apack"] = $vdata->accessories;

                    $vh["rsa_disc"] = $vdata->rsa_discount;

                    $vh["shield_disc"] = $vdata->shield_disc;

                    $vh["apack_disc"] = $vdata->accessories_discount;

                    $vh["cash_disc"] = $vdata->cash_discount;

                    $vh["fame_subsidy"] = $vdata->fame;

                    $vh["total_disc"] = $vdata->total_discount;

                    $vh["tcs"] = $vdata->tcs;

                    $vh["onroad"] = $vdata->onroad;

                    $vh["invoice"] = $vdata->invoice;

                    $vh["exchange"] = array();

                    $vh["loyalty"] = array();

                    //$enl = ExtrasHelper::getXchange($vh["vhid"]);

                    // foreach ($enl as $tel) {

                    //     if ($tel['type'] == "Exchange")

                    //         $vh["exchange"][] = $tel;

                    //     if ($tel['type'] == "Loyalty")

                    //         $vh["loyalty"][] = $tel;

                    // }

                    $vh["corp"] =  array(); //ExtrasHelper::getCorporate($vdata->Vehicle->cm_id);

                    $vh["shield"] =  array(); //ExtrasHelper::getShield($vdata->Vehicle->cm_id);

                    $vh["cond_disc"] = true;
                }



                $vhdata[$vdata->display_name] = $vh;
            }
        }

        //dd($vhdata[59]);

        // $data["data"] = $vhdata;

        return $vhdata;
    }



    public static function getPricelistCSD()

    {

        //self::updatePriceList();



        $plshow = self::getPriceHoldStatus();

        $mdata = XCsd::get();

        //dd($mdata->toArray());

        $vhdata = array();

        foreach ($mdata as $vdata) {

            $vhl = XVehicleMaster::find($vdata->vehicle_id);

            if (!isset($vhdata[$vhl->display_name])) {

                $vh = array();

                $vh["id"] = $vhl->id;

                $vh["group"] = $vhl->custom_model;

                $vh["model"] = $vhl->display_name;

                if ($plshow) {

                    $vh["invoice"] = 0;

                    $vh["dd"] = 0;

                    $vh["tcs"] = 0;

                    $vh["bl"] = " ";

                    $vh["incidental"] = 0;

                    $vh["ftag"] = 0;

                    $vh["trc"] = 0;

                    $vh["rsa"] = 0;

                    $vh["shield"] = 0; //self::getRSA($vdata->display_name);

                    $vh["nildep"]  = 0; //self::getInsurance($vdata->id);

                    //fuel_id  ||  segment_id  ||  permit_id  ||  rto_tape  ||  accessories  ||  accessories_mnp  ||  tcs   invoice  ||  onroad  ||  stock

                    $vh["rto"] = 0;

                    $vh["apack"] = 0;

                    $vh["c2d"] = 0;
                } else {

                    $vh["invoice"] = $vdata->invoice;

                    $vh["dd"] = $vdata->dd;

                    $vh["tcs"] = $vdata->tcs;

                    $vh["bl"] = " ";

                    $vh["incidental"] = $vdata->incidental_charges;

                    $vh["ftag"] = $vdata->fastag;

                    $vh["trc"] = $vdata->trc;

                    $vh["rsa"] = $vdata->rsa;

                    $vh["shield"] = $vdata->shield; //self::getRSA($vdata->display_name);

                    $vh["nildep"]  = $vdata->insurance; //self::getInsurance($vdata->id);

                    //fuel_id  ||  segment_id  ||  permit_id  ||  rto_tape  ||  accessories  ||  accessories_mnp  ||  tcs   invoice  ||  onroad  ||  stock

                    $vh["rto"] = $vdata->rto;

                    $vh["apack"] = $vdata->accessories;

                    $vh["c2d"] = $vdata->c2d;
                }

                $vhdata[$vhl->display_name] = $vh;
            }
        }

        //dd($vhdata[59]);

        // $data["data"] = $vhdata;

        return $vhdata;
    }



    public static function getModels($seg = "ALL")

    {

        $evn = CommonHelper::getOrCreateEnumId("ELECTRIC", "FUEL-TYPE");

        $ev = null;

        if ($seg == "ALL")

            $list = XVehicleMaster::where('status', 1)->get();

        else {

            $sl = strlen($seg);

            // print_r("<br>Segment is $seg and length is $sl<br>");

            $suff = substr($seg, strlen($seg) - 3, 3);

            // print_r("<br>suff : $suff<br>");

            if ($suff == " EV") {

                //print_r("Got EV<br>");

                $seg = substr($seg, 0, strlen($seg) - 3);

                $sid = CommonHelper::getSegID($seg);

                $ev = true;
            } else {

                // print_r("Not EV<br>");

                $ev = false;

                $sid = CommonHelper::getSegID($seg);
            }

            if ($ev == true)

                $list = XVehicleMaster::select('id', 'custom_model', 'display_name')->where('status', 1)->where('segment_id', $sid)->where('fuel_id', $evn)->get();

            else

                $list = XVehicleMaster::select('id', 'custom_model', 'display_name')->where('status', 1)->where('segment_id', $sid)->where('fuel_id', '!=', $evn)->get();
        }

        // print_r("<br>List is <br>");

        //  print_r($list->toarray());

        // $data = array();

        $data = array();

        foreach ($list as $item)

            $data[$item->custom_model] = $item->custom_model;

        sort($data);

        return $data;
    }



    public static function getSpecSheets()

    {

        $ev = CommonHelper::getOrCreateEnumId("ELECTRIC", "FUEL-TYPE");

        $list = XVehicleMaster::select('id', 'segment_id', 'fuel_id')->where('status', 1)->get();

        $sheets = array();

        foreach ($list as $item) {

            $seg = CommonHelper::enumValueById($item->segment_id);

            if ($seg != "TAXI") {

                if (!isset($sheets[$seg]))

                    $sheets[$seg] = $seg;

                if ($item->fuel_id == $ev) {

                    $seg = $seg . " EV";

                    if (!isset($sheets[$seg]))

                        $sheets[$seg] = $seg;
                }
            }
        }

        sort($sheets);

        return $sheets;
    }



    public static function getVhByModel($mdl = "ALL")

    {

        if ($mdl == "ALL")

            $list = XVehicleMaster::select('id', 'display_name')->where('status', 1)->get();

        else

            $list = XVehicleMaster::select('id', 'display_name')->where('status', 1)->where('custom_model', $mdl)->get();

        $data = array();

        foreach ($list as $item) {

            $data[$item->id] = $item->display_name;
        }

        return array_unique($data);
    }



    public static function getVehicleSpecifications($cm)

    {

        $list  = XVehicleSpecifications::select('id', 'head', 'subhead', 'details')->where('custom_model', $cm)->orderBy('head', 'asc')->orderBy('subhead', 'asc')->get();

        $data = array();

        foreach ($list as $item) {

            $tmp = array();

            $tmp['id'] = $item->id;

            $tmp['model'] = $cm;

            $tmp['head'] = $item->head;

            $tmp['subhead'] = $item->subhead;

            $tmp['value'] = $item->details;

            $data[] = $tmp;
        }

        // array_multisort(

        //     array_column($data, 'head'),

        //     SORT_ASC,

        //     array_column($data, 'subhead'),

        //     SORT_ASC,

        //     $data

        // );

        return $data;
    }



    public static function getVhSpecs($cm)

    {

        $list  = XVehicleSpecifications::select('id', 'head', 'subhead', 'details')->where('custom_model', $cm)->orderBy('head', 'asc')->orderBy('subhead', 'asc')->get();

        $data = array();

        foreach ($list as $item) {

            $tmp = array();

            $tmp['head'] = $item->head;

            $tmp['subhead'] = $item->subhead;

            $tmp['value'] = $item->details;

            $data[] = $tmp;
        }

        // array_multisort(

        //     array_column($data, 'head'),

        //     SORT_ASC,

        //     array_column($data, 'subhead'),

        //     SORT_ASC,

        //     $data

        // );

        return $data;
    }



    public static function getVehicleFeatures($dn)

    {

        $list  = XVehicleFeatures::select('id', 'head', 'subhead', 'details')->where('vehicle_name', $dn)->orderBy('head', 'asc')->orderBy('subhead', 'asc')->get();

        $data = array();

        foreach ($list as $item) {

            $tmp = array();

            $tmp['id'] = $item->id;

            $tmp['vehicle'] = $dn;

            $tmp['head'] = $item->head;

            $tmp['subhead'] = $item->subhead;

            $tmp['value'] = $item->details;

            $data[] = $tmp;
        }

        // array_multisort(

        //     array_column($data, 'head'),

        //     SORT_ASC,

        //     array_column($data, 'subhead'),

        //     SORT_ASC,

        //     $data

        // );

        return $data;
    }



    public static function getVhFeat($dn)

    {

        $list  = XVehicleFeatures::select('id', 'head', 'subhead', 'details')->where('vehicle_name', $dn)->orderBy('head', 'asc')->orderBy('subhead', 'asc')->get();

        $data = array();

        foreach ($list as $item) {

            $tmp = array();

            $tmp['head'] = $item->head;

            $tmp['subhead'] = $item->subhead;

            $tmp['value'] = $item->details;

            $data[] = $tmp;
        }

        // array_multisort(

        //     array_column($data, 'head'),

        //     SORT_ASC,

        //     array_column($data, 'subhead'),

        //     SORT_ASC,

        //     $data

        // );

        return $data;
    }



    public static function getAccessories($seg, $cm, $vrnt)
    {

        //Xessories::select('id', 'item_id', 'price')->where('display_name', $dname)->orderby('price', 'asc')->get();

        $key1  = "segment";

        $key2  = "model";

        $key3 = "variant";

        $list  =  Xessories::select('id', 'item', 'part_no', 'price', 'bundle')->where(function ($query) use ($key1, $seg) {

            $query->where($key1, '=', $seg)

                ->orWhere($key1, '=', 'ANY');
        })->where(function ($query) use ($key2, $cm) {

            $query->where($key2, '=', $cm)

                ->orWhere($key2, '=', 'ANY');
        })->where(function ($query) use ($key3, $vrnt) {

            $query->where($key3, '=', $vrnt)

                ->orWhere($key3, '=', 'ANY');
        })->get();

        $mrp = 0;

        $data = array();

        foreach ($list as $item) {

            if ($item->bundle == 0) {
                $tmp = array();

                $tmp['id'] = $item->id;

                $tmp['name'] = $item->item;

                $tmp['price'] = $item->price;

                $tmp['part_no'] = $item->part_no;

                $data[] = $tmp;
            }
        }
        array_multisort(
            array_column($data, 'name'),
            SORT_ASC,
            $data
        );
        return $data;
    }



    public static function getPriceSettings()

    {

        $list  = PriceSettings::get();

        $data = array();

        foreach ($list as $item) {

            $data[$item->key_name] = array("name" => $item->key_name, "value" => $item->value, "details" => $item->details);
        }

        return $data;
    }

    public static function getRSA($dname)

    {

        $list  = XRSA::select('id', 'year_1', 'year_2', 'year_3', 'year_4', 'year_5')->where('display_name', $dname)->first();

        $data = array();

        if (!empty($list)) {

            if (!empty($list->year_1))

                $data[] = array('year' => '1', 'price' => $list->year_1);

            if (!empty($list->year_2))

                $data[] = array('year' => '2', 'price' => $list->year_2);

            if (!empty($list->year_3))

                $data[] = array('year' => '3', 'price' => $list->year_3);

            if (!empty($list->year_4))

                $data[] = array('year' => '4', 'price' => $list->year_4);

            if (!empty($list->year_5))

                $data[] = array('year' => '5', 'price' => $list->year_5);
        }

        return $data;
    }



    public static function getShield($dname)

    {

        $list  = XShield::where('display_name', $dname)->first();

        $data = array();

        if (!empty($list)) {

            $data[] = array("name" => $list->standard, "price" => 0);

            if (!empty($list->scheme_1))

                $data[] = array("name" => $list->scheme_1, "price" => $list->scheme_1_price);

            if (!empty($list->scheme_2))

                $data[] = array("name" => $list->scheme_2, "price" => $list->scheme_2_price);

            if (!empty($list->scheme_3))

                $data[] = array("name" => $list->scheme_3, "price" => $list->scheme_3_price);

            if (!empty($list->scheme_4))

                $data[] = array("name" => $list->scheme_4, "price" => $list->scheme_4_price);

            if (!empty($list->scheme_5))

                $data[] = array("name" => $list->scheme_5, "price" => $list->scheme_5_price);
        }

        return $data;
    }



    public static function getInsDetails($vid)

    {

        $ins = XInsIRDA::where('vehicle_id', $vid)->where('permit', '<>', 'CSD')->first();

        if ($ins)

            $insextra = XInsExtra::where('irda_id', $ins->id)->first();

        else

            return false;

        if ($ins) {

            $addons = XInsAddons::get();

            $adds = array();



            foreach ($addons as $addon)

                $adds[$addon->id] = $addon->name;

            //print_r($adds);

            // print_r($insextra->toarray());

            $cadds = explode("-", $insextra->combo_1_details);

            // print_r($cadds);

            $dd = array();

            foreach ($cadds as $cadd) {

                if (isset($adds[$cadd]))

                    $dd[] = $adds[$cadd];
            }

            $dp = $ins->total;

            $ddf = $insextra->combo_1_amount;

            $insurance_details[] = array("name" => "Combo 1", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);

            if (!empty($insextra->combo_2_amount)) {

                $cadds = explode("-", $insextra->combo_2_details);

                $dd = array();

                foreach ($cadds as $cadd)

                    $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                $dp = $dp - $ddf + $insextra->combo_2_amount;

                $insurance_details[] = array("name" => "Combo 2", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
            }

            if (!empty($insextra->combo_3_amount)) {

                $cadds = explode("-", $insextra->combo_3_details);

                $dd = array();

                foreach ($cadds as $cadd)

                    $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                $dp = $dp - $ddf + $insextra->combo_3_amount;

                $insurance_details[] = array("name" => "Combo 3", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
            }

            if (!empty($insextra->combo_4_amount)) {

                $cadds = explode("-", $insextra->combo_4_details);

                $dd = array();

                foreach ($cadds as $cadd)

                    $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                $dp = $dp - $ddf + $insextra->combo_4_amount;

                $insurance_details[] = array("name" => "Combo 4", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
            }

            if (!empty($insextra->combo_5_amount)) {

                $cadds = explode("-", $insextra->combo_5_details);

                $dd = array();

                foreach ($cadds as $cadd)

                    $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                $dp = $dp - $ddf + $insextra->combo_5_amount;

                $insurance_details[] = array("name" => "Combo 5", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
            }

            if (!empty($insextra->combo_6_amount)) {

                $cadds = explode("-", $insextra->combo_6_details);

                $dd = array();

                foreach ($cadds as $cadd)

                    $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                $dp = $dp - $ddf + $insextra->combo_6_amount;

                $insurance_details[] = array("name" => "Combo 6", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
            }
        }

        return $insurance_details;
    }



    public static function getModelMedia($cm)

    {

        return array("img_front" => "", "img_back" => "", "img_left" => "", "img_right" => "", "brochure" => "");
    }



    public static function getSegments()
    {
        $list = DB::table('xlr8_vehicle_segment')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->get();

        $data = [];

        foreach ($list as $item) {
            $data[$item->id] = [
                'id'   => $item->id,
                'name' => $item->name,
            ];
        }

        return $data;
    }



    public static function getModelsX($segid = null)
    {
        $query = DB::table('xlr8_vehicle_model')
            ->select('id', 'name')
            ->whereNull('deleted_at');

        if (!empty($segid)) {

            $segment = Segment::find($segid);

            if ($segment) {
                $query->where('segment_code', $segment->code);
            }
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id'   => $item->id,
                    'name' => $item->name,
                ];
            })
            ->toArray();
    }

    



    public static function getVehiclesX($cm = null)
    {
        $query = DB::table('xlr8_vehicle_variant')
            ->select('id', 'name')
            ->whereNull('deleted_at');

        if (!empty($cm)) {

            $model = VehicleModel::find($cm);

            if ($model) {
                $query->where('model_code', $model->code);
            }
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id'   => $item->id,
                    'name' => $item->name,
                ];
            })
            ->toArray();
    }



    public static function getColorX($variantId = null)
    {
        $query = DB::table('xlr8_vehicle_color')
            ->select('id', 'name', 'code')
            ->whereNull('deleted_at');

        return $query
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'vid'        => $item->id,
                    'vname'      => $item->name,
                    'colr_name'  => $item->name,
                    'model_code' => $item->code ?? '',
                    'seating'    => '',
                ];
            })
            ->toArray();
    }



    public static function getPermitX($dn, $clr)

    {

        $dn = strtoupper($dn);

        $clr = strtoupper($clr);

        $list = XVehicleMaster::select('permit_id')->where('display_name', $dn)->where('color', $clr)->where('status', 1)->get();



        //print_r($list->toarray());

        $data = array();

        foreach ($list as $item) {

            $data[] = array('permit_id' => $item->permit_id, 'permit' => CommonHelper::enumValueById($item->permit_id));
        }

        return $data;
    }





    public static function getPriceMaster($seg = "ALL")

    {

        if (strtoupper($seg) == 'ALL')

            $list = XVehicleMaster::where('status', 1)->get();

        else {

            $sid = CommonHelper::getSegID($seg);

            $list = XVehicleMaster::where('status', 1)->where('segment_id', $sid)->get();
        }

        $vdata = $apack = $shield = $corp = $xchange = $loyalty = $master = array();

        $rapack = Xessories::get();

        $tmp = array();

        foreach ($rapack as $item) {

            if (!isset($apack[$item->display_name]))

                $apack[$item->display_name] = array();

            $apack[$item->display_name][] = $item;
        }



        $rrsa = XRSA::get();

        foreach ($rrsa as $item)

            $rsa[$item->display_name] = $item;

        $shield = XShield::get();

        foreach ($shield as $item)

            $shield[$item->display_name] = $item;

        $rcorp = XCorp::get();

        foreach ($rcorp as $item) {

            if (!isset($corp[$item->display_name]))

                $corp[$item->display_name] = array();

            $corp[$item->display_name][] = $item;
        }

        $rxchange = XchangeNloyalty::get();

        foreach ($rxchange as $item) {

            if (!isset($xchange[$item->display_name]))

                $xchange[$item->display_name] = array();

            $xchange[$item->display_name][] = $item;
        }



        $rmaster = XSchemeMaster::get();

        foreach ($rmaster as $item)

            $master[$item->display_name] = $item;

        $i = 0;

        foreach ($list as $vh) {

            $seg = CommonHelper::enumValueById($vh->segment_id);

            $fuel = CommonHelper::enumValueById($vh->fuel_id);

            $wheels = $vh->wheels;

            $seating = $vh->seating;

            if (!isset($tmp[$seg]))

                $tmp[$seg] = array("segment" => $seg, "models" => array());

            if (!isset($tmp[$seg]["models"][$vh->custom_model])) {

                $tmp[$seg]["models"][$vh->custom_model] = array("name" => $vh->custom_model, "specifications" => self::getVhSpecs($vh->custom_model), "media" => self::getModelMedia($vh->custom_model), "apack" => self::getAccessories($seg, $vh->custom_model), "variants" => array());
            }

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name])) {

                $rsa_details = array();

                if (isset($rsa[$vh->display_name])) {

                    $trsa = $rsa[$vh->display_name];

                    if (!empty($trsa->year_1))

                        $rsa_details[] = array('year' => '1', 'value' => $trsa->year_1);

                    if (!empty($trsa->year_2))

                        $rsa_details[] = array('year' => '2', 'value' => $trsa->year_2);

                    if (!empty($trsa->year_3))

                        $rsa_details[] = array('year' => '3', 'value' => $trsa->year_3);

                    if (!empty($trsa->year_4))

                        $rsa_details[] = array('year' => '4', 'value' => $trsa->year_4);

                    if (!empty($trsa->year_5))

                        $rsa_details[] = array('year' => '5', 'value' => $trsa->year_5);
                }

                $shield_details = array();

                if (isset($shield[$vh->display_name])) {

                    $tshield = $shield[$vh->display_name];

                    $shield_details[] = array("scheme" => $tshield->standard, "price" => 0);

                    if (!empty($tshield->scheme_1))

                        $shield_details[] = array("scheme" => $tshield->scheme_1, "price" => $tshield->scheme_1_price);

                    if (!empty($tshield->scheme_2))

                        $shield_details[] = array("scheme" => $tshield->scheme_2, "price" => $tshield->scheme_2_price);

                    if (!empty($tshield->scheme_3))

                        $shield_details[] = array("scheme" => $tshield->scheme_3, "price" => $tshield->scheme_3_price);

                    if (!empty($tshield->scheme_4))

                        $shield_details[] = array("scheme" => $tshield->scheme_4, "price" => $tshield->scheme_4_price);

                    if (!empty($tshield->scheme_5))

                        $shield_details[] = array("scheme" => $tshield->scheme_5, "price" => $tshield->scheme_5_price);
                }

                $corp_details = array();

                if (isset($master[$vh->display_name])) {

                    $tmaster = $master[$vh->display_name];

                    $corp_details['category'] = "M1";

                    $corp_details['amount'] = $tmaster->mnm + $tmaster->dealer;

                    $corp_details['scrappage_bonus'] = ($tmaster->corp == 1) ? true : false;

                    $corp_details['exchange_bonus'] = ($tmaster->exch == 1) ? true : false;

                    $corp_details['loyalty_bonus'] = ($tmaster->loyl == 1) ? true : false;
                }

                if (isset($corp[$vh->display_name])) {

                    $tcorp = $corp[$vh->display_name];

                    foreach ($tcorp as $item) {

                        $corp_details[] = array('category' => $item->category, 'amount' => $item->mnm + $item->dealer, 'scrappage_bonus' => true, 'exchange_bonus' => true, 'loyalty_bonus' => true);
                    }
                }

                $xchange_details = array();

                if (isset($xchange[$vh->display_name])) {

                    $txchange = $xchange[$vh->display_name];

                    foreach ($txchange as $item) {

                        $xchange_details[] = array('type' => $item->type, 'scheme' => $item->scheme, 'amount' => $item->mnm + $item->dealer);
                    }
                }

                // $loyalty_details = array();

                // if (isset($loyalty[$vh->display_name])) {

                //     $tloyalty = $loyalty[$vh->display_name];

                //     foreach ($tloyalty as $item) {

                //         $loyalty_details[] = array('scheme' => $item->scheme, 'amount' => $item->mnm + $item->dealer);

                //     }

                // }

                // $ms_details = array();

                // if (isset($master[$vh->display_name])) {

                //     $tmaster = $master[$vh->display_name];

                //     $ms_details['scheme'] = $tmaster->scheme;

                //     $ms_details['amount'] = $tmaster->mnm + $tmaster->dealer;

                //     $ms_details['scrappage_bonus'] = ($tmaster->corp == 1) ? true : false;

                //     $ms_details['exchange_bonus'] = ($tmaster->exch == 1) ? true : false;

                //     $ms_details['loyalty_bonus'] = ($tmaster->loyl == 1) ? true : false;

                // }

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name] = array("name" => $vh->display_name, "fuel" => $fuel, "seating" => $seating, "wheels" => $wheels, "features" => self::getVhFeat($vh->display_name), "pricing" => array("exshowroom" => $vh->exshowroom, "incidental_charges" => $vh->incidental_charges, "fastag" => $vh->fastag, "trc" => $vh->trc, "rsa" => $vh->rsa, "rsa_data" => $rsa_details, "shield" => $vh->shield, "shield_data" => $shield_details, "apack" => $vh->accessories, "minimum_apack_value" => $vh->accessories_mnp, "rsa_discount" => $vh->rsa_discount, "shield_discount" => $vh->shield_discount, "apack_discount" => $vh->accessories_discount, "corporate_bonus" => $corp_details, "exchange_bonus" => $xchange_details,  "year" => array()));
            }

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin])) {

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin] = array("year" => $vh->vin, "pricing_type" => array());
            }

            $tpermit = CommonHelper::enumValueById($vh->permit_id);

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin]["pricing_type"][$tpermit])) {

                $ins = XInsIRDA::where('vehicle_id', $vh->id)->where('permit', '<>', 'CSD')->first();

                if ($ins)

                    $insextra = XInsExtra::where('irda_id', $ins->id)->first();

                else

                    $insextra = false;

                $rto = XRtoData::select()->where('vehicle_id', $vh->id)->first();

                if ($rto)

                    $rto_charge = ($rto->rto_sc_applicable == 1) ? $rto->service_charge : 0;

                else

                    $rto_charge = 0;

                $insurance_details = array();



                if ($ins) {

                    $addons = XInsAddons::get();

                    $adds = array();



                    foreach ($addons as $addon)

                        $adds[$addon->id] = $addon->name;

                    //print_r($adds);

                    // print_r($insextra->toarray());

                    $cadds = explode("-", $insextra->combo_1_details);

                    // print_r($cadds);

                    $dd = array();

                    foreach ($cadds as $cadd) {

                        if (isset($adds[$cadd]))

                            $dd[] = $adds[$cadd];
                    }

                    $dp = $ins->total;

                    $ddf = $insextra->combo_1_amount;

                    $insurance_details[] = array("name" => "Combo 1", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);

                    if (!empty($insextra->combo_2_amount)) {

                        $cadds = explode("-", $insextra->combo_2_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_2_amount;

                        $insurance_details[] = array("name" => "Combo 2", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_3_amount)) {

                        $cadds = explode("-", $insextra->combo_3_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_3_amount;

                        $insurance_details[] = array("name" => "Combo 3", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_4_amount)) {

                        $cadds = explode("-", $insextra->combo_4_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_4_amount;

                        $insurance_details[] = array("name" => "Combo 4", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_5_amount)) {

                        $cadds = explode("-", $insextra->combo_5_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_5_amount;

                        $insurance_details[] = array("name" => "Combo 5", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_6_amount)) {

                        $cadds = explode("-", $insextra->combo_6_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_6_amount;

                        $insurance_details[] = array("name" => "Combo 6", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }
                }

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin]["pricing_type"][$tpermit] =

                    array(

                        "type" => $tpermit,

                        "this_pricing" => array(

                            "rto" => $vh->rto,

                            "rto_service_charge" => $rto_charge,

                            "insurance" => $vh->insurance,

                            "insurance_data" => $insurance_details,

                            "total_additions" => $vh->total_addition,

                            "cash_discount" => $vh->cash_discount,

                            "total_deductions" => $vh->total_discount,

                            "invoice" => $vh->invoice,

                            "onroad" => $vh->onroad,

                            "tcs" => $vh->tcs

                        ),

                        "colors" => array(

                            $vh->color => array("name" => $vh->color, "vid" => $vh->id)

                        )

                    );
            } else

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin]["pricing_type"][$tpermit]["colors"][$vh->color] = array("name" => $vh->color, "vid" => $vh->id);
        }



        $list = XCsd::where('status', 1)->get();



        foreach ($list as $cvh) {

            $vh = XVehicleMaster::find($cvh->vehicle_id);

            $fuel = CommonHelper::enumValueById($vh->fuel_id);

            $wheels = $vh->wheels;

            $seating = $vh->seating;

            $oseg = "CommonHelper::enumValueById($vh->segment_id)";

            $seg = "CSD";

            if (!isset($tmp[$seg]))

                $tmp[$seg] = array("segment" => $seg, "models" => array());

            if (!isset($tmp[$seg]["models"][$vh->custom_model])) {

                $tmp[$seg]["models"][$vh->custom_model] = array("name" => $vh->custom_model, "specifications" => self::getVhSpecs($vh->custom_model), "media" => self::getModelMedia($vh->custom_model), "apack" => self::getAccessories($oseg, $vh->custom_model), "variants" => array());
            }

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name])) {

                $rsa_details = array();

                if (isset($rsa[$vh->display_name])) {

                    $trsa = $rsa[$vh->display_name];

                    if (!empty($trsa->year_1))

                        $rsa_details[] = array('year' => '1', 'value' => $trsa->year_1);

                    if (!empty($trsa->year_2))

                        $rsa_details[] = array('year' => '2', 'value' => $trsa->year_2);

                    if (!empty($trsa->year_3))

                        $rsa_details[] = array('year' => '3', 'value' => $trsa->year_3);

                    if (!empty($trsa->year_4))

                        $rsa_details[] = array('year' => '4', 'value' => $trsa->year_4);

                    if (!empty($trsa->year_5))

                        $rsa_details[] = array('year' => '5', 'value' => $trsa->year_5);
                }

                $shield_details = array();

                if (isset($shield[$vh->display_name])) {

                    $tshield = $shield[$vh->display_name];

                    $shield_details[] = array("scheme" => $tshield->standard, "price" => 0);

                    if (!empty($tshield->scheme_1))

                        $shield_details[] = array("scheme" => $tshield->scheme_1, "price" => $tshield->scheme_1_price);

                    if (!empty($tshield->scheme_2))

                        $shield_details[] = array("scheme" => $tshield->scheme_2, "price" => $tshield->scheme_2_price);

                    if (!empty($tshield->scheme_3))

                        $shield_details[] = array("scheme" => $tshield->scheme_3, "price" => $tshield->scheme_3_price);

                    if (!empty($tshield->scheme_4))

                        $shield_details[] = array("scheme" => $tshield->scheme_4, "price" => $tshield->scheme_4_price);

                    if (!empty($tshield->scheme_5))

                        $shield_details[] = array("scheme" => $tshield->scheme_5, "price" => $tshield->scheme_5_price);
                }

                $ins = XInsIRDA::where('vehicle_id', $cvh->id)->where('permit', 'CSD')->first();

                if ($ins)

                    $insextra = XInsExtra::where('irda_id', $ins->id)->first();

                else

                    $insextra = false;

                $rto = XRtoData::select()->where('vehicle_id', $cvh->id)->first();

                if ($rto)

                    $rto_charge = ($rto->rto_sc_applicable == 1) ? $rto->service_charge : 0;

                else

                    $rto_charge = 0;

                $insurance_details = array();



                if ($ins) {

                    $addons = XInsAddons::get();

                    $adds = array();



                    foreach ($addons as $addon)

                        $adds[$addon->id] = $addon->name;

                    //print_r($adds);

                    // print_r($insextra->toarray());

                    $cadds = explode("-", $insextra->combo_1_details);

                    // print_r($cadds);

                    $dd = array();

                    foreach ($cadds as $cadd) {

                        if (isset($adds[$cadd]))

                            $dd[] = $adds[$cadd];
                    }

                    $dp = $ins->total;

                    $ddf = $insextra->combo_1_amount;

                    $insurance_details[] = array("name" => "Combo 1", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);

                    if (!empty($insextra->combo_2_amount)) {

                        $cadds = explode("-", $insextra->combo_2_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_2_amount;

                        $insurance_details[] = array("name" => "Combo 2", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_3_amount)) {

                        $cadds = explode("-", $insextra->combo_3_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_3_amount;

                        $insurance_details[] = array("name" => "Combo 3", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_4_amount)) {

                        $cadds = explode("-", $insextra->combo_4_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_4_amount;

                        $insurance_details[] = array("name" => "Combo 4", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_5_amount)) {

                        $cadds = explode("-", $insextra->combo_5_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_5_amount;

                        $insurance_details[] = array("name" => "Combo 5", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }

                    if (!empty($insextra->combo_6_amount)) {

                        $cadds = explode("-", $insextra->combo_6_details);

                        $dd = array();

                        foreach ($cadds as $cadd)

                            $dd[] = (isset($adds[$cadd])) ? $adds[$cadd] : "";

                        $dp = $dp - $ddf + $insextra->combo_6_amount;

                        $insurance_details[] = array("name" => "Combo 6", "details" => "Basic + " . implode(" + ", $dd), "price" => round($dp, 2), "company" => $insextra->company);
                    }
                }





                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name] = array(

                    "name" => $vh->display_name,

                    "fuel" => $fuel,

                    "seating" => $seating,

                    "wheels" => $wheels,

                    "features" => self::getVhFeat($vh->display_name),

                    "pricing" => array("incidental_charges" => $vh->incidental_charges, "fastag" => $vh->fastag, "trc" => $vh->trc, "rsa" => $vh->rsa, "rsa_data" => $rsa_details, "shield" => $vh->shield, "shield_data" => $shield_details, "apack" => $vh->accessories, "minimum_apack_value" => $vh->accessories_mnp)

                );
            }

            $dvin = idate('Y');

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin])) {

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$dvin] = array("year" => $dvin, "pricing_type" => array());
            }

            $tpermit = CommonHelper::enumValueById($vh->permit_id);

            if (!isset($tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$dvin]["pricing_type"][$tpermit])) {

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin]["pricing_type"][$tpermit] =

                    array(

                        "type" => $tpermit,

                        "this_pricing" => array(

                            "invoice" => $cvh->invoice,

                            "dd_amt" => $cvh->dd,

                            "tcs" => $cvh->tcs,

                            "rto" => $cvh->rto,

                            "rto_service_charge" => $rto_charge,

                            "insurance" => $cvh->insurance,

                            "insurance_data" => $insurance_details,

                            "c2d" => $cvh->c2d

                        ),

                        "colors" => array(

                            $vh->color => array("name" => $vh->color, "vid" => $vh->id)

                        )

                    );
            } else

                $tmp[$seg]["models"][$vh->custom_model]["variants"][$vh->display_name]["pricing"]["year"][$vh->vin]["pricing_type"][$tpermit]["colors"][$vh->color] = array("name" => $vh->color, "vid" => $vh->id);
        }





        $vdata = array();

        foreach ($tmp as $key1 => $seg) {

            $tpd1 = array();

            $tpd1["segment"] = $seg["segment"];

            $tpd1["models"] = array();

            foreach ($seg["models"] as $key2 => $model) {

                $tpd2 = array();

                $tpd2["name"] = $model["name"];

                $tpd2["specifications"] = $model["specifications"];

                $tpd2["media"] = $model["media"];

                $tpd2["apack"] = $model["apack"];

                $tpd2["variants"] = array();

                foreach ($model["variants"] as $key3 => $variant) {

                    $tpd3 = array();

                    $tpd3["name"] = $variant["name"];

                    $tpd3["fuel"] = $variant["fuel"];

                    $tpd3["seating"] = $variant["seating"];

                    $tpd3["wheels"] = $variant["wheels"];

                    $tpd3["features"] = $variant["features"];

                    $tpd3["pricing"] = $variant["pricing"];

                    $tpd3["pricing"]['tear'] = array();

                    foreach ($variant["pricing"]["year"] as $key4 => $year) {

                        $tpd4 = array();

                        $tpd4["year"] = $key4;

                        $tpd4["pricing_type"] = array();

                        foreach ($year["pricing_type"] as $key5 => $ptype) {

                            $tpd5 = array();

                            $tpd5["type"] = $ptype["type"];

                            $tpd5["this_pricing"] = $ptype["this_pricing"];

                            $tpd5["colors"] = array();

                            foreach ($ptype["colors"] as $key6 => $color)

                                $tpd5["colors"][] = $color;

                            $tpd4["pricing_type"][] = $tpd5;
                        }

                        $tpd3["pricing"]['tear'][] = $tpd4;
                    }

                    $tpd3["pricing"]['year'] = $tpd3["pricing"]['tear'];

                    unset($tpd3["pricing"]['tear']);

                    $tpd2["variants"][] = $tpd3;
                }

                $tpd1["models"][] = $tpd2;
            }

            $vdata[] = $tpd1;
        }





        return $vdata;
    }



    public static function getCorp($dname)

    {

        $list  = XCorp::where('display_name', $dname)->get();

        $data = array();

        foreach ($list as $itm) {

            $amt = $itm->mnm + $itm->dealer;

            $data[] = array('name' => $itm->category, 'amount' => $amt);
        }

        return $data;
    }



    public static function getEnL($dname, $type = false)

    {

        $list  = XchangeNloyalty::where('display_name', $dname)->get();

        $data = array();

        foreach ($list as $itm) {

            $amt = $itm->mnm + $itm->dealer;

            $data[] = array('type' => $itm->type, 'name' => $itm->scheme, 'amount' => $amt);
        }

        return $data;
    }



    public static function getMasterScheme($dname)

    {

        $list  = XSchemeMaster::where('display_name', $dname)->first();

        $data = array();

        if ($list) {

            //print_r($list->toarray());

            $acorp = ($list->corp == 1) ? 1 : 0;

            $aexch = ($list->exch == 1) ? 1 : 0;

            $aloyl = ($list->loyl == 1) ? 1 : 0;

            $amt = $list->mnm + $list->dealer;

            $data = array('name' => $list->scheme, 'amount' => $amt, 'corp' => $acorp, 'exch' => $aexch, 'loyl' => $aloyl);
        }

        return $data;
    }



    public static function getPriceHoldStatus()

    {

        $rec = PriceSettings::where('key_name', 'price_hold')->first();

        //dd($rec->toarray());

        if ($rec->value == "true")

            return true;

        else

            return false;
    }



    public static function setPriceHoldStatus($status = false) //send "true" to enable price hold and "false" to disable price hold

    {

        $rec = PriceSettings::where('key_name', 'price_hold')->first();

        $rec->value = ($status == true) ? "true" : "false";

        $rec->save();
    }





    public static function getStdQuote($vid, $enqid)

    {

        $sq = array("vid" => 0,  "enq_id" => 0, "exshowroom" => 0, "incidental" => 0, "fastag" => 0, "trc" => 0, "rto_tape" => 0, "rsa_details" => "", "rsa_amount" => 0,  "shield_details" => "", "shield_amount" => 0, "ins_type" => "", "ins_amount" => 0, "ins_details" => "",  "rto_amount" => 0, "rto_details" => "", "rto_disc" => 0, "apack_details" => array(), "apack_min" => 0, "apack_general" => 0, "tcs" => 0, "apack_disc" => 0, "rsa_disc" => 0, "shield_disc" => 0, "extra_disc" => 0, "cash_disc" => 0, "fame_disc" => 0, "spl_disc" => 0, "corp_details" => 0, "corp_disc" => 0,  "enl_type" => "", "enl_disc" => 0, "enl_details" => "", "rto_charges" => 0, "onroad" => 0, "invoice" => 0, "remarks" => "", "fix_cash" => 0, "cash" => 0, "credit" => 0, "fix_credit" => 0);

        $tsq = self::getVehiclePrice($vid);

        $sq["vid"] = $vid;

        $sq["enq_id"] = $enqid;

        $sq["exshowroom"] = $tsq['pricing']['exshowroom'];

        $sq["incidental"] = $tsq['pricing']['additions']['incidental_charges'];

        $sq["fastag"] = $tsq['pricing']['additions']['fastag'];

        $sq["trc"] = $tsq['pricing']['additions']['trc'];

        $sq["rto_tape"] = $tsq['pricing']['additions']['rto_tape'];

        $sq["rsa_amount"] = $tsq['pricing']['additions']['rsa'];

        $sq["shield_amount"] = $tsq['pricing']['additions']['shield'];

        $sq["ins_amount"] = $tsq['pricing']['additions']['insurance'];

        $sq["rto_amount"] = $tsq['pricing']['additions']['rto'];

        $sq["apack_min"] = $tsq['pricing']['minimum_apack_value'];

        $sq["apack_general"] = $tsq['pricing']['additions']['apack'];

        $sq["tcs"] = $tsq['pricing']['additions']['tcs'];

        $sq["apack_disc"] = $tsq['pricing']['deductions']['accessories_discount'];

        $sq["rsa_disc"] = $tsq['pricing']['deductions']['rsa_discount'];

        $sq["shield_disc"] = $tsq['pricing']['deductions']['shield_discount'];

        $sq["cash_disc"] = $tsq['pricing']['deductions']['cash_discount'];

        $sq["fame"] = $tsq['pricing']['deductions']['fame_subsidy'];

        $sq["rto_charges"] = $tsq['pricing']['rto_charges'];

        $sq["onroad"] = $tsq['pricing']['onroad'];

        $sq["invoice"] = $tsq['pricing']['invoice'];

        return $sq;
    }



    public static function getVehiclePrice($vid)

    {

        $vh = XVehicleMaster::find($vid);

        $clrs = XVehicleMaster::select('id', 'color', 'code')->where('display_name', $vh->display_name)->where('segment_id', $vh->segment_id)->where('vin', $vh->vin)->where('status', 1)->orderBy('color', 'asc')->get();

        $colors = array();

        foreach ($clrs as $clr) {

            $colors[$clr->id] = array('id' => $clr->id, 'name' => $clr->color . " - " . $clr->code);
        }

        $plshow = self::getPriceHoldStatus();

        $trto = XrtoData::select('id', 'rto_sc_applicable', 'rto_service_charge')->where('vehicle_id', $vid)->first();

        $settings = self::getPriceSettings();

        $data = array();

        if ($vh && $vh->status == 1) {

            $seg = CommonHelper::enumValueById($vh->segment_id);

            $rsa_details = self::getRsa($vh->display_name);

            $shield_details = self::getShield($vh->display_name);

            $apack_details = self::getAccessories($seg, $vh->custom_model);

            $rto_details = array();

            $insurance_details = self::getInsDetails($vid);

            $corp_details = self::getCorp($vh->display_name);

            $xchange_details = self::getEnL($vh->display_name, 'EXCHANGE');

            $loyalty_details = self::getEnL($vh->display_name, 'LOYALTY');

            $ms_details = self::getMasterScheme($vh->display_name);



            $data = $pricing = array();

            $data['id'] = $vid;

            $data["custom_model"] = $vh->custom_model;

            $data["custom_variant"] = $vh->custom_variant;

            $data["code"] = $vh->code;

            $data["name"] = $vh->display_name;

            $data["segment"] = $seg;

            $data["permit"] = CommonHelper::enumValueById($vh->permit_id);

            $data['fuel_type'] = CommonHelper::enumValueById($vh->fuel_id);

            $data['seating'] = $vh->seating;

            $data["specifications"] = self::getVehicleSpecifications($vh->custom_model);

            $data["features"] = self::getVehicleFeatures($vh->display_name);

            $data["colors"] = $colors;

            $data["media"] =  [

                "img_front" => "",

                "img_back" => "",

                "img_left" => "",

                "img_right" => "",

                "brochure" => ""

            ];

            if ($plshow) {

                $pricing["exshowroom"] = 0;

                $pricing["additions"] = array();

                $pricing["additions"]["incidental_charges"] = 0;

                $pricing["additions"]["fastag"] = 0;

                $pricing["additions"]["trc"] = 0;

                $pricing["additions"]["rto_tape"] = 0;

                $pricing["additions"]["rsa"] = 0;

                $pricing["additions"]["rsa_data"] = array();

                $pricing["additions"]["shield"] = 0;

                $pricing["additions"]["shield_data"] = array();

                $pricing["additions"]["apack"] = 0;

                $pricing["additions"]["apack_data"] = array();

                $pricing["additions"]["rto"] = 0;

                $pricing["additions"]["insurance"] = 0;

                $pricing["additions"]["insurance_data"] = array();

                $pricing["additions"]["tcs"] = 0;

                $pricing["total_additions"] = 0;

                $pricing["deductions"] = array();

                $pricing["deductions"]["rsa_discount"] =  0;

                $pricing["deductions"]["shield_discount"] = 0;

                $pricing["deductions"]["accessories_discount"] = 0;

                $pricing["deductions"]["cash_discount"] = 0;

                $pricing["deductions"]["fame_subsidy"] = 0;

                $pricing["total_deductions"] = 0;

                $pricing["conditional_deductions"] = array();

                $pricing["conditional_deductions"]["corporate_bonus"] = array();

                $pricing["conditional_deductions"]["exchange_bonus"] = array();

                $pricing["conditional_deductions"]["loyalty_bonus"] = array();

                $pricing["conditional_deductions"]["master_scheme"] = array();

                $pricing["invoice"] = 0;

                $pricing["onroad"] = 0;

                $pricing["minimum_apack_value"] = 0;

                $pricing["rto_charges"] = 0;

                $pricing["settings"] = $settings;
            } else {

                $pricing["exshowroom"] = $vh->exshowroom;

                $pricing["additions"] = array();

                $pricing["additions"]["incidental_charges"] = (!empty($vh->incidental_charges)) ? $vh->incidental_charges : 0;

                $pricing["additions"]["fastag"] = (!empty($vh->fastag)) ? $vh->fastag : 0;

                $pricing["additions"]["trc"] = (!empty($vh->trc)) ? $vh->trc : 0;

                $pricing["additions"]["rto_tape"] = (!empty($vh->rto_tape)) ? $vh->rto_tape : 0;

                $pricing["additions"]["rsa"] = (!empty($vh->rsa)) ? $vh->rsa : 0;

                $pricing["additions"]["rsa_data"] = (!empty($rsa_details)) ? $rsa_details : array();

                $pricing["additions"]["shield"] = (!empty($vh->shield)) ? $vh->shield : 0;

                $pricing["additions"]["shield_data"] = $shield_details;

                $pricing["additions"]["apack"] = (!empty($vh->accessories)) ? $vh->accessories : 0;

                $pricing["additions"]["apack_data"] = $apack_details;

                $pricing["additions"]["rto"] = (!empty($vh->rto)) ? $vh->rto : 0;

                $pricing["additions"]["insurance"] = (!empty($vh->insurance)) ? $vh->insurance : 0;

                $pricing["additions"]["insurance_data"] = $insurance_details;

                $pricing["additions"]["tcs"] = (!empty($vh->tcs)) ? $vh->tcs : 0;

                $pricing["total_additions"] = $vh->total_addition;

                $pricing["deductions"] = array();

                $pricing["deductions"]["rsa_discount"] = (!empty($vh->rsa_discount)) ? $vh->rsa_discount : 0;

                $pricing["deductions"]["shield_discount"] = (!empty($vh->shield_discount)) ? $vh->shield_discount : 0;

                $pricing["deductions"]["accessories_discount"] = (!empty($vh->accessories_discount)) ? $vh->accessories_discount : 0;

                $pricing["deductions"]["cash_discount"] = (!empty($vh->cash_discount)) ? $vh->cash_discount : 0;

                $pricing["deductions"]["fame_subsidy"] = (!empty($vh->fame)) ? $vh->fame : 0;

                $pricing["total_deductions"] = $vh->total_discount;

                $pricing["conditional_deductions"] = array();

                $pricing["conditional_deductions"]["corporate_bonus"] = $corp_details;

                $pricing["conditional_deductions"]["exchange_bonus"] = $xchange_details;

                $pricing["conditional_deductions"]["loyalty_bonus"] = $loyalty_details;

                $pricing["conditional_deductions"]["master_scheme"] = $ms_details;

                $pricing["invoice"] = $vh->invoice;

                $pricing["onroad"] = $vh->onroad;

                $pricing["minimum_apack_value"] = $vh->accessories_mnp;

                $pricing["rto_charges"] = ($trto->rto_sc_applicable == 0) ? $trto->rto_service_charge : 0;

                $pricing["settings"] = $settings;
            }

            $data["pricing"] = $pricing;

            return $data;
        } else

            return false;
    }



    public static function getInsurance($vid, $invoice, $permit, $combo = 1)

    {

        $irda = XInsIRDA::where('vehicle_id', $vid)->where('permit', $permit)->first();



        if (!$irda) {

            if ($permit == "CSD") {

                $tirda = XInsIRDA::where('vehicle_id', $vid)->where('permit', 'PRIVATE')->first();

                if ($tirda) {

                    $irda = $tirda->replicate();

                    $irda->permit = "CSD";

                    $irda->created_at = Carbon::now();

                    $irda->save();

                    $textras = XInsExtra::where('vehicle_id', $vid)->where('irda_id', $irda->id)->get();

                    foreach ($textras as $textra) {

                        $nextra = $textra->replicate();

                        $nextra->created_at = Carbon::now();

                        $nextra->save();
                    }
                } else

                    return false;
            } else

                return false;
        }



        $idv = $invoice * $irda->idv_rate;

        $od = ($irda->od_rate * $idv) / 100;

        $od +=  $irda->od_surcharge;

        $od = $od - ($irda->od_discount_rate * $od);

        $imt = $od * $irda->imt_rate;

        $tp = $irda->tp;

        $tax = $od * $irda->od_tax_rate;

        $tax += $imt * $irda->imt_tax_rate;

        $tax += $tp * $irda->tp_tax_rate;

        $total = $od + $imt + $tp + $tax;

        $extra = XInsExtra::where('vehicle_id', $vid)->where('irda_id', $irda->id)->first();

        $odp = $extra->pa_cover;

        $odp += $extra->legal_liability;

        if (!empty($extra->non_paying_psng) && (!empty($extra->adjusted_seating)))

            $odp +=   round($extra->non_paying_psng *  $extra->adjusted_seating, 2);

        else if (!empty($extra->paying_psng) && !empty($extra->adjusted_seating))

            $odp +=   round($extra->paying_psng *  $extra->adjusted_seating, 2);



        $odp_tax = round($odp * $extra->odp_tax_rate, 2);

        $total += $odp + $odp_tax;

        $combo = "combo_" . $combo . "_amount";

        if (!empty($extra->$combo))

            $amt = $extra->$combo;

        else

            $amt = 0;

        //$atax = $amt * $extra->addon_tax_rate / 100;

        $total += $amt;

        return $total;
    }





    public static function getPricingHeads()

    {

        $data = XPriceHeads::select('id', 'oem_label', 'db_col')->get()->toArray();

        //dd($data);

        return $data;
    }



    public static function tcsConditions()

    {

        $data = PriceSettings::get();

        $res = array();

        foreach ($data as $d)

            if ($d->key_name == 'tcs_rate' || $d->key_name == 'tcs_applied_from')

                $res[$d->key_name] = $d->value;

        //dd($res);

        return $res;
    }





    public static function addBackup($head, $type, $data)

    {

        $sr = new XSnapShot();

        $sr->snap_type = $type;

        $sr->snap_head = $head;

        if ($type == 'FILE')

            $sr->details = $data;

        else

            $sr->sdata = json_encode($data);

        $sr->save();

        return;
    }



    public static function getAccItems()

    {

        $data = XessoriesItems::select('id', 'name')->get()->toArray();

        //dd($data);

        return $data;
    }



    public static function getRtoHeads()

    {

        $data = XRtoHeads::select('id', 'label', 'name')->get()->toArray();

        //dd($data);

        return $data;
    }



    public static function getInsAddons()

    {

        $data = XInsAddons::select('id', 'name', 'slug')->get()->toArray();

        //dd($data);

        return $data;
    }



    public static function getVehicleId($vcode)

    {

        $data = XVehicleMaster::where('code', $vcode)->first();

        if ($data)

            return $data->id;

        else

            return false;
    }



    public static function getVehicleBycode($vcode)

    {

        $data = XVehicleMaster::where('code', $vcode)->first();

        if ($data)

            return $data;

        else

            return false;
    }



    public static function getVehicleInv($vid)

    {

        $data = XVehicleMaster::find($vid);

        if ($data) {

            $inv = $data->exshowroom;

            $inv = round($inv / 1000) * 1000;

            return $inv;
        } else

            return false;
    }



    public static function getVehicleIds($vcode)

    {

        $data = XVehicleMaster::select('id', 'display_name')->where('display_name', $vcode)->get();

        $ids = [];

        if ($data) {

            foreach ($data as $d) {

                $ids[] = $d->id;
            }

            return $ids;
        } else

            return false;
    }





    public static function getVehiclesByName($vcode, $segid = false)

    {

        if ($segid != false)

            $data = XVehicleMaster::select('id', 'custom_model', 'custom_variant', 'display_name', 'invoice', 'exshowroom', 'onroad', 'permit_id')->where('display_name', $vcode)->where('segment_id', $segid)->get();

        else

            $data = XVehicleMaster::select('id', 'custom_model', 'custom_variant', 'display_name', 'invoice', 'exshowroom', 'onroad', 'permit_id')->where('display_name', $vcode)->get();

        if ($data)

            return $data;

        else

            return false;
    }







    public static function priceRefresh()

    {

        $vhs = XVehicleMaster::get();

        foreach ($vhs as $vh) {

            //print_r($vh->id . " " . $vh->display_name . "<br> ");

            $rto = XRtoData::where('vehicle_id', $vh->id)->first();

            $ins = XInsIRDA::where('vehicle_id', $vh->id)->first();

            if (isset($rto->net_rto_amount))

                $vh->rto = $rto->net_rto_amount;

            else {

                $vh->rto = 0;

                //print_r("<br>");

            }

            if (isset($ins->total))

                $vh->insurance = $ins->total;

            else {

                $vh->insurance = 0;

                // print_r("<br>");

            }

            $vh->total_addition = $vh->incidental_charges + $vh->fastag + $vh->trc + $vh->rto_tape + $vh->rsa + $vh->shield + $vh->accessories + $vh->rto + $vh->insurance;

            $tcsrate = self::tcsConditions();

            if (CommonHelper::enumValueById($vh->permit1_id) == 'PRIVATE')

                $vh->total_addition -= $vh->rto_tape;

            if ($vh->invoice >= $tcsrate['tcs_applied_from']) {

                $vh->tcs = ($tcsrate['tcs_rate'] * $vh->invoice) / 100;

                $vh->total_addition += $vh->tcs;
            }

            $vh->onroad = $vh->exshowroom + $vh->total_addition - $vh->total_discount;

            $vh->save();
        }

        $vhs = XCsd::get();

        foreach ($vhs as $vh) {

            $ov = XVehicleMaster::find($vh->vehicle_id);

            $ins = XInsIRDA::where('vehicle_id', $vh->vehicle_id)->where('permit', 'CSD')->first();

            $vh->insurance = (isset($ins->total)) ? $ins->total : 0;

            $vh->rto = $ov->rto;

            $vh->c2d = $vh->c2d + $vh->insurance + $vh->rto;

            $vh->save();
        }





        //self::pricelist();

    }

    public static function pricelist()

    {

        print_r("S.No.|Segment|Model|Vairant|Code|Display Name|Permit|ExShowroom|Incidental|FasTag|TRC|RTO Tape|RSA|Shield|Accessories|RTO|Insurance|TCS|TotalAdditions|RSA Disc|Shield Disc|Accessories Disc|Cash Disc|Total Discount|Invoice|Onroad");

        $data = XVehicleMaster::get();

        $i = 1;

        foreach ($data as $vh) {



            print_r("<br>" . $i . "|" . Commonhelper::enumValueById($vh->segment_id) . "|" . $vh->custom_model . "|" . $vh->custom_variant .  "|" . $vh->code . "|" . $vh->display_name . "|" . Commonhelper::enumValueById($vh->permit1_id) . "|" . $vh->exshowroom . "|" . $vh->incidental_charges . "|" . $vh->fastag . "|" . $vh->trc . "|" . $vh->rto_tape . "|" . $vh->rsa . "|" . $vh->shield . "|" . $vh->accessories . "|" . $vh->rto . "|" . $vh->insurance . "|" . $vh->tcs . "|" . $vh->total_addition . "|" . $vh->rsa_discount . "|" . $vh->shield_discount . "|" . $vh->accessories_discount . "|" . $vh->cash_discount . "|" . $vh->total_discount . "|" . $vh->invoice . "|" . $vh->onroad);

            $i++;
        }
    }



    public static function purgeData($tr = array())

    {

        Xessories::truncate();

        XCorp::truncate();

        XCsd::truncate();

        XchangeNloyalty::truncate();

        XInsExtra::truncate();

        XInsIRDA::truncate();

        XSchemeMaster::truncate();

        // XVehicleMaster::truncate();

        XRSA::truncate();

        XRtoData::truncate();

        XShield::truncate();

        return;

        // if (empty($tr)) {

        //     Xessories::truncate();

        //     XCorp::truncate();

        //     XCsd::truncate();

        //     XchangeNloyalty::truncate();

        //     XInsExtra::truncate();

        //     XInsIRDA::truncate();

        //     XSchemeMaster::truncate();

        //     XPriceMaster::truncate();

        //     XRSA::truncate();

        //     XRtoData::truncate();

        //     XShield::truncate();

        // } else {

        //     foreach ($tr as $t) {

        //         if ($t == 'apack')

        //             Xessories::truncate();

        //         else if ($t == 'csd')

        //             XCsd::truncate();

        //         else if ($t == 'corpenl') {

        //             XchangeNloyalty::truncate();

        //             XCorp::truncate();

        //             XSchemeMaster::truncate();

        //         } else if ($t == 'ins') {

        //             XInsExtra::truncate();

        //             XInsIRDA::truncate();

        //         } else if ($t == 'rsa') {

        //             XRSA::truncate();

        //             XShield::truncate();

        //         } else if ($t == 'rto')

        //             XRtoData::truncate();

        //     }

        //}

    }



    public static function purgeDisc()

    {

        XReporting::truncate();

        XApprovers::truncate();

        Xl_Reporting::truncate();
    }



    public static function calcIns($vid, $permit, $invoice)

    {

        $rto = XRtoData::where('vehicle_id', $vid)->first();
    }
}
