<?php

namespace App\Helpers;

use App\User;
use Auth;

use App\Models\Communication;
use App\Models\Thread;


use VehicleHelper;

use App\Helpers\CommonHelper;
use QuotesHelper;

use Carbon\Carbon;
//Carbon::parse($qrec->created_at)->format('Y-m-d H:i:s')
class ChatHelper
{
    public static function get_communication($type, $id)
    {
        $thread = Communication::where('type', $type)->where('reference_id', $id)->first();
        $data = array("status" => array(), "comm" => array());
        if ($thread) {
            //Carbon::createFromTimeStamp(strtotime($comment->created_at))->diffForHumans()
            $chats = Thread::where('comm_id', $thread->id)->orderBy('sequence', 'ASC')->where('status', 2)->get();
            foreach ($chats as $chat) {
                $row = array("timestamp" => $chat->created_at->diffForHumans(), "actor_id" => $chat->created_by, "actor" => CommonHelper::getUserName($chat->created_by), "details" => $chat->content, "action" => $chat->remark, "image" => '');
                $row["image"] = $chat->getFirstMediaUrl('thread-docs');
                if (empty($row["image"]))
                    $row["image"] = false;

                $data['comm'][] = $row;
            }
            $chats = Thread::where('comm_id', $thread->id)->orderBy('sequence', 'DESC')->where('status', 1)->get();
            foreach ($chats as $chat) {
                $row = array("timestamp" => $chat->created_at->diffForHumans(), "actor_id" => $chat->created_by, "actor" => CommonHelper::getUserName($chat->created_by), "details" => $chat->content, "action" => $chat->remark, "image" => '');
                $row["image"] = $chat->getFirstMediaUrl('thread-docs');
                if (empty($row["image"]))
                    $row["image"] = false;

                $data['status'][] = $row;
            }
        } else {
            return false;
        }
        return $data;
    }

    public static function add_communication($type, $sub, $content, $refid, $rem = null, $assignee = null, $depart = null, $relation = null, $extra = null, $file = null)
    {
        //print_r("<br>Adding Communication for Type : $type, Subject : $sub, Content  : $content, Ref : $refid, Remarl : $rem, For : $assignee");
        $comm = Communication::where('type', $type)->where('reference_id', $refid)->where('subject', 'Quote Created')->first();
        if (!$comm) {
            $comm = new Communication;
            $comm->type = $type;
            $comm->subject = $sub;
            $comm->content = $content;
            $comm->reference_id = $refid;
            $comm->remark = $rem;
            $comm->assigned_to = $assignee;
            $comm->department = $depart;
            $comm->related_to = $relation;
            $comm->extra_data = $extra;
            $comm->save();
            //print_r("<br>New Comm Created");
            if (!empty($file))
                $comm->addMedia($file)->toMediaCollection('comm-docs'); //
        }
        //print_r($comm);
        //die();
        return $comm->id;
    }

    public static function get_commid($type, $ref, $sub)
    {
        $comm = Communication::where('type', $type)->where('reference_id', $ref)->where('subject', $sub)->first();
        if ($comm)
            return $comm->id;
        else
            return false;
    }

    public static function add_followup($commid, $content, $remark, $file = null, $stt = 2)
    {
        $comm = Communication::find($commid);
        if (!$comm) {
            return "Master Comm not found";
        } else {
            $oth = Thread::where("comm_id", $commid)->orderBy("sequence", "DESC")->first();
            if (!$oth)
                $seq = 1;
            else
                $seq = $oth->sequence + 1;
            $thr = new Thread;
            $thr->comm_id = $commid;
            $thr->sequence = $seq;
            $thr->content = $content;
            $thr->remark = $remark;
            $thr->status = $stt;
            $thr->save();
            if (!empty($file))
                $thr->addMedia($file)->toMediaCollection('thread-docs');
            return $thr->id;
        }
        return $comm->id;
    }
}
