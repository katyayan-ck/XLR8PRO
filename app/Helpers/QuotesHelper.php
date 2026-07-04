<?php

namespace App\Helpers;

use App\User;
use Auth;
use Illuminate\Support\Facades\File;

use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\Quote;
use App\Models\Person;
use App\Models\Vehicle;
use App\Models\XVehicleMaster;

use Barryvdh\DomPDF\Facade\Pdf;

use VehicleHelper;
use PricingHelper;
use XpricingHelper;
use ExtrasHelper;
use CommonHelper;
use ChatHelper;
use NotificationHelper;


use Carbon\Carbon;

class QuotesHelper
{

    public static function pdfQuote($qid)
    {
        $uid = Auth::User()->id;
        $data = self::fetchQuote($qid, $uid);
        // dd($data);
        $dd = Carbon::parse($data['updated_at']);
        $data['date'] = $dd->format('d/m/Y');
        $fndate =  $dd->format('d-m-Y');
        $fname = 'QuoteNo' . $qid . '---' . $fndate . '.pdf';
        $fpath = public_path() . '/pdf/' . $fname;
        $pdf =  Pdf::loadView('pdf.quote', compact('data'));
        if (File::exists($fpath))
            File::delete($fpath);
        $pdf->save(public_path() . '/pdf/' . $fname);
        $pdf->download($fname);
        //'/myfile.html')->save('/path-to/my_stored_file.pdf')->stream('download.pdf');

    }

    public static function fetchQuoteLink($qid)
    {
        $data = array();
        $uid = Auth::User()->id;
        $data = self::fetchQuote($qid, $uid);
        //dd($data);
        $dd = Carbon::parse($data['updated_at']);
        $data['date'] = $dd->format('d/m/Y');
        $fndate =  $dd->format('dmY');
        if (isset($data['standard']['exshowroom']))
            $fname = 'Quote-' . $qid . '-' . $fndate . '.pdf';
        else
            $fname = 'QuoteCSD-' . $qid . '-' . $fndate . '.pdf';
        $fpath = public_path() . '/pdf/' . $fname;

        //print_r("Looking for file : " . $fpath);
        $lnk = false;
        if (File::exists($fpath)) {
            //print_r("......Found....Sending.....");
            $lnk = true;
        } else {
            //print_r("......Not Found....Crating.....");
            if (isset($data['standard']['exshowroom']))
                $pdf =  Pdf::loadView('pdf.quote', compact('data'));
            else {

                $pdf =  Pdf::loadView('pdf.quoteCSD', compact('data'));
            }

            $pdf->save($fpath);
            $lnk = true;
        }
        $fpath = url('/pdf/' . $fname);
        if ($lnk) {
            $data['msg'] = "*Welcome to Bikaner Motors Private Limited [BMPL]* \n Hi *" . $data['enq_data']['cust_name'] . "*, \n Thanks for showing insterest in our service. You can download your quote for *" .
                $data['vehicle_data']['custom_model'] . ' ' . $data['vehicle_data']['vehicle'] . ' ' . $data['vehicle_data']['transmission'] . ' ' . $data['vehicle_data']['fuel'] . ' ' . $data['vehicle_data']['seating'] . " seater* from below link : \n " . $fpath;
            $data['file'] = $fname;
            $data['link'] = $fpath;
            //print_r("Message : $msg");
            return $data;
        } else
            return false;
    }

    public static function fetchINVLink($qid)
    {
        $uid = Auth::User()->id;
        $data = self::fetchQuote($qid, $uid);
        $dd = Carbon::parse($data['updated_at']);
        $data['date'] = $dd->format('d/m/Y');
        $fndate =  $dd->format('dmY');
        $fname = 'Quote' . $qid . '---' . $fndate . '.pdf';
        if (isset($data['standard']['exshowroom']))
            $fname = 'Invoice-' . $qid . '-' . $fndate . '.pdf';
        else
            $fname = 'QuoteCSD-' . $qid . '-' . $fndate . '.pdf';
        //print_r("Looking for file : " . $fpath);
        $lnk = false;
        if (File::exists($fpath)) {
            //print_r("......Found....Sending.....");
            $lnk = true;
        } else {
            //print_r("......Not Found....Crating.....");
            if (isset($data['standard']['exshowroom']))
                $pdf =  Pdf::loadView('pdf.inv', compact('data'));
            else {

                $pdf =  Pdf::loadView('pdf.quoteCSD', compact('data'));
            }
            $fpath = public_path() . '/pdf/' . $fname;
            $pdf->save($fpath);
            $lnk = true;
        }
        $fpath = url('/pdf/' . $fname);
        if ($lnk) {
            $data['msg'] = "*Welcome to Bikaner Motors Private Limited [BMPL]* \n Hi *" . $data['enq_data']['cust_name'] . "*, \n Thanks for showing insterest in our service. You can download your quote for *" .
                $data['vehicle_data']['custom_model'] . ' ' . $data['vehicle_data']['vehicle'] . ' ' . $data['vehicle_data']['transmission'] . ' ' . $data['vehicle_data']['fuel'] . ' ' . $data['vehicle_data']['seating'] . " seater* from below link : \n " . $fpath;
            //print_r("Message : $msg");
            $data['file'] = $fname;
            $data['link'] = $fpath;
            //print_r("Message : $msg");
            return $data;
        } else
            return false;
    }

    public static function getStdQuote($plid)
    {
        $data = PricingHelper::getStdQuote($plid);
        return $data;
    }

    public static function getCreator($id)
    {
        //print_r("<br><br>In QuotesHelper::getCreator with id : $id");
        $fsc_data = User::find($id);
        //print_r("<br><br>User of $id found :");
        //print_r($fsc_data->toarray());
        if (isset($fsc_data->person_id))
            $fsc_pdata = Person::find($fsc_data->person_id)->toarray();
        else
            $fsc_pdata = array('firstname' => 'NA', 'lastname' => 'NA');

        //print_r("<br><br>Person of found :");
        //print_r($fsc_pdata);
        $fsc = "[" . $fsc_data->emp_code . "] " . $fsc_pdata['firstname'] . " - " . $fsc_data->mobile;

        return $fsc;
    }

    public static function modifiable($qid, $uid)
    {
        $mq = Quotation::find($qid);
        $stt = $mq->status;
        $rev = Quote::where('quote_id', $qid)->orderBy('revision', 'DESC')->first();
        if ($rev->action_by == $uid  && ($stt == 3 || $stt == 4 || $stt == 5))
            return true;
        else
            return false;
    }

    public static function getMyQuotes($uid, $type)
    {
        //print_r("Fetching quotes for $uid");
        if ($type == "A")
            $qlist = Quotation::where('fsc_id', $uid)->orWhere('assigned_to', $uid)->orderBy('updated_at', 'desc')->get();
        elseif ($type == "R") //Raised Quotes
            $qlist = Quotation::where('fsc_id', $uid)->Where('assigned_to', "<>", $uid)->Where('status', '<', 6)->orderBy('updated_at', 'desc')->get();
        elseif ($type == "AP") //Approved
            $qlist = Quotation::where('fsc_id', $uid)->Where('assigned_to', $uid)->orderBy('updated_at', 'desc')->get();
        elseif ($type == "C") //Closed Quotes
            $qlist = Quotation::where('fsc_id', $uid)->Where('status', '>', 5)->orderBy('updated_at', 'desc')->get();
        elseif ($type == "AS") //Assigned Quotes
            $qlist = Quotation::where('fsc_id', '<>', $uid)->Where('assigned_to', $uid)->Where('status', "<", 6)->orderBy('updated_at', 'desc')->get();
        elseif ($type == "ES" || $type == "T") //Escalated or Team
            $qlist = Quotation::where('l1_approver', $uid)->orWhere('l2_approver', $uid)->orWhere('l3_approver', $uid)->orWhere('l4_approver', $uid)->orWhere('l5_approver', $uid)->orderBy('updated_at', 'desc')->get();
        $list = array();
        if (isset($qlist)) {
            //print_r($qlist->toarray());

            foreach ($qlist as $qtm) {
                //print_r("<br><br>QuoteMaster : ");//print_r($qtm->toarray());
                $add = false;
                if ($type == "ES" || $type == "T") {
                    $app = array(0 => $qtm->fsc_id, 1 => $qtm->l1_approver, 2 => $qtm->l2_approver, 3 => $qtm->l3_approver, 4 => $qtm->l4_approver, 5 => $qtm->l5_approver);
                    for ($i = 0; $i < 6; $i++) {
                        if ($app[$i] == $uid)
                            break;
                    }
                    $ina = array();
                    if ($type == "ES") {
                        for ($j = $i + 1; $j < 6; $j++) {
                            if ($qtm->assigned_to == $app[$j])
                                $add = true;
                        }
                    } elseif ($type == "T") {
                        for ($j = $i - 1; $j >= 0; $j--) {
                            if ($qtm->assigned_to == $app[$j])
                                $add = true;
                        }
                    }
                } else
                    $add = true;

                if ($add) {
                    $enq = Enquiry::where("enq_id", $qtm->enq_id)->first();
                    //print_r("<br><br>Enq : ");//print_r($enq->toarray());
                    $per = Person::find($enq->person_id);
                    $fsc_id = $qtm->created_by;
                    $fsc = QuotesHelper::getCreator($fsc_id);
                    $app_id = $qtm->assigned_to;
                    $app = QuotesHelper::getCreator($app_id);
                    $vh = XVehicleMaster::find($enq->vehicle_id);

                    $rev = Quote::where('quote_id', $qtm->id)->orderBy('revision', 'DESC')->first();
                    //print_r("<br><br>Quote : ");//print_r($rev->toarray());
                    $qdata = array();
                    $qdata['quote_id'] = $qtm->id;
                    $qdata['enq_id'] = $enq->enq_id;
                    $qdata['custom_model'] = $vh->custom_model;
                    $qdata['vehicle'] = $vh->display_name;
                    $qdata['onroad'] = $rev->onroad;
                    $qdata['customer'] = $per->firstname;
                    $qdata['cust_mobile'] = $per->mobile;
                    $qdata['cust_pin'] = $per->pincode;
                    $qdata['revision'] = $rev->revision;
                    $qdata['creator_id'] = $fsc_id;
                    $qdata['created_by'] = $fsc;
                    $qdata['updated_by'] = $rev->action_by;
                    $qdata['approver_id'] = $app_id;
                    $qdata['assigned_to'] = $app;
                    $qdata['stt'] = $qtm->status;
                    if ($qtm->status == 1)
                        $qdata['status'] = '<span class="badge badge-pill badge-dark mb-1">Pending</span>';
                    elseif ($qtm->status == 2)
                        $qdata['status'] = '<span class="badge badge-pill badge-secondary mb-1">UnderProcess</span>';
                    elseif ($qtm->status == 3)
                        $qdata['status'] = '<span class="badge badge-pill badge-success mb-1">Approved</span>';
                    elseif ($qtm->status == 4)
                        $qdata['status'] = '<span class="badge badge-pill badge-primary mb-1">Modified</span>';
                    elseif ($qtm->status == 5)
                        $qdata['status'] = '<span class="badge badge-pill badge-info mb-1">Escalated</span>';
                    elseif ($qtm->status == 6)
                        $qdata['status'] = '<span class="badge badge-pill badge-light mb-1">Accepted</span>';
                    elseif ($qtm->status == 7)
                        $qdata['status'] = '<span class="badge badge-pill badge-warning mb-1">Rejected</span>';
                    elseif ($qtm->status == 8)
                        $qdata['status'] = '<span class="badge badge-pill badge-success mb-1">Sold</span>';
                    elseif ($qtm->status == 9)
                        $qdata['status'] = '<span class="badge badge-pill badge-danger mb-1">Cancelled</span>';
                    $list[] = $qdata;
                }
            }
        }
        return $list;
    }

    // public static function getMyQuotes($uid, $type)//Legacy Code
    // {
    //     //print_r("Fetching quotes for $uid");
    //     if ($type == "A")
    //         $qlist = Quotation::where('fsc_id', $uid)->orWhere('assigned_to', $uid)->orderBy('updated_at', 'desc')->get();
    //     elseif ($type == "R") //Raised Quotes
    //         $qlist = Quotation::where('fsc_id', $uid)->Where('assigned_to', "<>", $uid)->Where('status', '<', 6)->orderBy('updated_at', 'desc')->get();
    //     elseif ($type == "AP") //Approved
    //         $qlist = Quotation::where('fsc_id', $uid)->Where('assigned_to', $uid)->orderBy('updated_at', 'desc')->get();
    //     elseif ($type == "C") //Closed Quotes
    //         $qlist = Quotation::where('fsc_id', $uid)->Where('status', '>', 5)->orderBy('updated_at', 'desc')->get();
    //     elseif ($type == "AS") //Assigned Quotes
    //         $qlist = Quotation::where('fsc_id', '<>', $uid)->Where('assigned_to', $uid)->Where('status', "<", 6)->orderBy('updated_at', 'desc')->get();
    //     elseif ($type == "ES" || $type == "T") //Escalated or Team
    //         $qlist = Quotation::where('l1_approver', $uid)->orWhere('l2_approver', $uid)->orWhere('l3_approver', $uid)->orWhere('l4_approver', $uid)->orWhere('l5_approver', $uid)->orderBy('updated_at', 'desc')->get();
    //     $list = array();
    //     if (isset($qlist)) {
    //         //print_r($qlist->toarray());

    //         foreach ($qlist as $qtm) {
    //             //print_r("<br><br>QuoteMaster : ");//print_r($qtm->toarray());
    //             $add = false;
    //             if ($type == "ES" || $type == "T") {
    //                 $app = array(0 => $qtm->fsc_id, 1 => $qtm->l1_approver, 2 => $qtm->l2_approver, 3 => $qtm->l3_approver, 4 => $qtm->l4_approver, 5 => $qtm->l5_approver);
    //                 for ($i = 0; $i < 6; $i++) {
    //                     if ($app[$i] == $uid)
    //                         break;
    //                 }
    //                 $ina = array();
    //                 if ($type == "ES") {
    //                     for ($j = $i + 1; $j < 6; $j++) {
    //                         if ($qtm->assigned_to == $app[$j])
    //                             $add = true;
    //                     }
    //                 } elseif ($type == "T") {
    //                     for ($j = $i - 1; $j >= 0; $j--) {
    //                         if ($qtm->assigned_to == $app[$j])
    //                             $add = true;
    //                     }
    //                 }
    //             } else
    //                 $add = true;

    //             if ($add) {
    //                 $enq = Enquiry::where("enq_id", $qtm->enq_id)->first();
    //                 //print_r("<br><br>Enq : ");//print_r($enq->toarray());
    //                 $per = Person::find($enq->person_id);
    //                 $fsc_id = $qtm->created_by;
    //                 $fsc = QuotesHelper::getCreator($fsc_id);
    //                 $app_id = $qtm->assigned_to;
    //                 $app = QuotesHelper::getCreator($app_id);
    //                 $vh = Vehicle::find($enq->vehicle_id);

    //                 $rev = Quote::where('quote_id', $qtm->id)->orderBy('revision', 'DESC')->first();
    //                 //print_r("<br><br>Quote : ");//print_r($rev->toarray());
    //                 $qdata = array();
    //                 $qdata['quote_id'] = $qtm->id;
    //                 $qdata['enq_id'] = $enq->enq_id;
    //                 $qdata['custom_model'] = $vh->cm1;
    //                 $qdata['vehicle'] = $vh->local_name;
    //                 $qdata['onroad'] = $rev->onroad;
    //                 $qdata['customer'] = $per->firstname;
    //                 $qdata['cust_mobile'] = $per->mobile;
    //                 $qdata['cust_pin'] = $per->pincode;
    //                 $qdata['revision'] = $rev->revision;
    //                 $qdata['creator_id'] = $fsc_id;
    //                 $qdata['created_by'] = $fsc;
    //                 $qdata['updated_by'] = $rev->action_by;
    //                 $qdata['approver_id'] = $app_id;
    //                 $qdata['assigned_to'] = $app;
    //                 $qdata['stt'] = $qtm->status;
    //                 if ($qtm->status == 1)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-dark mb-1">Pending</span>';
    //                 elseif ($qtm->status == 2)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-secondary mb-1">UnderProcess</span>';
    //                 elseif ($qtm->status == 3)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-success mb-1">Approved</span>';
    //                 elseif ($qtm->status == 4)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-primary mb-1">Modified</span>';
    //                 elseif ($qtm->status == 5)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-info mb-1">Escalated</span>';
    //                 elseif ($qtm->status == 6)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-light mb-1">Accepted</span>';
    //                 elseif ($qtm->status == 7)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-warning mb-1">Rejected</span>';
    //                 elseif ($qtm->status == 8)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-success mb-1">Sold</span>';
    //                 elseif ($qtm->status == 9)
    //                     $qdata['status'] = '<span class="badge badge-pill badge-danger mb-1">Cancelled</span>';
    //                 $list[] = $qdata;
    //             }
    //         }
    //     }
    //     return $list;
    // }

    /* public static function getTeamQuotes($uid)
			{
			$qlist = Quotation::get();
			$list = array();
			foreach($qlist as $qtm)
			{
			if(($qtm->l1_approver==$uid || $qtm->l2_approver==$uid || $qtm->l3_approver==$uid || $qtm->l4_approver==$uid || $qtm->l5_approver==$uid) && ($qtm->assigned_to !=  $uid &&  $qtm->fsc_id !=$uid))
			{
			$enq = Enquiry::where("enq_id",$qtm->enq_id)->first();
			$per = Person::find($enq->person_id);
			$vh = Vehicle::find($enq->vehicle_id);
			$fsc_id = $qtm->created_by;
			$fsc_data = User::find($fsc_id);
			$fsc_pdata = Person::find($fsc_data->person_id);
			$fsc = "[".$fsc_data->emp_code."] ".$fsc_pdata->firstname." - ".$fsc_data->mobile;

			$app_id = $qtm->assigned_to;
			$app_data = User::find($app_id);
			$app_pdata = Person::find($app_data->person_id);
			$app = "[".$app_data->emp_code."] ".$app_pdata->firstname." - ".$app_data->mobile;
			$rev = Quote::where('quote_id',$qtm->id)->orderBy('revision','DESC')->first();
			$qdata = array();
			$qdata['quote_id'] = $qtm->id;
			$qdata['enq_id'] = $enq->enq_id;
			$qdata['custom_model'] = $vh->cm1;
			$qdata['vehicle'] = $vh->local_name;
			$qdata['onroad'] = $rev->onroad;
			$qdata['customer'] = $per->firstname;
			$qdata['cust_mobile'] = $per->mobile;
			$qdata['cust_pin'] = $per->pincode;
			$qdata['revision'] = $rev->revision;
			$qdata['created_by'] = $fsc;
			$qdata['assigned_to'] = $app;
			if($qtm->status == 1)
			$qdata['status'] = "Pending";
			elseif($qtm->status == 2)
			$qdata['status'] = "UnderProcess";
			elseif($qtm->status == 3)
			$qdata['status'] = "Approved";
			elseif($qtm->status == 4)
			$qdata['status'] = "Modified";
			elseif($qtm->status == 5)
			$qdata['status'] = "Escalated";
			elseif($qtm->status == 6)
			$qdata['status'] = "Accepted";
			elseif($qtm->status == 7)
			$qdata['status'] = "Rejected";
			elseif($qtm->status == 8)
			$qdata['status'] = "Sold";
			elseif($qtm->status == 9)
			$qdata['status'] = "Cancelled";
			$list[] = $qdata;
			}
			}
			return $list;
			}
		*/


    public static function checkQuote($sqt, $cqt)
    {
        return true;
    }



    public static function createQuote($sq, $cq, $enqid, $cid)
    {
        //print_r("<br><br>Creating Quote :");
        //print_r("<br>SQ:");
        //print_r($sq);
        //print_r("<br>CQ:");
        //print_r($cq);
        $sqs = json_encode($sq);
        $cqs = json_encode($cq);
        //print_r("<br><br> Enq id : $enqid, CID : $cid, Standard Quote : ");
        //print_r($sq);
        //print_r("<br><br> Custom Quote : ");
        //print_r($cq);
        if (isset($cq['onroad']))
            $cor = $cq['onroad'];
        elseif (isset($cq['c2d']))
            $cor = $cq['c2d'];
        $rtval = false;
        //print_r("<br>Enqid : $enqid , <br>");
        //print_r($sq);
        // if (isset($sq["vhid"]))
        //     $appr = XpricingHelper::findApprover($erec->vehicle_id, 1);//VehicleHelper::findApprover($erec->vehicle_id, 1);
        // else {
        //     $erec = Enquiry::where('enq_id', $enqid)->first();
        //     //print_r($erec->toarray());
        //     $appr = XpricingHelper::findApprover($erec->vehicle_id, 1);//VehicleHelper::findApprover($erec->vehicle_id, 1);
        // }
        $mqrec = Quotation::where('enq_id', $enqid)->first();
        if (!$mqrec) {
            $mqrec = new Quotation;
            $mqrec->enq_id = $enqid;
            $mqrec->pl_id = 11; //$sq["plid"];
            $mqrec->standard = $sqs;
            $mqrec->requested = $cqs;
            $mqrec->fsc_id = $cid;
            $mqrec->assigned_to = $mqrec->l1_approver = 55; //$appr[1]['uid'];
            $mqrec->l2_approver = 58; //$appr[2]['uid'];
            $mqrec->l3_approver = 59; //$appr[3]['uid'];
            $mqrec->l4_approver = 60; //$appr[4]['uid'];
            $mqrec->l5_approver = 64; //$appr[5]['uid'];
            //print_r($mqrec->toarray());
            $mqrec->save();
            $qrec = new Quote;
            $qrec->quote_id = $mqrec->id;
            $qrec->action = 0;
            $qrec->action_by = $cid;
            $qrec->onroad = $cor;
            $qrec->revision = 0;
            $qrec->requested = $cqs;
            $qrec->save();

            if ($qrec) {
                $content = self::getCreator($cid) . " created the quote";
                ChatHelper::add_communication(1, "Quote Created", $content, $mqrec->id);

                NotificationHelper::notify($mqrec->assigned_to, $content, "New Quote Assigned. Quote Id : " . $mqrec->id, 1, $mqrec->id, "N");
                $rtval = array(
                    'message' => 'Quote created successfully',
                    'success' => true,
                    'quoteid' => $mqrec->id
                );
            } else {
                $rtval = array(
                    'message' => 'Quote creation failed',
                    'success' => false,
                    'quoteid' => null
                );
            }
        } else {
            $tqrec = Quote::where('quote_id', $mqrec->id)->where('status', 1)->orderby('revision', 'DESC')->first();
            if (!$tqrec) {
                $qrec = new Quote;
                $qrec->quote_id = $mqrec->id;
                $qrec->action = 0;
                $qrec->action_by = $cid;
                $qrec->onroad = $cor;
                $qrec->revision = 0;
                $qrec->requested = $cqs;
                $qrec->save();
            } else {
                if ((isset($cq['onroad']) && $tqrec->onroad == $cq['onroad'])
                    || (isset($cq['c2d']) && $tqrec->onroad == $cq['c2d'])
                ) {
                    $rtval = array(
                        'message' => 'Duplicate Submission Attempt. No data changed',
                        'success' => false,
                        'quoteid' => null
                    );
                } else {
                    $qrec = new Quote;
                    $qrec->quote_id = $mqrec->id;
                    $qrec->action = 0;
                    $qrec->action_by = $cid;
                    $qrec->onroad = $cor;
                    $qrec->revision = $tqrec->revision + 1;
                    $qrec->requested = $cqs;
                    $qrec->save();
                    $commid = ChatHelper::get_commid(1, $mqrec->id, "Quote Created");
                    $rem = self::getCreator($cid) . " revised the quote to new onroad value : " . $cor;
                    Quotation::find($mqrec->id)->update(['assigned_to' => $appr[1]["uid"], 'status' => 2]);
                    $fup = ChatHelper::add_followup($commid, $rem, "Quote Revised");
                    NotificationHelper::notify($mqrec->assigned_to, "Quote Revised. Quote Id : " . $mqrec->id, $rem, 1, $mqrec->id, "N");
                    $rtval = array(
                        'message' => 'Quote Revised with new values. Revision No. # ' . $qrec->revision,
                        'success' => true,
                        'quoteid' => $mqrec->id
                    );
                }
            }
        }
        return $rtval;
    }

    public static function get_quote($id)
    {
        $qrec = Quotation::find($id);
        $enq = Enquiry::where('enq_id', $qrec->enq_id)->first();
        //print_r($enq->toarray());//print_r("<br><br>");
        $person = Person::find($enq->person_id);
        //print_r($person->toarray());//print_r("<br><br>");
        $vehicle = Vehicle::find($enq->vehicle_id);
        //print_r($vehicle->toarray());//print_r("<br><br>");
        $fsc = User::find($qrec->fsc_id);
        //print_r($fsc->toarray());//print_r("<br><br>");
        $quote = array('enq_id' => $qrec->enq_id, 'person' => $person->firstname, 'mobile' => $person->mobile, 'created' =>  Carbon::parse($qrec->created_at)->format('Y-m-d H:i:s'), 'updated_at' => Carbon::parse($qrec->updated_at)->format('Y-m-d H:i:s'), 'vehicle' => $vehicle->cm1 . ' ' . $vehicle->local_name, 'vehicle_id' => $enq->vehicle_id, 'fsc' => $fsc->name,  'fsc_id' => $qrec->fsc_id, 'level' => $qrec->level, 'approver' => '', 'approver_id' => '');
        $stan = array();
        $pricing = explode("|||", $qrec->standard);
        foreach ($pricing as $itm) {
            $tmp = explode("--", $itm);
            $stan[$tmp[0]] = $tmp[1];
        }
        $quote['standard'] = $stan;
        $quote['history'] = self::get_quote_history($id, $quote['standard'], false);
        return $quote;
    }

    public static function get_quote_history($id, $base, $latest = true)
    {
        //print_r("<br>In History Id : $id, <br>");//print_r($base);
        $quotes = Quote::where('quote_id', $id)->orderBy('created_at', 'DESC')->get();
        //print_r("<br>Quotes : <br>");//print_r($quotes);
        $history = array();
        foreach ($quotes as $qrec) {
            $requested = $remarks = $proposed = $reply = array();
            $temp = explode("|||", $qrec->requested);
            foreach ($temp as $itm) {
                $tmp = explode("--", $itm);
                $requested[$tmp[0]] = $tmp[1];
            }
            $temp = explode("|||", $qrec->remarks);
            foreach ($temp as $itm) {
                $tmp = explode("--", $itm);
                $remarks[$tmp[0]] = $tmp[1];
            }
            if ($qrec->proposed) {
                $temp = explode("|||", $qrec->proposed);

                foreach ($temp as $itm) {
                    $tmp = explode("--", $itm);
                    $proposed[$tmp[0]] = $tmp[1];
                }
            }
            if ($qrec->reply) {
                $temp = explode("|||", $qrec->reply);
                foreach ($temp as $itm) {
                    $tmp = explode("--", $itm);
                    $reply[$tmp[0]] = $tmp[1];
                }
            }
            $history[$qrec->id] = array('id' => $qrec->id, 'status' => $qrec->status, 'standard' => $base, 'requested' => $requested, 'remarks' => $remarks, 'proposed' => $proposed, 'reply' => $reply, 'disscussion' => ChatHelper::get_communication('Quote', $qrec->id));
        }
        //dd($history);
        return $history;
    }

    public static function fetchQuote($qid, $uid, $mod = false)
    {
        $qrec = Quotation::find($qid);

        if ($qrec) {
            $appa = array(0 => $qrec->fsc_id, 1 => $qrec->l1_approver, 2 => $qrec->l2_approver, 3 => $qrec->l3_approver, 4 => $qrec->l4_approver, 5 => $qrec->l5_approver);
            for ($i = 0; $i < 6; $i++) {
                if ($appa[$i] == $qrec->assigned_to)
                    break;
            }
            for ($cur = 0; $cur < 6; $cur++) {
                if ($appa[$cur] == $uid)
                    break;
            }
            $rocq = json_decode($qrec->requested);
            //print_r($rocq);
            //print_r($qrec->toarray());//print_r("<br><br>");
            $enq = Enquiry::where('enq_id', $qrec->enq_id)->first();
            //print_r($enq->toarray());//print_r("<br><br>");
            $person = Person::find($enq->person_id);
            //print_r($person->toarray());//print_r("<br><br>");
            $vehicle = XVehicleMaster::find($enq->vehicle_id);
            //print_r($vehicle->toarray());//print_r("<br><br>");
            $fsc = User::find($qrec->fsc_id);
            //print_r($fsc->toarray());//print_r("<br><br>");
            $appd = array(
                array('uid' => 59, 'level' => 1, 'dlimit' => 1500, 'olimit' => 65),
                array('uid' => 60, 'level' => 2, 'dlimit' => 2000, 'olimit' => 65),
                array('uid' => 61, 'level' => 3, 'dlimit' => 2500, 'olimit' => 70),
                array('uid' => 62, 'level' => 4, 'dlimit' => 0, 'olimit' => 75),
                array('uid' => 63, 'level' => 5, 'dlimit' => 0, 'olimit' => 65)
            ); //XpricingHelper::findApprover($enq->vehicle_id, 1);
            //dd($appd);
            $approver_rec = false;

            $sqo = json_decode($qrec->standard, true);
            //print_r("<BR><BR>SQ in Fetch Quote :");
            //print_r($sqo);
            //dd($sqo);
            $fsc_id = $qrec->fsc_id;
            $fsc = self::getCreator($fsc_id);
            //print_r("<br>FSC $fsc_id : ");//print_r($fsc);
            $app_id = $qrec->assigned_to;
            $app = self::getCreator($app_id);
            //print_r("<br>Approver $app_id : ");//print_r($app);
            foreach ($appd as $aprec) {
                if ($aprec["uid"] == $app_id)
                    $approver_rec = $aprec;
                elseif ($mod == true && $aprec["uid"] == $uid)
                    $approver_rec = $aprec;
                elseif (($aprec["uid"] == $uid) && ($cur > $i))
                    $approver_rec = $aprec;
            }

            if ($qrec->status != 4) {
                $lq = Quote::where('quote_id', $qrec->id)->where('status', 1)->orderby('revision', 'DESC')->first();
                $cq = json_decode($lq->requested, true);
                $aq = false;
            } else {

                $alq = Quote::where('quote_id', $qrec->id)->where('status', 1)->orderby('revision', 'DESC')->first();
                $aq = json_decode($alq->requested, true);
                $lq = Quote::where('quote_id', $qrec->id)->where('status', 1)->where('revision', $alq->revision - 1)->first();
                $cq = json_decode($lq->requested, true);
            }
            //dd($cq);
            //print_r("<BR><BR>CQ in Fetch Quote :");
            //print_r($cq);
            //print_r("<BR><BR>AQ in Fetch Quote :");
            //print_r($aq);
            //die();
            $vd = array("vid" => $enq->vehicle_id, "custom_model" => $vehicle->custom_model, "vehicle" => $vehicle->display_name, "transmission" => CommonHelper::enumValueById($vehicle->transmission_type), "seating" => $vehicle->seating, "fuel" => CommonHelper::enumValueById($vehicle->fuel_type_id));
            if (isset($cq['c2d']) && !empty($cq['c2d']) && $cq['c2d'] != false) {
                $lbl = $sq = $cql = $qd = $qr = array("lsorder" => null, "csd_charges" => null, "dd_amt" => null, "tcs" => null, "trc" => null, "fasttag" => null, "rsa" => null, "shield" => null, "insurance" => null, "rto" => null, "apack" => null, "extra_disc" => null, "c2d" => null);
                $ed = array("enq_id" => $qrec->enq_id, "cust_id" => $enq->person_id, "cust_name" => $person->firstname, "mobile" => $person->mobile, "email" => $person->email, "pincode" => $person->pincode);
                $lbl["lsorder"] = "Invoice Amount (LS Order Amount)";
                $lbl["csd_charges"] = "CSD Charge";
                $lbl["dd_amt"] = "DD Amount (Inclusive of CSD Charge @ 0.5%)";
                $lbl["tcs"] = "TCS";
                $lbl["trc"] = "TRC";
                $lbl["fasttag"] = "Fastag";
                $lbl["rsa"] = "RSA";
                $lbl["shield"] = "Shield [Extended Warranty]";
                $lbl["insurance"] = "Insurance";
                $lbl["rto"] = "RTO";
                $lbl["apack"] = "Accessories Pack";
                $lbl["extra_disc"] = "Extra Discount";
                $lbl["c2d"] = "Amount Payable by Customer to Dealer ";


                $sq["rsa"] = $cql["rsa"] = $sqo["rsa_amount"];
                $sq["shield"] = $cql["shield"] = $sqo["shield_amount"];
                $sq["insurance"] = $sqo["ins_amount"];
                $sq["rto"] = $cql["rto"] = $sqo["rto_amount"];
                $sq["apack"] = $cql["apack"] = $sqo["apack_amount"];
                $sq["c2d"] = $sqo["c2d"];
                $cql["lsorder"] = $sq["lsorder"] = $sqo["lsorder"];
                $sq["csd_charges"] = $cql["csd_charges"] = $sqo["csd_charges"];
                $sq["dd_amt"] = $cql["dd_amt"] = $sqo["dd_amt"];
                $qd["lsorder"] = "FIXED";
                $qd["dd_amt"] = "FIXED";
                $qd["csd_charges"] = "FIXED";
                $sq["fasttag"] = $cql["fasttag"] = $sqo["fastag"];
                $qd["fasttag"] = "FIXED";
                $sq["trc"] = $cql["trc"] = $sqo["trc"];
                $qd["trc"] = "FIXED";
                $sq["tcs"] = $sqo["tcs"];
                $cql["tcs"] = $rocq["tcs"];
                $qd["tcs"] = "FIXED";
                $qd["rsa"] = $sqo['rsa_type'];
                $qd["shield"] = $sqo['shield_type'];
                $qd["extra_disc"] = "Additional Discount Requested";
                $qd["c2d"] = "Amount to be paid directly to Dealer by Customer";
                $qd['rto'] = $sqo['rto_type'];


                $insdt = "Details Not Avaialble";
                if ($sqo["ins_type"] == "STANDARD")
                    $insdt = "Selected Default Insurance with Basic + NilDep + Consumables @ " . $sqo["ins_amount"];
                elseif ($sqo["ins_type"] == "ADDON") {
                    $insdt = "Selected Default Insurance with Basic + NilDep + Consumables with addons [";
                    $iadd = array();
                    foreach ($sqo["ins_details"]["addons"] as $iad)
                        $iadd[] = $iad["name"] . " @ " . $iad["price"];
                    $insdt .= implode(", ", $iadd) . "] @ " . $sqo["ins_amount"];
                } elseif ($sqo["ins_type"] == "COMBO")
                    $insdt = "Selected Combo " . $cq["ins_details"]["combo"]["name"] . " having " . $sqo["ins_details"]["combo"]["head"] . " @ " . $sqo["ins_details"]["combo"]["price"];
                elseif ($sqo["ins_type"] == "SELF")
                    $insdt = "Insurance Removed as Customer chose to get insurance by self";

                $apkdt = "";
                if (isset($sqo["apack_details"]["core"])) {
                    $apkdt = "Essential Items : <br> ";
                    $apl = array();
                    foreach ($sqo["apack_details"]["core"] as $iap)
                        $apl[] = $iap["name"] . " @ " . $iap["price"] . "<br>";
                    $apkdt .= implode(", ", $apl) . " <br> ";
                }
                if (isset($sqo["apack_details"]["extra"]) && count($sqo["apack_details"]["extra"]) >= 1) {
                    $apkdt .= "<br><br>Extra Items : <br> ";
                    $apl = array();
                    foreach ($sqo["apack_details"]["extra"] as $iap)
                        $apl[] = $iap["name"] . " @ " . $iap["price"] . "<br>, ";
                    $apkdt .= implode(", ", $apl) . " <br> ";
                }

                $qd["insurance"] = $insdt;
                $qd["apack"] = $apkdt;


                $cql["insurance"] = $cq["ins_amount"];
                $cql["extra_disc"] = $cq["extra_disc"];
                $cql["c2d"] = $cq["c2d"];
                $cql["cash"] = $cq["cash"];
                $cql["credit"] = $cq["credit"];
                $cq["removed_disc"] = 0;
                //print_r($rocq);
                if (isset($sqo["remarks"]["rsa"]))
                    $qr["rsa"] = $sqo["remarks"]["rsa"];
                if (isset($sqo["remarks"]["shield"]))
                    $qr["shield"] = $sqo["remarks"]["shield"];
                if (isset($sqo["remarks"]["ins"]))
                    $qr["insurance"] = $sqo["remarks"]["ins"];
                if (isset($sqo["remarks"]["rto"]))
                    $qr["rto"] = $sqo["remarks"]["rto"];
                if (isset($sqo["remarks"]["extra_disc"]))
                    $qr["extra_disc"] = $sqo["remarks"]["extra_disc"];




                //$tda = ($cq["ins_amount"] - $cq["ins_asked"]) + ($cq["rto_amount"] - $cq["rto_asked"]) + $cq["unused_disc"];
            } else {
                $lbl = $sq = $cql = $qd = $qr = array("exshowroom" => null, "incidental" => null, "fastag" => null, "trc" => null, "rto_tape" => null, "charger" => null, "license_fee" => null, "training_fee" => null, "rsa" => null, "shield" => null, "insurance" => null, "rto" => null, "apack" => null, "apack_disc" => null, "shield_disc" => null, "rsa_disc" => null, "cash_disc" => null, "fame" => null, "extra_disc" => null, "tcs" => null, "corp" => null, "enl" => null, "onroad" => null, "invoice" => null);
                $ed = array("enq_id" => $qrec->enq_id, "cust_id" => $enq->person_id, "cust_name" => $person->firstname, "mobile" => $person->mobile, "email" => $person->email, "pincode" => $person->pincode, "loc_id" => $person->loc_id);
                $lbl["exshowroom"] = "Ex-Showroom Price";
                $lbl["charger"] = "Electric Charger";
                $lbl["license_fee"] = "Learning License Fee";
                $lbl["training_fee"] = "Training Fee";
                $lbl["incidental"] = "Incidental Charges";
                $lbl["trc"] = "TRC";
                $lbl["fastag"] = "Fastag";
                $lbl["rto_tape"] = "RTO Tape";
                $lbl["rsa"] = "RSA";
                $lbl["shield"] = "Shield [Extended Warranty]";
                $lbl["insurance"] = "Insurance";
                $lbl["rto"] = "RTO";
                $lbl["apack"] = "Accessories Pack";
                $lbl["apack_disc"] = "Accessories Discount";
                $lbl["shield_disc"] = "Shield Discount";
                $lbl["rsa_disc"] = "RSA Discount";
                $lbl["cash_disc"] = "Cash Discount";
                $lbl["fame"] = "FAME Subsidy";
                $lbl["extra_disc"] = "Extra Discount";
                $lbl["tcs"] = "TCS";
                $lbl["corp"] = "Corporate Bonus";
                $lbl["enl"] = "Exchange or Loyalty";
                $lbl["onroad"] = "OnRoad Price";
                $lbl["invoice"] = "Financier Invoice";
                //print_r($sqo);
                $qd["exshowroom"] = "FIXED";
                $qd["charger"] = "FIXED";
                $qd["license_fee"] = "FIXED";
                $qd["training_fee"] = "FIXED";
                $qd["incidental"] = "FIXED";
                $qd["trc"] = "FIXED";
                $qd["fastag"] = "FIXED";
                $qd["rto_tape"] = "FIXED";
                $qd["cash_disc"] = "FIXED";
                $qd["apack_disc"] = "Fixed discount against Accessories Purchase";
                $qd["fame"] = "FIXED";
                $qd["tcs"] = "FIXED";
                $qd['rto'] = $sqo["rto_type"];
                $qd["rsa"] = $sqo['rsa_type'];
                $qd["shield"] = $sqo['shield_type'];
                $qd["extra_disc"] = "Additional Discount Requested";
                $qd["onroad"] = "Final price for the Vehicle";
                $qd["invoice"] = "Financial Invoice Value for Funding";

                $sq["rsa"] =  $sqo["rsa_amount"];
                $cql["rsa"] = $cq["rsa_amount"];
                $sq["shield"] = $sqo["shield_amount"];
                $cql["shield"] = $cq["shield_amount"];
                $sq["insurance"] = $sqo["ins_amount"];
                $sq["rto"] = $cql["rto"] = $sqo["rto_amount"];
                $sq["apack"] = $sqo["apack_amount"];
                $cql["apack"] = $cq["apack_amount"];
                $sq["apack_disc"] = $sqo["apack_disc"];
                $sq["shield_disc"] = $sqo["shield_disc"];
                $sq["rsa_disc"] = $sqo["rsa_disc"];
                $sq["onroad"] = $sqo["onroad"];
                $sq["invoice"] = $sqo["invoice"];
                $sq["exshowroom"] = $cql["exshowroom"] = $sqo["exshowroom"];
                $sq["incidental"] = $cql["incidental"] = $sqo["incidental"];
                $sq["fastag"] = $cql["fastag"] = $sqo["fastag"];
                $sq["fame"] =  $sqo["fame"];
                $cql["fame"] = $cq["fame"];
                $sq["trc"] = $cql["trc"] = $sqo["trc"];
                $sq["cash_disc"] = $cql["cash_disc"] = $sqo["cash_disc"];
                $sq["tcs"] =  $sqo["tcs"];
                $cql["tcs"] = $cq["tcs"];
                //dd($sqo);
                if (!empty($sqo["corp_amount"])) {
                    $sq['corp'] = $cql["corp"] = $sqo["corp_amount"];
                    $qd["corp"] = $sqo["corp_type"];
                }
                if (!empty($sqo["enl_amount"])) {
                    $sq['enl'] = $cql["enl"] = $sqo["enl_amount"];
                    $qd["enl"] = $sqo["enl_type"];
                }
                if ($sqo['shield_disc'] == $cq['shield_disc'])
                    $qd['shield_disc'] = "Standard Discount Applied";
                else
                    $qd['shield_disc'] = "Standard Discout Removed";

                if ($sqo['rsa_disc'] == $cq['rsa_disc'])
                    $qd['rsa_disc'] = "Standard Discount Applied";
                else
                    $qd['rsa_disc'] = "Standard Discout Removed";

                $insdt = "Details Not Avaialble";
                if ($sqo["ins_type"] == "STANDARD")
                    $insdt = "Selected Default Insurance with Basic + NilDep + Consumables @ " . $sqo["ins_amount"];
                elseif ($sqo["ins_type"] == "ADDON") {
                    $insdt = "Selected Default Insurance with Basic + NilDep + Consumables with addons [";
                    $iadd = array();
                    if ($sqo["ins_details"]["addons"]) {
                        foreach ($sqo["ins_details"]["addons"] as $iad)
                            $iadd[] = $iad["name"] . " @ " . $iad["price"];
                    }
                    $insdt .= implode(", ", $iadd) . "] @ " . $sqo["ins_amount"];
                } elseif ($sqo["ins_type"] == "COMBO")
                    $insdt = "Selected Combo " . $cq["ins_details"]["combo"]["name"] . " having " . $sqo["ins_details"]["combo"]["includes"] . " @ " . $sqo["ins_details"]["combo"]["price"];
                elseif ($sqo["ins_type"] == "SELF")
                    $insdt = "Insurance Removed as Customer chose to get insurance by self";

                $apkdt = "";
                if (isset($sqo["apack_details"]["core"])) {
                    $apkdt = "Essential Items : <br> ";
                    $apl = array();
                    foreach ($sqo["apack_details"]["core"] as $iap)
                        $apl[] = $iap["name"] . " @ " . $iap["price"] . "<br>";
                    $apkdt .= implode(", ", $apl) . " <br> ";
                }
                if (isset($sqo["apack_details"]["extra"]) && count($sqo["apack_details"]["extra"]) >= 1) {
                    $apkdt .= "<br><br>Extra Items : <br> ";
                    $apl = array();
                    foreach ($sqo["apack_details"]["extra"] as $iap)
                        $apl[] = $iap["name"] . " @ " . $iap["price"] . "<br>, ";
                    $apkdt .= implode(", ", $apl) . " <br> ";
                }

                $qd["insurance"] = $insdt;
                $qd["apack"] = $apkdt;

                if (isset($cq["ins_amount"]))
                    $cql["insurance"] = $cq["ins_amount"];
                if (isset($cq["apack_disc"]))
                    $cql["apack_disc"] = $cq["apack_disc"];
                else
                    $cql["apack_disc"] = 0;

                if (!empty($cq["enl_disc"])) {
                    $sq["enl"] = $cql["enl"] = $cq["enl_disc"];
                    $qd["enl"] = $sqo["enl_type"];
                } else {
                    $sq["enl"] = $cql["enl"] = 0;
                    $qd["enl"] = "Not opted for Exchange or Loyalty Bonus";
                }

                if (!empty($cq["corp_disc"])) {
                    $sq["corp"] = $cql["corp"] = $cq["corp_disc"];
                    $qd["corp"] = $sqo["corp_type"];
                } else {
                    $sq["corp"] = $cql["corp"] = 0;
                    $qd["corp"] = "Not opted for Corporate Discount";
                }

                $cql["shield_disc"] = $cq["shield_disc"];
                $cql["rsa_disc"] = $cq["rsa_disc"];
                $cql["extra_disc"] = $cq["extra_disc"];
                $cql["onroad"] = $cq["onroad"];
                $cql["invoice"] = $cq["invoice"];
                $cql["cash"] = $cq["cash"];
                $cql["credit"] = $cq["credit"];

                //print_r($rocq);
                if (isset($sqo["remarks"]["rsa"]))
                    $qr["rsa"] = $sqo["remarks"]["rsa"];
                if (isset($sqo["remarks"]["shield"]))
                    $qr["shield"] = $sqo["remarks"]["shield"];
                if (isset($sqo["remarks"]["ins"]))
                    $qr["insurance"] = $sqo["remarks"]["ins"];
                if (isset($sqo["remarks"]["rto"]))
                    $qr["rto"] = $sqo["remarks"]["rto"];
                if (isset($sqo["remarks"]["extra_disc"]))
                    $qr["extra_disc"] = $sqo["remarks"]["extra_disc"];
                if (isset($sqo["remarks"]["enl_disc"]))
                    $qr["enl"] = $sqo["remarks"]["enl_disc"];
                if (isset($sqo["remarks"]["corp_disc"]))
                    $qr["corp"] = $sqo["remarks"]["corp_disc"];
                if (isset($sqo["remarks"]["fame"]))
                    $qr["fame"] = $sqo["remarks"]["fame"];
                if (isset($sqo["remarks"]["license_fee"]))
                    $qr["license_fee"] = $sqo["remarks"]["license_fee"];
                if (isset($sqo["remarks"]["training_fee"]))
                    $qr["training_fee"] = $sqo["remarks"]["training_fee"];

                //$tda = ($cq["ins_amount"] - $cq["ins_asked"]) + ($cq["rto_amount"] - $cq["rto_asked"]) + $cq["unused_disc"];
            }

            $qh = ChatHelper::get_communication(1, $qid);
            if ($qrec->status == 1)
                $qst = "Pending";
            elseif ($qrec->status == 2)
                $qst = "UnderProcess";
            elseif ($qrec->status == 3)
                $qst = "Approved";
            elseif ($qrec->status == 4)
                $qst = "Modified";
            elseif ($qrec->status == 5)
                $qst = "Escalated";
            elseif ($qrec->status == 6)
                $qst = "Accepted";
            elseif ($qrec->status == 7)
                $qst = "Rejected";
            elseif ($qrec->status == 8)
                $qst = "Sold";
            elseif ($qrec->status == 9)
                $qst = "Cancelled";
            $qdata = array("quote_id" => $qid, "current_user" => $uid, "creator" => $fsc, "creator_id" => $fsc_id, "created_at" => $qrec->created_at,  "updated_at" => $qrec->updated_at, "enq_data" => $ed, "vehicle_data" => $vd, "labels" => $lbl, "standard" => $sq, "custom" => $cql, "details" => $qd, "remarks" => $qr, "history" => $qh, "unused_discount" => $cq["removed_disc"], "ins_diff" => $cq["ins_diff"], "net_disc_requested" => $cq["net_diff"], "assigned_to" => $app_id, "approver" => $app, "quote_status" => $qst, "qst" => $qrec->status, 'aq' => $aq);
            if (($uid == $app_id && $app_id != $fsc_id) || ($mod == true) || $approver_rec != false) {
                $odd = round(self::getAppInsLimit($approver_rec["olimit"], $sqo["plid"]), 0);
                $qdata["approver_data"] = array(
                    "insurance_min_amount" => round($sqo["ins_amount"] - $odd, 0),
                    "insurance_deduction" => $odd,
                    "discount" => $cq["removed_disc"],
                    "ins_diff" => $cq["ins_diff"],
                    "net_disc_requested" => $cq["net_diff"],
                    "bargain" => $approver_rec["dlimit"],
                    "max" => $odd + $cq["removed_disc"] + $approver_rec["dlimit"]
                );
            }
            //print_r($approver_rec);die;
            if (isset($approver_rec["uid"]) && $uid == $approver_rec["uid"])
                $qdata["assigned_to"] = $uid;
            //dd($qdata);
            return $qdata;
        } else
            return false;
    }

    public static function getAppInsLimit($odlimit, $plid)
    {
        return round(InsuranceHelper::getCustomIns($plid, $odlimit), 0);
    }

    public static function shapeQuotedata($datac)
    {
        // $tsq = PricingHelper::getStdQuote($plid);

        //print_r("<br><br> Data in shapeQuoteData : 1. Common -: ");
        //print_r($datac->common);
        //print_r("<br><br> Data in shapeQuoteData : 2. Standard -: ");
        //print_r($datac->sq);
        //print_r("<br><br> Data in shapeQuoteData : 3. Custom -: ");
        //print_r($datac->cq);
        //dd($datac);

        $sq = XpricingHelper::getStdQuote($datac->common['vid'], $datac->common['enq_id']);
        //dd($tsq);
        if (!isset($datac->sq['c2d'])) {
            $cq = array("vid" => 0, "enq_id" => 0, "exshowroom" => 0, "incidental" => 0, "fastag" => 0, "trc" => 0, "rto_tape" => 0, "rsa_details" => "", "rsa_amount" => 0,  "rto_discount" => 0, "shield_details" => "", "shield_amount" => 0, "ins_type" => "", "ins_amount" => 0, "ins_details" => "",  "rto_amount" => 0, "rto_details" => "", "rto_disc" => 0, "apack" => array(), "apack_min" => 0, "apack_general" => 0, "apack_amount" => 0, "tcs" => 0, "apack_disc" => 0, "rsa_disc" => 0, "shield_disc" => 0, "extra_disc" => 0, "cash_disc" => 0, "fame_disc" => 0, "spl_disc" => 0, "corp_details" => 0, "corp_disc" => 0,  "enl_type" => "", "enl_disc" => 0, "enl_details" => "", "rto_charges" => 0, "onroad" => 0, "invoice" => 0, "remarks" => "", "fix_cash" => 0, "cash" => 0, "credit" => 0, "fix_credit" => 0);

            // [sq] => Array ( [rsa_amount] => 2021 [shield_amount] => 8259 [ins_amount] => 34523 [rto_amount] => 152640 [apack_amount] => 27446 [apack_disc] => 10000 [rsa_disc] => 2021 [shield_disc] => 8259 [cash_disc] => 46770 [fame_disc] => 3000 [tcs] => 10205.5 [invoice] => 1010344.5 [onroad] => 1248039 [rto_charges] => 3000 ) 

            // [cq] => Array ( [rsa_amount] => 2021 [shield_amount] => 8259 [ins_amount] => 32000 [rto_amount] => 152640 [rto_discount] => 1500 [apack_amount] => 27446 [apack_disc] => 10000 [rsa_disc] => 2021 [shield_disc] => 8259 [cash_disc] => 46770 [fame_disc] => 3000 [extra_disc] => 56780 [spl_disc] => 8760 [corp_disc] => 4000 [enl_disc] => 20000 [enl_type] => SCRAPPAGE [tcs] => 0 [invoice] => 951010 [onroad] => NaN [rto_charges] => 3000 ) 

            foreach ($datac->common as $key => $val)
                $cq[$key] = $sq[$key] = $val;
            $apack = array();
            $temp = explode("###", $datac->common['apack_details']);
            //print_r("<br><br> Apack Core : ");
            //print_r($temp);
            foreach ($temp as $itm) {
                if (!empty($itm)) {
                    $idt = explode("||", $itm);
                    //print_r("<br><br> Acc Item : ");
                    //print_r($idt);
                    $apack[] = array("id" => $idt[0], "name" => $idt[2], "price" => $idt[1]);
                }
            }

            $cq['apack'] = $sq['apack'] = $apack;
            foreach ($cq as $key => $val) {
                if (isset($datac->sq[$key]))
                    $sq[$key] = $datac->sq[$key];
                if (isset($datac->cq[$key]))
                    $cq[$key] = $datac->cq[$key];
            }
        } else {
            if (!isset($cqo['c2d'])) {
                $sq['onroad'] = $sqo['onroad'];
                $cq['onroad'] = $cqo['onroad'];
                $sq['invoice'] = $sqo['invoice'];
                $cq['invoice'] = $cqo['invoice'];
            } else {
                $sq['c2d'] = $sqo['c2d'];
                $cq['c2d'] = $cqo['c2d'];
            }
            //dd($sq);


        }

        $discl = array("RSA" => "rsa_disc", "SHIELD" => "shield_disc", "APACK" => "apack_disc");
        $dwh = $exds = $insdiff = 0;
        foreach ($discl as $dval) {
            $sqv = (is_numeric($sq[$dval])) ? $sq[$dval] : 0;
            $cqv = (is_numeric($cq[$dval])) ? $cq[$dval] : 0;
            $dwh += $sqv - $cqv;
        }

        if ($sq['ins_amount'] != $cq['ins_amount'])
            $insdiff = $sq['ins_amount'] - $cq['ins_amount'];
        if (isset($cq['extra_disc']))
            $exds = $cq['extra_disc'];


        $cq['removed_disc'] = $dwh;
        $cq['ins_diff'] = $insdiff;
        $cq['net_diff'] = $exds + $insdiff;
        $cq['opd'] = $sq['onroad'] - $cq['onroad'];
        if ($sq["ins_amount"] < $cq["ins_amount"])
            $sq["ins_amount"] = $cq["ins_amount"];
        if ($sq["rsa_amount"] < $cq["rsa_amount"])
            $sq["rsa_amount"] = $cq["rsa_amount"];
        if ($sq["shield_amount"] < $cq["shield_amount"])
            $sq["shield_amount"] = $cq["shield_amount"];


        $data = array("csq" => $sq, "ccq" => $cq);
        return $data;
    }

    // public static function formatQuotedata($plid, $vid, $commono, $sqo, $cqo, $remarkso, $apack_coreo, $apack_extrao, $insaddonso, $inscomboo, $st_ins)
    // {
    //     $tsq = PricingHelper::getStdQuote($plid);
    //     //dd($tsq);
    //     $cq = $sq = array("vid" => 0, "plid" => 0, "enq_id" => 0, "exshowroom" => 0, "incidental" => 0, "fastag" => 0, "trc" => 0, "rto_tape" => 0, "charger" => 0, "license_fee" => 0, "training_fee" => 0, "rsa_id" => 0, "rsa_type" => 0, "rsa_amount" => 0, "shield_id" => 0, "shield_type" => 0, "shield_amount" => 0, "ins_type" => 0, "ins_amount" => 0, "ins_details" => 0, "rto_id" => 0, "rto_amount" => 0, "rto_type" => 0, "apack_core" => 0, "apack_extra" => 0, "apack_amount" => 0, "apack_type" => 0, "apack_details" => 0, "tcs" => 0, "apack_disc" => 0, "rsa_disc" => 0, "shield_disc" => 0, "extra_disc" => 0, "cash_disc" => 0, "fame" => 0, "corp_id" => 0, "corp_type" => 0, "corp_disc" => 0, "enl_id" => 0, "enl_type" => 0, "enl_disc" => 0, "onroad" => 0, "invoice" => 0, "remarks" => 0, "cash" => 0, "credit" => 0);
    //     foreach ($commono as $key => $val) {
    //         if ($val != "false")
    //             $cq[$key] = $sq[$key] = $val;
    //         else
    //             $commono[$key] = 0;
    //     }
    //     foreach ($sqo as $key => $val) {
    //         if ($val == "false")
    //             $sqo[$key] = 0;
    //     }
    //     foreach ($cqo as $key => $val) {
    //         if ($val == "false")
    //             $cqo[$key] = 0;
    //     }
    //     //print_r("<br><br>Apack Core : ");
    //     //print_r($apack_coreo);
    //     $temp = $apack_coreo;
    //     foreach ($temp as $key => $val)
    //         if ($val == "false")
    //             unset($temp[$key]);
    //     $apack_core = $temp;
    //     //print_r("<br><br>Apack Extra : ");
    //     //print_r($apack_extrao);
    //     $temp = $apack_extrao;
    //     foreach ($temp as $key => $val)
    //         if ($val == "false")
    //             unset($temp[$key]);
    //     $apack_extra = $temp;
    //     //print_r("<br><br>Ins add : ");
    //     //print_r($insaddonso);
    //     $temp = $insaddonso;
    //     foreach ($temp as $key => $val)
    //         if ($val == "false")
    //             unset($temp[$key]);
    //     $ins_addons = $temp;
    //     if ($inscomboo != "false")
    //         $ins_combo = $inscomboo;
    //     foreach ($sqo as $key => $val)
    //         if ($val != "false")
    //             $sq[$key] = $val;
    //     foreach ($cqo as $key => $val)
    //         if ($val != "false")
    //             $cq[$key] = $val;
    //     $temp = $remarkso;
    //     foreach ($temp as $key => $val)
    //         if ($val == "false")
    //             $temp[$key] = null;
    //     $cq["remarks"] = $sq["remarks"] = $temp;
    //     $cq["rsa_id"] = $sq["rsa_id"] = $cq["rsa_type"];
    //     $cq["shield_id"] = $sq["shield_id"] = $cq["shield_type"];
    //     $cq["ins_id"] = $sq["ins_id"] = $cq["ins_type"];
    //     $cq["rto_id"] = $sq["rto_id"] = $sq["rto_type"];
    //     $sq["rsa_amount"] = $sqo["rsa_amount"];
    //     $sq["shield_amount"] = $sqo["shield_amount"];
    //     $sq["apack_amount"] = $sqo["apack_amount"];
    //     $cq["rsa_amount"] = $cqo["rsa_amount"];
    //     $cq["shield_amount"] = $cqo["shield_amount"];
    //     $cq["apack_amount"] = $cqo["apack_amount"];
    //     if (isset($cqo["tcs"]))
    //         $cq["tcs"] = $cqo["tcs"];
    //     else
    //         $cq["tcs"] = 0;
    //     //dd($cq);
    //     $tsq = PricingHelper::getStdQuote($plid);
    //     //print_r($tsq);
    //     //dd($tsq);
    //     $rsadata = $shielddata = false;
    //     if (!empty($cq["rsa_id"]))
    //         $rsadata = ExtrasHelper::getRSAByID($cq["rsa_id"]);
    //     if ($rsadata)
    //         $sq["rsa_type"] = $cq["rsa_type"] = "RSA for " . $rsadata['years'] . " year(s)";
    //     else
    //         $sq["rsa_type"] = $cq["rsa_type"] = "None";
    //     if (!empty($cq["shield_id"]))
    //         $shielddata = ExtrasHelper::getShieldByID($cq["shield_id"]);
    //     if ($shielddata)
    //         $sq["shield_type"] = $cq["shield_type"] = "Shield for " . $shielddata['cover'] . " year(s)";
    //     else
    //         $sq["shield_type"]  = $cq["shield_type"]  = "None";


    //     //Insurnace
    //     $itype = $cq["ins_type"];
    //     $itp = "";
    //     if ($itype == 1)
    //         $itp = "STANDARD";
    //     elseif ($itype == 2)
    //         $itp = "ADDON";
    //     elseif ($itype == 3)
    //         $itp = "COMBO";
    //     elseif ($itype == 4)
    //         $itp = "SELF";
    //     $sq["ins_type"] = $cq["ins_type"] = $itp;
    //     $sq["ins_details"] = $cq["ins_details"] = array("standard" => false, "addons" => false, "combo" => false);
    //     if ($itype < 3) {
    //         $sq["ins_details"]["standard"] = $cq["ins_details"]["standard"] = array("name" => "Basic + NilDep + Consumables", "price" => $st_ins);
    //         if ($itype == 2 && !empty($ins_addons)) {
    //             $sq["ins_details"]["addons"] = $cq["ins_details"]["addons"] = array();
    //             foreach ($ins_addons as $aid) {
    //                 foreach ($tsq["nildep"]["addons"] as $iad) {
    //                     if ($iad["id"] == $aid) {
    //                         $sq["ins_details"]["addons"][] = $cq["ins_details"]["addons"][] = array("id" => $aid, "name" => $iad["head"], "price" => $iad["total"]);
    //                     }
    //                 }
    //             }
    //         }
    //     } elseif ($itype == 3) {
    //         foreach ($tsq["nildep"]["combos"] as $iad) {
    //             if ($iad["id"] == $ins_combo) {
    //                 $sq["ins_details"]["combo"] = $cq["ins_details"]["combo"] = array("id" => $iad, "name" => $iad["name"], "includes" => $iad["addons"], "price" => $iad["total"]);
    //             }
    //         }
    //     }

    //     //RTO
    //     if ($cq["rto_type"] == 1)
    //         $sq["rto_type"] = $cq["rto_type"] = "Standard RTO";
    //     elseif ($cq["rto_type"] == 2)
    //         $sq["rto_type"] = $cq["rto_type"] = "BH Series RTO";
    //     elseif ($cq["rto_type"] == 3)
    //         $sq["rto_type"] = $cq["rto_type"] = "RTO Removed for Other State Vehicle";
    //     elseif ($cq["rto_type"] == 4)
    //         $sq["rto_type"] = $cq["rto_type"] = "Removed for Other Reason";



    //     //Accessories Pack
    //     $sq["apack_details"] = $cq["apack_details"] = array("core" => array(), "extra" => array());
    //     if (!empty($apack_core)) {
    //         //print_r("<br><br>CoreO in helper : ");//print_r($apack_coreo);
    //         //print_r("<br><br>Core in helper : ");//print_r($apack_core);
    //         //dd($tsq["apackdt"]);
    //         foreach ($apack_core as $aid) {
    //             foreach ($tsq["apackdt"]["essential"] as $oap) {
    //                 if ($oap["id"] == $aid)
    //                     $sq["apack_details"]["core"][] = $cq["apack_details"]["core"][] = array("id" => $aid, "name" => $oap["name"], "price" => $oap["price"]);
    //             }
    //         }
    //     }
    //     if (!empty($apack_extra)) {
    //         foreach ($apack_extra as $aid) {
    //             foreach ($tsq["apackdt"]["extra"] as $oap) {
    //                 if ($oap["id"] == $aid)
    //                     $sq["apack_details"]["extra"][] = $cq["apack_details"]["core"][] = array("id" => $aid, "name" => $oap["name"], "price" => $oap["price"]);
    //             }
    //         }
    //     }

    //     //print_r("<br><br>Details : ");dd($sq["apack_details"]);
    //     //Corporate and Exchange
    //     if (!empty($cq["enl_id"])) {
    //         //print_r("<br><br>Checking ENL : ");
    //         foreach ($tsq["enl"] as $tdat)
    //             if ($tdat["id"] == $cq["enl_id"])
    //                 $sq["enl_type"] = $cq["enl_type"] = $tdat["type"] . " Bonus for " . $tdat["scheme"] . " against " . $tdat["old_vehicle"] . " vehicle";
    //     }

    //     if (!empty($cq["corp_id"])) {
    //         foreach ($tsq["corp"] as $tdat)
    //             if ($tdat["id"] == $cq["corp_id"])
    //                 $cq["corp_type"] = $sq["corp_type"] = $tdat["category"] . " Bonus";
    //     }
    //     if (!isset($cqo['c2d'])) {
    //         $sq['onroad'] = $sqo['onroad'];
    //         $cq['onroad'] = $cqo['onroad'];
    //         $sq['invoice'] = $sqo['invoice'];
    //         $cq['invoice'] = $cqo['invoice'];
    //     } else {
    //         $sq['c2d'] = $sqo['c2d'];
    //         $cq['c2d'] = $cqo['c2d'];
    //     }
    //     //dd($sq);
    //     $discl = array("RSA" => "rsa_disc", "SHIELD" => "shield_disc", "APACK" => "apack_disc");
    //     $dwh = $exds = $insdiff = 0;
    //     foreach ($discl as $dval) {
    //         $sqv = (is_numeric($sq[$dval])) ? $sq[$dval] : 0;
    //         $cqv = (is_numeric($cq[$dval])) ? $cq[$dval] : 0;
    //         $dwh += $sqv - $cqv;
    //     }

    //     if ($sq['ins_amount'] != $cq['ins_amount'])
    //         $insdiff = $sq['ins_amount'] - $cq['ins_amount'];
    //     if (isset($cq['extra_disc']))
    //         $exds = $cq['extra_disc'];

    //     $cq['removed_disc'] = $dwh;
    //     $cq['ins_diff'] = $insdiff;
    //     $cq['net_diff'] = $exds + $insdiff;
    //     $cq['opd'] = $sq['onroad'] - $cq['onroad'];
    //     if ($sq["ins_amount"] < $tsq["insurance"])
    //         $sq["ins_amount"] = $tsq["insurance"];
    //     if ($sq["rsa_amount"] < $tsq["rsa"])
    //         $sq["rsa_amount"] = $tsq["rsa"];
    //     if ($sq["shield_amount"] < $tsq["shield"])
    //         $sq["shield_amount"] = $tsq["shield"];
    //     if ($sq["apack_amount"] < $tsq["apackdt"]["mrp"])
    //         $sq["apack_amount"] = $tsq["apackdt"]["mrp"];

    //     $data = array("csq" => $sq, "ccq" => $cq);
    //     return $data;
    // }

    public static function fetchEnqData($enqid)
    {
        $data = array('eid' => false, 'fid' => false, 'pid' => false, 'vid' => false, 'qid' => false, 'plid' => false, 'status' => false,);
        $enq = Enquiry::where('enq_id', $enqid)->first();
        if ($enq) {
            $data['eid'] = $enqid;
            $data['fid'] = $enq->fsc_id;
            $data['pid'] = $enq->person_id;
            $data['vid'] = $enq->vehicle_id;
            $quote = Quotation::where('enq_id', $enqid)->first();
            if ($quote) {
                $data['qid'] = $quote->id;
                $data['plid'] = $quote->pl_id;
                if ($quote->status == 1)
                    $data['status'] = "Pending";
                elseif ($quote->status == 2)
                    $data['status'] = "UnderProcess";
                elseif ($quote->status == 3)
                    $data['status'] = "Approved";
                elseif ($quote->status == 4)
                    $data['status'] = "Modified";
                elseif ($quote->status == 5)
                    $data['status'] = "Escalated";
                elseif ($quote->status == 6)
                    $data['status'] = "Accepted";
                elseif ($quote->status == 7)
                    $data['status'] = "Rejected";
                elseif ($quote->status == 8)
                    $data['status'] = "Sold";
                elseif ($quote->status == 9)
                    $data['status'] = "Cancelled";
            }
        }
        return $data;
    }

    public static function getPerson($pid)
    {
        $per = Person::find($pid);
        if ($per) {
            $data = array(
                "person_id" => $per->id,
                "name" => $per->firstname,
                "email" => $per->email,
                "mobile" => $per->mobile,
                "address" => $per->address,
                "loc_id" => $per->loc_id,
                "pincode" => $per->pincode,
            );
            return $data;
        }
        return false;
    }

    public static function getClient($mob)
    {
        $per = Person::where('mobile', $mob)->first();
        if ($per) {
            $data = array(
                "person_id" => $per->id,
                "name" => $per->firstname,
                "email" => $per->email,
                "mobile" => $per->mobile,
                "address" => $per->address,
                "loc_id" => $per->loc_id,
                "pincode" => $per->pincode,
            );
            return $data;
        }
        return false;
    }


    public static function getEnqData($vid, $enqdata)
    {
        $data = array('message' => 0, 'rcode' => 0, 'enq_id' => 0, 'person' => array(), 'qid' => 0, 'enq_status' => 0, 'status' => false);
        //Search for Enquiry
        $enq = Enquiry::where('enq_id', $enqdata["enq_id"])->first();
        $vh = XVehicleMaster::find($vid);
        //print_r("<br>   <br>   ");
        //print_r($enqdata);
        if ($enq) {
            //Enqiry Found now match Vehicle
            if ($vh->status == 1) {
                if ($enq->vehicle_id == $vid) {
                    //Vehicle Matched now find Person
                    $per = Person::find($enq->person_id);
                    if ($per) {
                        //Person Found Matching Info
                        if ($per->mobile == $enqdata["mobile"] && $per->email == $enqdata["email"]) {
                            $data["person"] = array(
                                "person_id" => $per->id,
                                "name" => $per->firstname,
                                "email" => $per->email,
                                "mobile" => $per->mobile,
                                "address" => $per->address,
                                "loc_id" => $per->loc_id,
                                "pincode" => $per->pincode,
                            );
                            //Info Matched searching of Existing Quote
                            $quote = Quotation::where('enq_id', $enqdata["enq_id"])->first();
                            if ($quote) {

                                //Quote Found checking status and respond accordingly
                                if ($quote->status > 6) {
                                    $data['rcode'] = 8;
                                    $data['message'] = "Closed Quote Communication Found";
                                    $data['enq_id'] = $enqdata["enq_id"];
                                    $data['enq_status'] = "CLOSED";
                                    $data['status'] = true;
                                } else {
                                    $data['rcode'] = 7;
                                    $data['message'] = "Ongoing Quote Communication Found";
                                    $data['enq_id'] = $enqdata["enq_id"];
                                    $data['enq_status'] = "InProgress";
                                    $data['status'] = false;
                                }
                            } else //Quote not found
                            {
                                $data['rcode'] = 5;
                                $data['message'] = "Enq Exist without Quote";
                                $data['enq_id'] = $enqdata["enq_id"];
                                $data['enq_status'] = "FRESH";
                                $data['status'] = true;
                            }
                        } else // Person not matched
                        {
                            $data['rcode'] = 4;
                            $data['message'] = "Enq Exist but for some other user";
                            $data['enq_id'] = $enqdata["enq_id"];
                            $data['enq_status'] = "InProgress";
                            $data['status'] = false;
                        }
                    } else // Person not Found
                    {
                        $data['rcode'] = 4;
                        $data['message'] = "Enq Exist but for some other user";
                        $data['enq_id'] = $enqdata["enq_id"];
                        $data['enq_status'] = "InProgress";
                        $data['status'] = false;
                    }
                } else {
                    $data['rcode'] = 3;
                    $data['message'] = "Enq exist but for other Vehicle";
                    $data['enq_id'] = $enqdata["enq_id"];
                    $data['enq_status'] = "InProgress";
                    $data['status'] = false;
                }
            } else //Vehicle not found or disabled
            {
                $data['rcode'] = 6;
                $data['message'] = "Incorrect Vehicle Id";
                $data['status'] = false;
            }
        } else // Enq not found
        {
            $flag = true;
            $per = Person::where('mobile', $enqdata["mobile"])->where('email', $enqdata["email"])->where('pincode', $enqdata["pincode"])->first();
            $user = Auth::user();
            if (!$per) {
                $per = new Person;
                $per->firstname = $enqdata["name"];
                $per->mobile = $enqdata["mobile"];
                $per->email = $enqdata["email"];
                $per->address = $enqdata["address"];
                $per->loc_id = $enqdata["postoffice"];
                $per->pincode = $enqdata["pincode"];
                $per->save();
                $flag = false;
            }
            $data["person"] = array(
                "person_id" => $per->id,
                "name" => $per->firstname,
                "email" => $per->email,
                "mobile" => $per->mobile,
                "address" => $per->address,
                "loc_id" => $enqdata["postoffice"],
                "pincode" => $per->pincode,
            );
            //$pinfo = array('name' => $cname,
            $enq = new Enquiry;
            $enq->enq_id = $enqdata["enq_id"];
            $enq->vehicle_id = $vid;
            $enq->person_id = $per->id;
            $enq->fsc_id = $user['id'];
            $enq->save();
            if (!$flag) {
                $data['rcode'] = 1;
                $data['enq_id'] = $enqdata["enq_id"];
                $data['enq_status'] = "FRESH";
                $data['message'] = "New Enq created with new Person";
                $data['status'] = true;
            } else {
                $data['rcode'] = 2;
                $data['enq_id'] = $enqdata["enq_id"];
                $data['enq_status'] = "FRESH";
                $data['message'] = "New Enq created with existing Person";
                $data['status'] = true;
            }
        }
        return $data;
    }


    public static function Qfup($qid, $tsub, $rem, $uid, $file = null)
    {
        $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
        if ($tsub == "C")
            $sub = self::getCreator($uid) . " Added a comment";
        elseif ($tsub == "R")
            $sub = self::getCreator($uid) . " Added a reply";
        elseif ($tsub == "Q")
            $sub = self::getCreator($uid) . " Added a query";
        // $commid, $content, $remark, $file = null, $stt = 2
        //print_r("<br>commid : $commid, sub : $sub, rem : $rem");
        //print_r("<br>Going to Add Comm in ChatHelper");
        $fup = ChatHelper::add_followup($commid, $rem, $sub, $file, 2);
        //print_r("<br>Back from Add Comm in ChatHelper");

        return;
    }

    public static function addComm($tid, $uid, $remark, $file)
    {
        $td = Quotation::find($tid);
        if ($td) {

            $commid = ChatHelper::get_commid(1, $tid, "Quote Created");
            $user = CommonHelper::getUserName($uid);
            $sub = $user . " added a remark";
            ChatHelper::add_followup($commid, $remark, $sub, $file, 2);
            return true;
        } else
            return false;
    }



    public static function updateQuote($qid, $uid, $act, $rem, $amt = 0, $car = 0)
    {
        //print_r($car);
        $data = self::fetchQuote($qid, $uid);
        if ($data) {
            if ($act == "Q" || $act == "R" || $act == "C") {
                if ($act == "Q")
                    $sub = self::getCreator($uid) . " added a question";
                elseif ($act == "R")
                    $sub = self::getCreator($uid) . " added a reply";
                elseif ($act == "C")
                    $sub = self::getCreator($uid) . " added a comment";
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                if ($commid) {
                    //print_r("<br>Comm found");

                    $fup = ChatHelper::add_followup($commid, $rem, $sub);
                    $mq = Quotation::find($qid);
                    $mq->status = 2;
                    $mq->save();
                    NotificationHelper::notify($mq->assigned_to, $sub . " on Quote # " . $qid, $rem, 1, $mq->id, "N");

                    if ($fup)
                        return array('message' => 'Quote Updated', 'success' => true, 'response' => "Quote Updated Successfully");
                    else
                        return array('message' => 'Unable to update quote please try again', 'success' => false, 'response' => "Quote Updation Failed");
                } else {
                    //print_r("<br>Comm  Not found");
                    return array('message' => 'Unable to update quote please try again', 'success' => false, 'response' => "Quote Updation Failed");
                }
            } elseif ($act == "E") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                if ($uid == $data["assigned_to"]) //($uid == $data["assigned_to"])
                {
                    if ($data["net_disc_requested"] > $data["approver_data"]["max"]) {
                        $appd = VehicleHelper::findApprover($data["vehicle_data"]["vid"], 1);
                        foreach ($appd as $lvl => $ldat) {
                            if ($ldat["uid"] == $uid)
                                $alvl = $lvl;
                        }
                        if ($alvl < 5) {
                            $nlvl = $alvl + 1;
                            while (true) {
                                if (!empty($appd[$nlvl]["uid"]))
                                    break;
                                $nlvl++;
                                if ($nlvl == 6)
                                    break;
                            }
                            if ($nlvl < 6) {
                                $apid = $appd[$nlvl]["uid"];
                                $mq = Quotation::find($qid);
                                $mq->assigned_to = $apid;
                                $mq->status =  5;
                                $mq->save();
                                $sub = self::getCreator($uid) . " esclated the quote next level to " . self::getCreator($apid);
                                $fup = ChatHelper::add_followup($commid, $rem, $sub);
                                NotificationHelper::notify($mq->assigned_to, self::getCreator($uid) . " escalated teh Quote # " . $qid . " to you", $rem, 1, $qid, "N");
                                return array('message' => 'Quote esclated to next level', 'success' => true, 'response' => "Quote esclated to next level");
                            } else
                                return array('message' => 'Highest Approval Level cant escalate further', 'success' => false, 'response' => "Highest Approval Level cant esclate further");
                        } else
                            return array('message' => 'Highest Approval Level cant escalate further', 'success' => false, 'response' => "Highest Approval Level cant esclate further");
                    } else
                        return array('message' => 'No need to escalate. Discount requested is within approval limit', 'success' => false, 'response' => "No need to esclate. Discount requested is within approval limit");
                } else
                    return array('message' => 'You are not authorised for this action', 'success' => false, 'response' => "No need to esclate. Discount requested is within approval limit");
            } elseif ($act == "M") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                //print_r("<br> Working...");
                if (isset($data["custom"]["onroad"])) {
                    //print_r("<br> General");

                    $sub = self::getCreator($uid) . " modify the quote from requested OnRoad Value of " . $data["custom"]["onroad"] . " to " . $amt . " and submitted for user's approval ";
                    $fup = ChatHelper::add_followup($commid, $rem, $sub);
                    //print_r("<br> 3");
                    $qd = self::fetchQuote($qid, $uid);

                    $dmq = Quote::where('quote_id', $qid)->where('status', 1)->orderby('revision', 'DESC')->first();
                    $cq = json_decode($dmq->requested);
                    // [ins_amount] => 35000 [rto_amount] => 500 [shield_disc] => 8000 [rsa_disc] => 0 [extra_disc] => 2500 [onroad] => 974214 [invoice] => 920021 [remarks] => Array ( [rsa] => RSA Not Required [insurance] => As offered by Acko [enl] => Exchange Bonus Applicable [corp] => Corp Bonus Applicable ) [removed_disc] => 2021 [ins_diff] => 3467 [net_diff] => 5967 [opd] => 1446
                    $ocdisc = $cq['extra_disc'];
                    $oins = $cq['ins_amount'];
                    $oonrd = $cq['onroad'];
                    $oinv = $cq['invoice'];
                    //$ond = $cq['net_disc_requested'];
                    $cq['extra_disc'] = $car['extra_disc'];
                    $cq['ins_amount'] = $car['insurance'];
                    $cq['onroad'] = $car['onroad'];
                    $cq['invoice'] = $car['invoice'];
                    $cq['cash'] = $car['cash'];
                    $cq['credit'] = $car['credit'];
                    $newPost = new Quote();
                    $newPost->requested = json_encode($cq);
                    $newPost->revision = $dmq->revision + 1;
                    $newPost->quote_id = $qid;
                    $newPost->action_by = $qd["current_user"];
                    $newPost->action = 2;
                    $newPost->onroad = $car['onroad'];
                    $newPost->save();
                    $mq = Quotation::find($qid);
                    $mq->status = 4;
                    $mq->assigned_to = $mq->fsc_id;
                    $mq->save();
                    NotificationHelper::notify($mq->assigned_to, "Quote # " . $qid, $sub, 1, $qid, "N");
                    //print_r("<br> 5");
                    return array('message' => 'Quote modified and submitted for user acceptance', 'success' => true, 'response' => "Quote modified and submitted for user acceptance");
                } elseif (isset($data["custom"]["c2d"])) {
                    //print_r("<br> CSD");
                    $sub = self::getCreator($uid) . " modify the quote from requested Customer Value of " . $data["custom"]["c2d"] . " to " . $amt . " and submitted for user's approval ";
                    $fup = ChatHelper::add_followup($commid, $rem, $sub);
                    //print_r("<br> 1");
                    $dmq = Quote::where('quote_id', $qid)->where('status', 1)->orderby('revision', 'DESC')->first();
                    $cq = json_decode($dmq->requested);
                    //print_r("<br> 3");
                    // [ins_amount] => 35000 [rto_amount] => 500 [shield_disc] => 8000 [rsa_disc] => 0 [extra_disc] => 2500 [onroad] => 974214 [invoice] => 920021 [remarks] => Array ( [rsa] => RSA Not Required [insurance] => As offered by Acko [enl] => Exchange Bonus Applicable [corp] => Corp Bonus Applicable ) [removed_disc] => 2021 [ins_diff] => 3467 [net_diff] => 5967 [opd] => 1446
                    $ocdisc = $cq['extra_disc'];
                    $oins = $cq['ins_amount'];
                    $oc2d = $cq['c2d'];

                    //$ond = $cq['net_disc_requested'];
                    $cq['extra_disc'] = $car['extra_disc'];
                    $cq['ins_amount'] = $car['insurance'];
                    $cq['c2d'] = $amt;
                    $newPost = new Quote();
                    $newPost->requested = json_encode($cq);
                    $newPost->revision = $dmq->revision + 1;
                    $newPost->quote_id = $qid;
                    $newPost->action_by = $uid;
                    $newPost->action = 2;
                    $newPost->onroad = $amt;
                    $newPost->save();
                    //print_r("<br> 4");
                    $mq = Quotation::find($qid);
                    $mq->status = 4;
                    $mq->assigned_to = $mq->fsc_id;
                    $mq->save();
                    //print_r("<br> 5");
                    return array('message' => 'Quote modified and submitted for user acceptance', 'success' => true, 'response' => "Quote modified and submitted for user acceptance");
                }
            } elseif ($act == "A") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                $mq = Quotation::find($qid);
                $mq->status = 3;
                $mq->assigned_to = $mq->fsc_id;
                $mq->save();
                $sub = self::getCreator($uid) . " approved the quote";
                $fup = ChatHelper::add_followup($commid, $rem, $sub);
                return array('message' => 'Quote Approved', 'success' => true, 'response' => "Quote Approved");
            } elseif ($act == "AC") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                Quotation::find($qid)->update(['status' => 6]);
                $sub = "Customer accepted the quote";
                $fup = ChatHelper::add_followup($commid, $rem, $sub);
                //dd($fup);
                return array('message' => 'Quote Accepted by the customer', 'success' => true, 'response' => "Quote Accepted");
            } elseif ($act == "RJ") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                Quotation::find($qid)->update(['status' => 7]);
                $sub = "Customer rejected the quote";
                $fup = ChatHelper::add_followup($commid, $rem, $sub);
                //dd($fup);
                return array('message' => 'Customer rejected the quote', 'success' => true, 'response' => "Quote Rejected");
            } elseif ($act == "CN") {
                $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
                Quotation::find($qid)->update(['status' => 9]);
                $sub = "Quote Cancelled";
                $fup = ChatHelper::add_followup($commid, $rem, $sub);
                //dd($fup);
                return array('message' => 'Customer cancelled the quote', 'success' => true, 'response' => "Quote Cancelled");
            } else
                return array('message' => 'Invalid Action Quote. Please Check', 'success' => true, 'response' => "Invalid Action Quote. Please Check");
        } else {
            return array('message' => 'Quote Data not found for Quote id : ' . $qid, 'success' => false, 'response' => "Quote Data Not found");
        }
    }

    public static function editQuote($sq, $cq, $qid, $uid)
    {
        //print_r($car);
        $data = self::fetchQuote($qid, $uid);
        if ($data) {
            $sub = self::getCreator($uid) . " edit the quote";
            $commid = ChatHelper::get_commid(1, $qid, "Quote Created");
            if ($commid) {
                $fup = ChatHelper::add_followup($commid, $sub, "Quote Edited");
                $dmq = Quote::where('quote_id', $qid)->where('status', 1)->orderby('revision', 'DESC')->first();
                $newPost = new Quote();
                $newPost->requested = json_encode($cq);
                $newPost->revision = $dmq->revision + 1;
                $newPost->quote_id = $qid;
                $newPost->action_by = $uid;
                $newPost->action = 0;
                if (!isset($cq['c2d']))
                    $newPost->onroad = $cq['onroad'];
                else
                    $newPost->onroad = $cq['c2d'];
                $newPost->save();
                $mq = Quotation::find($qid);
                $mq->revision = $dmq->revision + 1;
                $mq->standard = json_encode($sq);
                //$mq->status = json_encode($sq);
                //print_r("<br><br>");//print_r($mq->toarray());
                $mq->save();
                NotificationHelper::notify($mq->assigned_to, "Quote Edited. Quote Id : " . $mq->id, $sub, 1, $mq->id, "N");
                return array('message' => 'Quote Edited Successfully', 'success' => true, 'response' => "Quote Edited");
            }
        }
        return array('message' => 'Quote Data not found for Quote id : ' . $qid, 'success' => false, 'response' => "Quote Data Not found");
    }

    public static function cancelQuote($qid, $uid)
    {
        //print_r($car);
        $data = self::fetchQuote($qid, $uid);
        if ($data) {
            $sub = self::getCreator($uid) . " Cancelled the quote";
            $commid = ChatHelper::get_commid(1, $qid, "Quote Created");

            if ($commid) {
                $fup = ChatHelper::add_followup($commid, $sub, "Quote Cancelled");
                $mq = Quotation::find($qid);
                $mq->status = 9;
                $mq->save();
                NotificationHelper::notify($mq->assigned_to, "Quote Cancelled. Quote Id : " . $mq->id, $sub, 1, $mq->id, "N");
                return array('message' => 'Quote Cancelled Successfully', 'success' => true, 'response' => "Quote Cancelled");
            }
        }
        return array('message' => 'Quote Data not found for Quote id : ' . $qid, 'success' => false, 'response' => "Quote Data Not found");
    }

    public static function reviveQuote($qid, $uid)
    {
        //print_r($car);
        $data = self::fetchQuote($qid, $uid);
        if ($data) {
            $sub = self::getCreator($uid) . " Reviveded the quote";
            $commid = ChatHelper::get_commid(1, $qid, "Quote Created");

            if ($commid) {
                $fup = ChatHelper::add_followup($commid, $sub, "Quote Revived");
                $mq = Quotation::find($qid);
                $mq->status = 1;
                $mq->save();
                NotificationHelper::notify($mq->assigned_to, "Quote Revived. Quote Id : " . $mq->id, $sub, 1, $mq->id, "N");
                return array('message' => 'Quote Revived Successfully', 'success' => true, 'response' => "Quote Revived");
            }
        }
        return array('message' => 'Quote Data not found for Quote id : ' . $qid, 'success' => false, 'response' => "Quote Data Not found");
    }


    public static function quoteEditData($qid)
    {
        $qrec = Quotation::find($qid);
        if ($qrec) {
            $enq = Enquiry::where('enq_id', $qrec->enq_id)->first();

            $person = Person::find($enq->person_id);
            $cust = array("name" => $person->firstname, "mobile" => $person->mobile, "email" => $person->email, "address" => $person->address, "loc_id" => $person->loc_id, "postoffice" => "", "tehsil" => "", "district" => "", "state" => "", "pincode" => $person->pincode);
            if (!empty($person->loc_id)) {
                $loc = CommonHelper::locById($person->loc_id);
                //dd($loc);
                if ($loc) {
                    $cust["postoffice"] = $loc["postoffice"]["name"];
                    $cust["tehsil"] = $loc["tehsil"]["name"];
                    $cust["district"] = $loc["district"]["name"];
                    $cust["state"] = $loc["state"]["name"];
                }
            }
            $vehicle = Vehicle::find($enq->vehicle_id);
            $fsc = User::find($qrec->fsc_id);

            $vd = array("vid" => $enq->vehicle_id, "custom_model" => $vehicle->cm1, "vehicle" => $vehicle->local_name, "transmission" => CommonHelper::enumValueById($vehicle->transmission_type), "seating" => $vehicle->seating, "fuel" => CommonHelper::enumValueById($vehicle->fuel_type_id));
            $lq = Quote::where('quote_id', $qrec->id)->where('status', 1)->orderby('revision', 'DESC')->first();
            $sq = json_decode($qrec->standard);
            $cq = json_decode($lq->requested);
            $sel = array(
                "license_fee" => $cq["license_fee"],
                "training_fee" => $cq["training_fee"],
                "rsa_id" => $cq["rsa_id"],
                "shield_id" => $cq["shield_id"],
                "ins_id" => $cq["ins_id"],
                "ins_amt_type" => $cq["ins_amt_type"],
                "ins_addons" => array(),
                "ins_combo" => 0,
                "rto_id" => 0,
                "apack_id" => $cq["apack_type"],
                "apack_core" => array(),
                "apack_extra" => array(),
                "fame_id" => $cq["fame"],
                "corp_id" => $cq["corp_id"],
                "enl_id" =>  $cq["enl_id"],
                "cash" => $cq["cash"],
                "credit" => $cq["credit"]
            );
            if ($cq["ins_id"] == 2 && is_array($sq["ins_details"]["addons"]))
                foreach ($sq["ins_details"]["addons"] as $itm)
                    $sel["ins_addons"][] = $itm["id"];
            $sel["rto_id"] = $sq["rto_id"];
            if ($cq["ins_id"] == 3)
                $sel["ins_combo"] = $sq["ins_details"]["combo"]["id"];
            foreach ($cq["apack_details"]["core"] as $itm)
                $sel["apack_core"][] = $itm["id"];
            foreach ($cq["apack_details"]["extra"] as $itm)
                $sel["apack_extra"][] = $itm["id"];
            $cqo = array("license_fee" => $cq["license_fee"], "training_fee" => $cq["training_fee"], "fame" => $cq["fame"],  "rsa_amount" => $cq["rsa_amount"], "shield_amount" => $cq["shield_amount"], "ins_amount" => $cq["ins_amount"], "rto_amount" => $cq["rto_amount"], "apack_amount" => $cq["apack_amount"], "apack_disc" => $cq["apack_disc"],  "rsa_disc" => $cq["rsa_disc"], "shield_disc" => $cq["shield_disc"], "extra_disc" => $cq["extra_disc"], "corp_disc" => $cq["corp_disc"], "enl_disc" => $cq["enl_disc"],  "tcs" => $cq["tcs"],  "onroad" => $cq["onroad"],  "invoice" => $cq["invoice"], "remarks" => $cq["remarks"]);
            $sqo = array("license_fee" => $sq["license_fee"], "training_fee" => $sq["training_fee"], "fame" => $sq["fame"], "rsa_amount" => $sq["rsa_amount"], "shield_amount" => $sq["shield_amount"], "ins_amount" => $sq["ins_amount"], "rto_amount" => $sq["rto_amount"], "apack_amount" => $sq["apack_amount"], "apack_disc" => $sq["apack_disc"],  "rsa_disc" => $sq["rsa_disc"], "shield_disc" => $sq["shield_disc"], "extra_disc" => $sq["extra_disc"], "corp_disc" => $sq["corp_disc"], "enl_disc" => $sq["enl_disc"],  "tcs" => $sq["tcs"],  "onroad" => $sq["onroad"],  "invoice" => $sq["invoice"], "remarks" => $sq["remarks"]);

            $qdata = array("quote_id" => $qid, "enq_id" => $enq->enq_id, "vid" => $enq->vehicle_id, "pl_id" => $qrec->pl_id, "customer" => $cust, "sq" => $sqo, "cq" => $cqo, "selection" => $sel);
            //dd($qdata);
            return $qdata;
        }

        return false;
    }
}
