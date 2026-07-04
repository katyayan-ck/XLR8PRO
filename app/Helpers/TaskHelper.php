<?php

namespace App\Helpers;

use App\User;
use Auth;

use App\Models\ToDo;
use App\Models\Tasker;


use ChatHelper;
use CommonHelper;
use NotificationHelper;

use Carbon\Carbon;
//Carbon::parse($qrec->created_at)->format('Y-m-d H:i:s')
class TaskHelper
{
    public static function getTypes()
    {
        $data = CommonHelper::enumGetValues("TASK_TYPE");
        $res = array();
        foreach ($data as $rec) {
            $res[] = array('id' => $rec['id'], 'name' => $rec['value']);
        }
        return $res;
    }

    public static function getPriority()
    {
        $data = CommonHelper::enumGetValues("TASK_PRIORITY");
        $res = array();
        foreach ($data as $rec) {
            $res[] = array('id' => $rec['id'], 'name' => $rec['value']);
        }
        return $res;
    }

    public static function getTaskList($uid, $type = null, $api = false)
    {
        if ($type == null)
            $tasks = Tasker::where('member_id', $uid)->get();
        elseif (strtoupper($type) == "ASSIGNED")
            $tasks = Tasker::where('member_id', $uid)->where('association', 'ASSIGNEE')->get();
        elseif (strtoupper($type) == "CREATED")
            $tasks = Tasker::where('member_id', $uid)->where('association', 'OWNER')->get();
        elseif (strtoupper($type) == "FOLLOWED")
            $tasks = Tasker::where('member_id', $uid)->where('association', 'FOLLOWER')->get();
        elseif (strtoupper($type) == "SNOOPED")
            $tasks = Tasker::where('member_id', $uid)->where('association', 'SNOOPER')->get();
        $data = array();
        foreach ($tasks as $task) {
            $temp = self::getTaskInfo($task->task_id, $api);

            $data[] = $temp;
        }
        return $data;
    }

    public static function getTeam()
    {
        $id = auth()->user()->id;
        $tdata = User::where('id', '<>', $id)->get();
        $data = array();
        foreach ($tdata as $rec) {
            $departs = $rec->get_departments();
            $dept = "";
            foreach ($departs as $depart);
            if ($dept == "")
                $dept = $depart["name"];
            else
                $dept .= ", " . $depart["name"];

            $data[] = array("id" => $rec->id, "name" => $rec->id . " - $dept - " . $rec->name . " - " . $rec->mobile);
        }
        return $data;
    }

    public static function deleteTask($uid, $id)
    {
        $task = ToDo::find($id);
        if ($task) {
            if ($task->owner == $uid) {
                Tasker::where('task_id', $id)->forcedelete();
                $task->forcedelete();
                return true;
            }
        }
        return false;
    }

    public static function getTask($id)
    {
        $data = array();
        $task = ToDo::find($id);
        // print_r($task->toArray());
        $comm = ChatHelper::get_communication(2, $id);
        $data['task_id'] = $task->id;
        $data['task_type'] = CommonHelper::enumValueById($task->task_type);
        $data['type_id'] = $task->task_type;
        $data['task_priority'] = CommonHelper::enumValueById($task->priority);
        $data['priority_id'] = $task->priority;
        $data['title'] = $task->title;
        $data['details'] = $task->details;
        if (!empty($task->deadline)) {
            $now = Carbon::now();
            $dd = Carbon::parse($task->deadline);
            $data['deadline'] = $dd->format('d/m/Y');
            $data['time_in_due'] = $dd->diffInDays($now);
            $data['overdue'] = ($now->gte($dd)) ? true : false;
            if ($data['overdue'])
                $data['time_in_due'] *= -1;
        } else {
            $data['deadline'] = null;
            $data['time_in_due'] = "0";
            $data['overdue'] = null;
        }

        $data['age'] = Carbon::parse($task->created_at)->diffInDays();
        $data['creator'] = CommonHelper::getUserName($task->owner);
        $data['created_at'] = Carbon::parse($task->created_at)->format('d/m/Y H:i:s');
        $data['id_assignee'] = array();
        $data['assignee'] = array();
        $data['id_follower'] = array();
        $data['follower'] = array();
        $data['id_snooper'] = array();
        $data['snooper'] = array();
        $data['image'] = $task->getFirstMediaUrl('docs');
        if (empty($data['image']))
            $data['image'] = null;
        $data['history'] = array('status' => array(), 'communication' => array());
        $tmp = explode(",", $task->assignee);
        foreach ($tmp as $uid) {
            $data['assignee'][] = CommonHelper::getUserName($uid);
            $data['id_assignee'][] = $uid;
        }
        if (!empty($task->follower)) {
            $tmp = explode(",", $task->follower);
            foreach ($tmp as $uid) {
                $data['follower'][] = CommonHelper::getUserName($uid);
                $data['id_follower'][] = $uid;
            }
        }
        if (!empty($task->snooper)) {
            $tmp = explode(",", $task->snooper);
            foreach ($tmp as $uid) {
                $data['snooper'][] = CommonHelper::getUserName($uid);
                $data['id_snooper'][] = $uid;
            }
        }
        if ($task->status == 1)
            $tst = "FRESH";
        elseif ($task->status == 2)
            $tst = "INPROGRESS";
        elseif ($task->status == 3)
            $tst = "HOLD";
        elseif ($task->status == 4)
            $tst = "SUBMITTED";
        elseif ($task->status == 5)
            $tst = "CLOSED";
        elseif ($task->status == 6)
            $tst = "REOPENED";
        $data['status'] = $tst;
        $data['user_can'] = array();
        if (!empty($comm['status'])) {
            //dd($comm);
            foreach ($comm['status'] as $his) {

                $data['history']['status'][] = $his;
            }
        }
        if (!empty($comm['comm'])) {
            //dd($comm);
            foreach ($comm['comm'] as $his) {
                $data['history']['communication'][] = $his;
            }
        }
        $cuser = Auth::user()->id;
        $data['user_id'] = $cuser;
        $data['user_name'] = CommonHelper::getUserName($cuser);
        if ($cuser == $task->owner) {
            if ($data['status'] == "CLOSED") {
                $data['user_can'][] = array('id' => 0, 'action' => "NO-CHANGE");
                $data['user_can'][] = array('id' => 6, 'action' => "REOPEN");
            } else {
                $data['user_can'][] = array('id' => 0, 'action' => "NO-CHANGE");
                $data['user_can'][] = array('id' => 2, 'action' => "INPROGRESS");
                $data['user_can'][] = array('id' => 3, 'action' => "HOLD");
                $data['user_can'][] = array('id' => 5, 'action' => "CLOSE");
            }
            $data['user'] = "CREATOR";
        } elseif (in_array($cuser, $data['id_assignee'])) {
            $data['user_can'][] = array('id' => 0, 'action' => "NO-CHANGE");
            $data['user_can'][] = array('id' => 2, 'action' => "INPROGRESS");
            $data['user_can'][] = array('id' => 3, 'action' => "HOLD");
            $data['user_can'][] = array('id' => 4, 'action' => "SUBMIT FOR REVIEW");
            $data['user'] = "ASSIGNEE";
        } elseif (in_array($cuser, $data['id_follower'])) {
            $data['user'] = "FOLLOWER";
        } elseif (in_array($cuser, $data['id_snooper'])) {
            $data['user'] = "SNOOPER";
        } else {
            $pata = array('user' => "UNAUTHORISED");
            $pata['user_id'] = $cuser;
            $pata['user_name'] = CommonHelper::getUserName($cuser);
            /* $pata['id_assignee'] = $data['id_assignee'];
					$pata['assignee'] = $data['assignee'] ;
					$pata['id_follower'] = $data['id_follower'];
					$pata['follower'] = $data['follower'];
					$pata['id_snooper'] = $data['id_snooper'];
				$pata['snooper'] = $data['snooper']; */
            $data = $pata;
        }
        //dd($data);

        return $data;
    }



    public static function updateTask($tid, $title, $type, $priority, $uid, $assignee, $follower, $snooper, $fdoc = null, $details, $deadline = null)
    {
        $td = ToDo::find($tid);
        if ($td) {
            if ($td->owner == $uid) {
                $td->title = $title;
                $td->task_type = $type;
                $td->priority = $priority;
                $td->owner = $uid;
                $td->assignee = implode(",", $assignee);
                $td->follower = implode(",", $follower);
                $td->snooper = implode(",", $snooper);
                $td->details = $details;
                if (!empty($deadline))
                    $td->deadline = Carbon::createFromFormat('d/m/Y', $deadline);
                $td->save();
                if ($td) {
                    Tasker::where('task_id', $tid)->forcedelete();

                    $commid = ChatHelper::get_commid(2, $tid, "Task Created");
                    ChatHelper::add_followup($commid, $td->title, "Task Edited", null, 1);
                    NotificationHelper::notify($td->owner, "Task Edited. Id : " . $td->id, $td->title, 2, $td->id, "N");
                    $ac = 0;
                    $tmp = new Tasker;
                    $tmp->task_id = $td->id;
                    $tmp->member_id = $uid;
                    $tmp->association = "OWNER";
                    $tmp->save();
                    foreach ($assignee as $person) {
                        $tmp = new Tasker;
                        $tmp->task_id = $td->id;
                        $tmp->member_id = $person;
                        $tmp->association = "ASSIGNEE";
                        $tmp->save();
                        $ac++;
                        NotificationHelper::notify($person, "Task Edited  Id : " . $td->id, $td->title, 2, $td->id, "N");
                    }
                    if ($ac > 1) {
                        $td->group_task = 1;
                        $td->save();
                    }

                    if (!empty($follower)) {
                        foreach ($follower as $person) {
                            $tmp = new Tasker;
                            $tmp->task_id = $td->id;
                            $tmp->member_id = $person;
                            $tmp->association = "FOLLOWER";
                            $tmp->save();
                            NotificationHelper::notify($person, "Task Edited  Id : " . $td->id, $td->title, 2, $td->id, "N");
                        }
                    }

                    if (!empty($snooper)) {
                        foreach ($snooper as $person) {
                            $tmp = new Tasker;
                            $tmp->task_id = $td->id;
                            $tmp->member_id = $person;
                            $tmp->association = "SNOOPER";
                            $tmp->save();
                            NotificationHelper::notify($person, "Task Edited  Id : " . $td->id, $td->title, 2, $td->id, "N");
                        }
                    }
                    //die;
                    if (!empty($fdoc)) {
                        $td->addMedia($fdoc)->toMediaCollection('docs'); //
                    }

                    return $td->id;
                } else
                    return false;
            }
        }
        return false;
    }


    public static function addFollowUp($tid, $uid, $remark, $status, $file)
    {
        $td = ToDo::find($tid);
        if ($td) {
            $assignee = explode(",", $td->assignee);
            $follower = explode(",", $td->follower);
            $snooper = explode(",", $td->snooper);
            if ($uid == $td->owner)
                $urole = "CREATOR";
            elseif (in_array($uid, $assignee))
                $urole = "ASSIGNEE";
            elseif (in_array($uid, $follower))
                $urole = "FOLLOWER";
            elseif (in_array($uid, $snooper))
                $urole = "SNOOPER";
            else
                $urole = "UNAUTHORISED";
            // print_r("<br> User is : $urole");
            if ($urole != "SNOOPER" && $urole != "UNAUTHORISED") {
                $commid = ChatHelper::get_commid(2, $td->id, "Task Created");

                $act = false;
                $user = CommonHelper::getUserName($uid);
                if ($status == 0) {
                    // print_r("<br> Status not changed");
                    $sub = $user . " added a remark";

                    ChatHelper::add_followup($commid, $remark, $sub, $file, 2);
                    $act = true;
                } else {
                    //  print_r("<br> Status Changed");
                    $action = "Staus Changed";
                    $pr = $td->status;
                    if ($pr == 1)
                        $pre = "FRESH";
                    elseif ($pr == 2)
                        $pre = "INPROGRESS";
                    elseif ($pr == 3)
                        $pre = "HOLD";
                    elseif ($pr == 4)
                        $pre = "SUBMITTED";
                    elseif ($pr == 5)
                        $pre = "CLOSE";
                    elseif ($pr == 6)
                        $pre = "REOPEN";
                    if ($status == 1)
                        $post = "FRESH";
                    elseif ($status == 2)
                        $post = "INPROGRESS";
                    elseif ($status == 3)
                        $post = "HOLD";
                    elseif ($status == 4)
                        $post = "SUBMITTED";
                    elseif ($status == 5)
                        $post = "CLOSE";
                    elseif ($status == 6)
                        $post = "REOPEN";
                    //print_r("<br> from $pre to $post");
                    if ($urole == "ASSIGNEE") {
                        if (($pr == 1 || $pr == 2 || $pr == 3) && ($status == 2 || $status == 3 || $status == 4))
                            $act = true;
                    } elseif ($urole == "CREATOR") {
                        if (($pr == 4) && ($status == 5 || $status == 6))
                            $act = true;
                        elseif (($pr < 4) && ($status == 2 || $status == 3 || $status == 5))
                            $act = true;
                    }
                    if ($act) {

                        $sub = $user . " changed status from $pre to $post";
                        //$commid, $content, $remark, $file = null, $stt = 2
                        //print_r("<br> Act is true, adding followup with subject : $sub");
                        ChatHelper::add_followup($commid, $remark, $sub, $file, 1);
                        $td->status = $status;
                        $td->save();
                        // print_r("<br> Follow up added status changed");
                    }
                }
                if ($act) {
                    // print_r("<br> Act is true, sending notifications");
                    foreach ($assignee as $pr)
                        if (!empty($pr))
                            NotificationHelper::notify($pr, $sub, $remark, 2, $td->id, "N");
                    foreach ($follower as $pr)
                        if (!empty($pr))
                            NotificationHelper::notify($pr, $sub, $remark, 2, $td->id, "N");
                    foreach ($snooper as $pr)
                        if (!empty($pr))
                            NotificationHelper::notify($pr, $sub, 2, $td->id, "N");
                    NotificationHelper::notify($td->owner, $sub, $remark, 2, $td->id, "N");
                }
            } else
                $act = false;
        } else
            $act = false;

        return $act;
    }


    public static function createTask($title, $type, $priority, $uid, $assignee, $follower = null, $snooper = null, $fdoc = null, $details, $deadline = null)
    {
        $td = new ToDo;
        $td->title = $title;
        $td->task_type = $type;
        $td->priority = $priority;
        $td->owner = $uid;
        $td->assignee = implode(",", $assignee);
        if (!empty($follower))
            $td->follower = implode(",", $follower);
        if (!empty($snooper))
            $td->snooper = implode(",", $snooper);
        $td->details = $details;
        if (!empty($deadline))
            $td->deadline = Carbon::createFromFormat('d/m/Y', $deadline);
        $td->save();
        if ($td) {
            ChatHelper::add_communication(2, "Task Created", $td->title, $td->id);
            $commid = ChatHelper::get_commid(2, $td->id, "Task Created");
            //  $commid, $content, $remark, $file = null, $stt = 2
            ChatHelper::add_followup($commid, $td->title, "Task Created", null, 1);
            NotificationHelper::notify($td->owner, "New Task Created. Id : " . $td->id, $td->title, 2, $td->id, "N");
            $ac = 0;
            $tmp = new Tasker;
            $tmp->task_id = $td->id;
            $tmp->member_id = $uid;
            $tmp->association = "OWNER";
            $tmp->save();
            foreach ($assignee as $person) {
                $tmp = new Tasker;
                $tmp->task_id = $td->id;
                $tmp->member_id = $person;
                $tmp->association = "ASSIGNEE";
                $tmp->save();
                $ac++;
                NotificationHelper::notify($person, "New Task Assigned : " . $td->id, $td->title, 2, $td->id, "N");
            }
            if ($ac > 1) {
                $td->group_task = 1;
                $td->save();
            }

            if (!empty($follower)) {
                foreach ($follower as $person) {
                    $tmp = new Tasker;
                    $tmp->task_id = $td->id;
                    $tmp->member_id = $person;
                    $tmp->association = "FOLLOWER";
                    $tmp->save();
                    NotificationHelper::notify($person, "New Task to Follow : " . $td->id, $td->title, 2, $td->id, "N");
                }
            }

            if (!empty($snooper)) {
                foreach ($snooper as $person) {
                    $tmp = new Tasker;
                    $tmp->task_id = $td->id;
                    $tmp->member_id = $person;
                    $tmp->association = "SNOOPER";
                    $tmp->save();
                    NotificationHelper::notify($person, "New Task to Snoop : " . $td->id,  $td->title, 2, $td->id, "N");
                }
            }
            //die;
            if (!empty($fdoc)) {
                $td->addMedia($fdoc)->toMediaCollection('docs'); //
            }

            return $td->id;
        } else
            return false;
    }

    public static function getTaskInfo($tid, $api = false)
    {
        $trec = ToDo::find($tid);
        $members = Tasker::where('task_id', $tid)->get();
        // print_r("<br>Menebers : <br>");
        // print_r($members->toarray());
        $temp = array();
        $temp['id'] = $trec->id;
        $temp['task_type'] = CommonHelper::enumValueById($trec->task_type);
        $temp['type_id'] = $trec->task_type;
        $temp['task_priority'] = CommonHelper::enumValueById($trec->priority);
        $temp['priority_id'] = $trec->priority;
        $temp['title'] = $trec->title;
        $temp['details'] = $trec->details;

        $temp['assignee'] = array();
        $temp['id_assignee'] = array();
        $temp['follower'] = array();
        $temp['id_follower'] = array();
        $temp['snooper'] = array();
        $temp['id_snooper'] = array();
        $temp['owner'] = array();
        $temp['id_owner'] = array();


        foreach ($members as $tm) {
            //print_r("<br>" . $tm->member_id . " " . $tm->association);
            if ($tm->association == "OWNER") {
                $temp['owner'][] = CommonHelper::getUserName($tm->member_id);
                $temp['id_owner'][] = $tm->member_id;
            } elseif ($tm->association == "ASSIGNEE") {
                $temp['assignee'][] =  CommonHelper::getUserName($tm->member_id);
                $temp['id_assignee'][] = $tm->member_id;
            } elseif ($tm->association == "FOLLOWER") {
                $temp['follower'][] =  CommonHelper::getUserName($tm->member_id);
                $temp['id_follower'][] = $tm->member_id;
            } elseif ($tm->association == "SNOOPER") {
                $temp['snooper'][] = CommonHelper::getUserName($tm->member_id);
                $temp['id_snooper'][] = $tm->member_id;
            }
        }


        if ($trec->status == 1)
            $tst = "FRESH";
        elseif ($trec->status == 2)
            $tst = "INPROGRESS";
        elseif ($trec->status == 3)
            $tst = "HOLD";
        elseif ($trec->status == 4)
            $tst = "SUBMITTED";
        elseif ($trec->status == 5)
            $tst = "CLOSED";
        elseif ($trec->status == 6)
            $tst = "REOPENED";
        if (!empty($trec->deadline)) {
            $now = Carbon::now();
            $dd = Carbon::parse($trec->deadline);
            $temp['deadline'] = $dd->format('d/m/Y');
            $temp['time_in_due'] = $dd->diffInDays($now);
            $temp['overdue'] = ($now->gte($dd)) ? true : false;
            if ($temp['overdue'])
                $temp['time_in_due'] *= -1;
        } else {
            $temp['deadline'] = null;
            $temp['time_in_due'] = "0";
            $temp['overdue'] = null;
        }

        $temp['age'] = Carbon::parse($trec->created_at)->diffInDays();
        $temp['created_at'] = Carbon::parse($trec->created_at)->format('d/m/Y H:i:s');
        $temp['status'] = $tst;
        $temp['media'] = $trec->getFirstMediaUrl('docs');
        return $temp;
    }
}
