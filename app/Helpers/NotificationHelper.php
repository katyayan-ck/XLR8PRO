<?php

namespace App\Helpers;

use App\User;
use Auth;

use App\Models\Notification;
use App\Models\UserNotif;

use CommonHelper;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

use Carbon\Carbon;
//Carbon::parse($qrec->created_at)->format('Y-m-d H:i:s')
class NotificationHelper
{
    public static function get_notifications($type = "N", $uid = false, $stt = "ALL")
    {
        if (!$uid) {
            $user = Auth::User()->toArray();
            $uid = $user['id'];
        }
        $ntype = array("A" => "Alert", "N" => "Notification", "M" => "Message");
        $rtype = array(1 => "Quote", 2 => "Task", 3 => "VehicleData", 4 => "Pricing");
        $data = array();
        //print_r("<BR><BR>Searching for $type of user $uid <BR><BR>");
        $un = UserNotif::where('user_id', $uid)->where('type', $type);
        if ($stt == "UNREAD")
            $un = $un->where('status', 1);
        elseif ($stt == "READ")
            $un = $un->where('status', 2);
        else
            $un = $un->where('status', '<', 3);

        $un = $un->with('message')->orderBy('created_at', 'desc')->get();
        //print_r($un->toarray());
        if ($un) {
            foreach ($un as $noty) {
                $row = array();
                $row['id'] = $noty->id;
                $row['msg_id'] = $noty->message->id;
                $row['type'] = $ntype[$noty->type];
                $row['master'] = $rtype[$noty->message->ref_type];
                $row['master_id'] = $noty->message->ref_id;
                $row['msg'] = $noty->message->msg;
                $row['sender'] = $noty->message->sender;
                $row['user'] = $uid;
                $row['status'] = $noty->status;
                $row['created_at'] = Carbon::parse($noty->message->created_at)->format('d/m/Y H:i:s');
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public static function get_counts($uid)
    {
        if (!$uid) {
            $user = Auth::User()->toArray();
            $uid = $user['id'];
        }
        $data = array();
        $data['notifications'] = $data['alerts'] = $data['messages'] = array("total" => 0, "read" => 0, "unread" => 0);
        $type = array("N" => "notifications", "A" => "alerts", "M" => "messages");
        $mode = array(1 => "unread", 2 => "read");
        // $mode = null;
        $un = UserNotif::select('id', 'type', 'status')->where('user_id', $uid)->where('status', '<', 3)->get();
        foreach ($un as $rec) {
            // print_r($rec->toarray());
            // print_r("<br>");
            $data[$type[$rec->type]]['total']++;
            $data[$type[$rec->type]][$mode[$rec->status]]++;
        }
        return $data;
    }

    public static function sendNoty($type, $target, $title, $body, $rtype, $rid, $img = false, $data = false, $color = false)
    {
        $firebase = (new Factory)
            ->withServiceAccount('firebase_credentials.json');

        $messaging = $firebase->createMessaging();
        if ($type == "FCM") {
            $message = CloudMessage::fromArray([
                'token' => $target,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => [
                    'type' => $rtype,
                    'id' => $rid,
                ]
            ]);
        } elseif ($type == "TOPIC") {
            $message = CloudMessage::fromArray([
                'topic' => $target,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => [
                    'type' => $rtype,
                    'id' => $rid,
                ]
            ]);
        }

        $messaging->send($message);
        return true;
    }


    public static function getNotyCount($type = "N", $uid = false)
    {
        if (!$uid) {
            $user = Auth::User()->toArray();
            $uid = $user['id'];
        }
        $un = UserNotif::where('user_id', $uid)->where('status', 1)->where('type', $type)->count();
        if ($un) {
            return $un;
        }
        return;
    }

    public static function notify($uid, $ttl, $msg, $ref = 1, $refid = 1, $type = "N")
    {
        $note = new Notification;
        $note->msg = $msg;
        if ($type == "N")
            $tp = 1;
        if ($type == "A")
            $tp = 2;
        if ($type == "M")
            $tp = 3;
        $note->type = $tp;
        $note->target = $uid;
        $note->ref_type = $ref;
        $note->ref_id = $refid;
        $note->save();
        $un = new UserNotif;
        $un->note_id = $note->id;
        $un->user_id = $uid;
        $un->save();
        $rtype = array(1 => "QUOTE", 2 => "TASK");
        //$type, $target, $title, $body, $rtype, $rid, $img = false, $data = false, $color = false
        $fcm = null; //User::find($uid)->fcm_token;
        if (isset($fcm) && !empty($fcm) && $fcm != null)
            self::sendNoty("FCM", $fcm, $ttl, $msg, $rtype[$ref], $refid);
        return;
    }

    public static function update($uid, $nid, $action = "READ")
    {
        if ($action == "READ")
            $stt = 2;
        elseif ($action == "UNREAD")
            $stt = 1;
        elseif ($action == "DELETE")
            $stt = 3;
        if (isset($stt)) {
            $un = UserNotif::where('user_id', $uid)->where('note_id', $nid)->first();
            if ($un) {
                if ($rec->status != $stt) {
                    $rec->status = $stt;
                    $rec->save();
                    if ($stt == 3)
                        $rec->delete();
                    return true;
                }
            }
        }
        return false;
    }

    public static function cleanType($uid, $type, $action = "READ")
    {
        $un = UserNotif::where('user_id', $uid)->where('type', $type)->get();
        $count = 0;
        if ($action == "READ")
            $stt = 2;
        elseif ($action == "UNREAD")
            $stt = 1;
        elseif ($action == "DELETE")
            $stt = 3;
        if (isset($stt)) {
            foreach ($un as $rec) {
                if ($rec->status != $stt) {
                    $rec->status = $stt;
                    $rec->save();
                    if ($stt == 3)
                        $rec->delete();
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function masterPurge($uid, $mtype, $mid, $act = "READ")
    {
        $rtype = array("QUOTE" => 1, "TASK" => 2);
        $actype = array("READ" => 2, "UNREAD" => 1, "DELETE" => 3);
        $mtype = strtoupper($mtype);
        $act = strtoupper($act);
        if (isset($actype[$act])) {
            if (isset($rtype[$mtype]))
                $rft = $rtype[$mtype];
            $nots = Notification::where('target', $uid)->where('ref_type', $rft)->where('ref_id', $mid)->get();
            if ($nots) {
                //print_r("<br>Noty found...");
                $count = 0;
                foreach ($nots as $noty) {
                    $un = UserNotif::where('note_id', $noty->id)->where('user_id', $uid)->get();
                    foreach ($un as $rec) {
                        // print_r("<br>Notif found...");
                        if ($rec->status != $actype[$act]) {
                            // print_r("<br>Status Diff found...");
                            $rec->status = $actype[$act];
                            $rec->save();
                            if ($rec->status == 3)
                                $rec->delete();
                            $count++;
                        }
                    }
                }
                return $count;
            }
        }
        return 0;
    }
}
