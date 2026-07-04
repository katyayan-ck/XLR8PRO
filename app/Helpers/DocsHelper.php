<?php

namespace App\Helpers;

use App\User;
use Auth;


use App\Models\Utilities\Docs\Documents;
use App\Models\Utilities\Docs\DocsGroups;
use App\Models\Utilities\Docs\DocsFilters;
use App\Models\Utilities\Docs\DocUser;

use Carbon\Carbon;

use VehicleHelper;
use CommonHelper;

use Spatie\MediaLibraryPro\Rules\Concerns\ValidatesMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\MediaStream;

class DocsHelper
{



    public static function getCatTree()
    {
        $data = array();
        return $data;
    }

    public static function getSubCatList()
    {
        $data = array();
        return $data;
    }

    public static function getDoc($did, $uid = null, $admin = false)
    {
        $doc = Documents::find($did);

        if ($doc) {
            $usrs = explode(",", $doc->users);
            if (in_array($uid, $usrs)) {
                $row = array();
                $row['id'] = $doc->id;
                $row['entity_id'] = $doc->entity_id;
                $row['entity_name'] = $doc->entity;
                $row['location_id'] = $doc->location_id;
                $row['location_name'] = $doc->location;
                $row['category_id'] = $doc->cat_id;
                $row['category_name'] = $doc->category;
                $row['subcategory_id'] = $doc->sub_cat_id;
                $row['subcategory_name'] = $doc->sub_category;
                $row['item_id'] = $doc->item_id;
                $row['item_name'] = $doc->item;
                $row['pid'] = $row['fy_id'] = $doc->fy_id;
                $row['fy_name'] = $doc->fy;
                $row['path'] = $doc->entity . " > " . $doc->location . " > " . $doc->category . " > " . $doc->sub_category . " > " . $doc->item . " > " . $doc->fy;
                $row['name'] = $doc->name;
                $row['details'] = $doc->remark;
                $row['type_id'] = $doc->type;
                if ($doc->type == 1) {
                    $row['type'] = "Image";
                    $row['url'] = $doc->getFirstMediaUrl('Image');
                    $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><img src="' . $row['url'] . '" id="frame" class="img-fluid" width="100" ></a>';
                } elseif ($doc->type == 2) {
                    $row['type'] = "Document";
                    $row['url'] = $doc->getFirstMediaUrl('Doc');
                    $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><i class="ik ik-file-text"></i>' . $row['name'] . '</a>';
                } else {
                    $row['type'] = "Information";
                    $row['url'] = null;
                    $row['preview'] = '---';
                }
                if ($admin) {
                    $row['user_ids'] = explode(",", $doc->users);
                    $row['users'] = array();
                    foreach ($row['user_ids'] as $tmp)
                        $row['users'][] = CommonHelper::getUsername($tmp);
                }
                return $row;
            }
        } else
            return false;
    }

    public static function getAllDocs()
    {
        $docs = Documents::orderby('entity', 'asc')->orderBy('location', 'asc')->orderBy('item', 'asc')->orderBy('fy', 'asc')->get();
        foreach ($docs as $doc) {

            $row = array();
            $row['id'] = $doc->id;
            $row['pid'] = $doc->fy_id;
            $row['path'] = $doc->entity . " > " . $doc->location . " > " . $doc->category . " > " . $doc->sub_category . " > " . $doc->item . " > " . $doc->fy;
            $row['name'] = $doc->name;
            $row['details'] = $doc->remark;
            if ($doc->type == 1) {
                $row['type'] = "Image";
                $row['url'] = $doc->getFirstMediaUrl('Image');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><img src="' . $row['url'] . '" id="frame" class="img-fluid" width="100"></a>';
            } elseif ($doc->type == 2) {
                $row['type'] = "Document";
                $row['url'] = $doc->getFirstMediaUrl('Doc');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><i class="ik ik-file-text"></i>' . $row['name'] . '</a>';
            } else {
                $row['type'] = "Information";
                $row['url'] = null;
                $row['preview'] = '---';
            }

            $data[] = $row;
        }
        return $data;
    }

    public static function getUserDocs($uid)
    {
        $docs = DocUser::where('user_id', $uid)->get();
        $cart = DocsGroups::where('user_id', $uid)->where('name', "_temp")->first();
        if ($cart)
            $docsincart = explode(",", $cart->docs);
        else
            $docsincart = array();
        $data = array();
        foreach ($docs as $udoc) {
            $doc = Documents::find($udoc->doc_id);
            $row = array();
            $row['id'] = $doc->id;
            $row['entity_id'] = $doc->entity_id;
            $row['entity_name'] = $doc->entity;
            $row['location_id'] = $doc->location_id;
            $row['location_name'] = $doc->location;
            $row['category_id'] = $doc->cat_id;
            $row['category_name'] = $doc->category;
            $row['subcategory_id'] = $doc->sub_cat_id;
            $row['subcategory_name'] = $doc->sub_category;
            $row['item_id'] = $doc->item_id;
            $row['item_name'] = $doc->item;
            $row['pid'] = $row['fy_id'] = $doc->fy_id;
            $row['fy_name'] = $doc->fy;
            $row['path'] = $doc->entity . " > " . $doc->location . " > " . $doc->category . " > " . $doc->sub_category . " > " . $doc->item . " > " . $doc->fy;
            $row['name'] = $doc->name;
            $row['details'] = $doc->remark;
            if ($doc->type == 1) {
                $row['type'] = "Image";
                $row['url'] = $doc->getFirstMediaUrl('Image');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><img src="' . $row['url'] . '" id="frame" class="img-fluid" width="100"></a>';
            } elseif ($doc->type == 2) {
                $row['type'] = "Document";
                $row['url'] = $doc->getFirstMediaUrl('Doc');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><i class="ik ik-file-text"></i>' . $row['name'] . '</a>';
            } else {
                $row['type'] = "Information";
                $row['url'] = null;
                $row['preview'] = '---';
            }
            if (in_array($doc->id, $docsincart))
                $row['in_cart'] = true;
            else
                $row['in_cart'] = false;
            $data[] = $row;
        }
        return $data;
    }

    public static function getGroups($uid)
    {
        // dd($uid);
        $groups = DocsGroups::where('user_id', $uid)->where('docs_count', '>', 0)->get();
        $data = array();
        foreach ($groups as $group) {
            $row = array();
            $row['id'] = $group->id;
            $row['name'] = $group->name;
            $row['details'] = $group->purpose;
            $row['docs'] = explode(",", $group->docs);
            $row['count'] = $group->docs_count;
            if ($row['name'] == "_temp")
                $row['cart'] = true;
            else
                $row['cart'] = false;
            $data[] = $row;
        }
        //dd($data);
        return $data;
    }

    public static function addToGroup($did, $gid, $uid)
    {
        ////print_r("<br>Searching for GID : $gid, UID : $uid, DID : $did<br>");
        if ($gid != 0) {

            $group = DocsGroups::where('user_id', $uid)->where('id', $gid)->first();
            if (!$group)
                return false;
        } else {
            $group = DocsGroups::where('user_id', $uid)->where('name', "_temp")->first();
            if (!$group) {
                $group = new DocsGroups;
                $group->name = "_temp";
                $group->user_id = $uid;
                $group->save();
            }
        }
        ////print_r("...Found it..");
        $docs = explode(",", $group->docs);
        if (!in_array($did, $docs)) {
            $docs[] = $did;
            $group->docs = implode(",", $docs);
            $group->docs_count++;
            $group->save();
            ////print_r("...Added..");
        }
        return true;
    }

    public static function doxInGroup($uid, $id)
    {
        $dg = DocsGroups::find($id);
        $docsa = explode(",", $dg->docs);
        $docsa = array_filter($docsa);
        //  ////print_r($docsa);
        $data = array(); //whereRaw("find_in_set($uid , users)")
        $docs = Documents::whereIn('id', $docsa)->get();
        // ////print_r("<br>...<br>...");
        // ////print_r($docs->toarray());
        foreach ($docs as $doc) {

            $row = array();
            $row['id'] = $doc->id;
            $row['pid'] = $doc->fy_id;
            $row['path'] = $doc->entity . " / " . $doc->location . " / " . $doc->item . " / " . $doc->fy;
            $row['name'] = $doc->name;
            $row['details'] = $doc->remark;
            if ($doc->type == 1) {
                $row['type'] = "Image";
                $row['url'] = $doc->getFirstMediaUrl('Image');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><img src="' . $row['url'] . '" id="frame" class="img-fluid" width="100"></a>';
            } elseif ($doc->type == 2) {
                $row['type'] = "Document";
                $row['url'] = $doc->getFirstMediaUrl('Doc');
                $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><i class="ik ik-file-text"></i>' . $row['name'] . '</a>';
            } else {
                $row['type'] = "Information";
                $row['url'] = null;
                $row['preview'] = '---';
            }

            $data[] = $row;
        }
        return $data;
    }

    public static function doxOutGroup($uid, $id)
    {
        $dg = DocsGroups::find($id);
        $docsa = explode(",", $dg->docs);
        $docsa = array_filter($docsa);
        $data = array(); //
        $docs = Documents::whereNotIn('id', $docsa)->get();
        //////print_r($docs->toArray());
        // ////print_r("<br>...<br>...");
        foreach ($docs as $doc) {
            //  ////print_r("<br>...<br>...");
            // ////print_r($doc->toArray());
            $users = explode(",", $doc->users);
            if (in_array($uid, $users)) {
                //   ////print_r("<br>...<br>...");
                //  ////print_r("Adding");
                $row = array();
                $row['id'] = $doc->id;
                $row['pid'] = $doc->fy_id;
                $row['path'] = $doc->entity . " / " . $doc->location . " / " . $doc->item . " / " . $doc->fy;
                $row['name'] = $doc->name;
                $row['details'] = $doc->remark;
                if ($doc->type == 1) {
                    $row['type'] = "Image";
                    $row['url'] = $doc->getFirstMediaUrl('Image');
                    $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><img src="' . $row['url'] . '" id="frame" class="img-fluid" width="100"></a>';
                } elseif ($doc->type == 2) {
                    $row['type'] = "Document";
                    $row['url'] = $doc->getFirstMediaUrl('Doc');
                    $row['preview'] = '<a href="' . $row['url'] . '" target="_blank"><i class="ik ik-file-text"></i>' . $row['name'] . '</a>';
                } else {
                    $row['type'] = "Information";
                    $row['url'] = null;
                    $row['preview'] = '---';
                }

                $data[] = $row;
            }
        }
        // ////print_r($data);
        return $data;
    }

    public static function getGroup($uid, $id = 0)
    {

        if ($id != 0) {

            $group = DocsGroups::where('user_id', $uid)->where('id', $id)->first();
            if (!$group)
                return false;
        } else {
            $group = DocsGroups::where('user_id', $uid)->where('name', "_temp")->first();
            if (!$group) {
                $group = new DocsGroups;
                $group->name = "_temp";
                $group->save();
            }
        }
        $data = array();
        $data['id'] = $group->id;
        $data['name'] = $group->name;
        $data['details'] = $group->purpose;
        $data['docs'] = explode(",", $group->docs);
        $data['count'] = count($data['docs']);
        if ($data['name'] == "_temp")
            $data['cart'] = true;
        else
            $data['cart'] = false;
        return $data;
    }

    public static function saveGroup($gid, $name, $remark, $uid)
    {

        $group = DocsGroups::find($gid);
        if (!$group || $group->user_id != $uid)
            return false;
        $group->name = $name;
        $group->purpose = $remark;
        $group->save();
        return true;
    }
    public static function removeFromGroup($did, $gid, $uid)
    {
        // dd($uid);
        if ($gid != 0) {
            $group = DocsGroups::where('user_id', $uid)->where('id', $gid)->first();
            if (!$group)
                return false;
        } else {
            $group = DocsGroups::where('user_id', $uid)->where('name', "_temp")->first();
            if (!$group)
                return false;
        }
        $docs = explode(",", $group->docs);
        if (in_array($did, $docs)) {
            foreach ($docs as $key => $doc) {
                if ($doc == $did) {
                    unset($docs[$key]);
                    $group->docs = implode(",", $docs);
                    $group->docs_count--;
                    $group->save();
                }
            }
        }
        return true;
    }

    public static function deleteGroup($gid, $uid)
    {

        $group = DocsGroups::find($gid);
        if (!$group || $group->user_id != $uid)
            return false;
        $group->delete();
        return true;
    }

    public static function getCatUsers($id)
    {
        $data = array();
        $cat = DocsCats::find($id);
        if ($cat) {
            $uids = explode(",", $cat->user_ids);
            $users = User::select('id', 'name')->whereIn('id', $uids)->get()->toArray();
            //////print_r($users);
            return $users;
        } else
            return false;
    }

    public static function getCatAccess($id)
    {
        $data = array("designations" => array(), "departments" => array());
        $cat = DocsCats::find($id);
        if ($cat) {
            $desig = explode(",", $cat->designations);
            foreach ($desig as $tmp)
                $data['designations'][] = array('id' => $tmp, 'name' => CommonHelper::enumValueById($tmp));

            $dept = explode(",", $cat->departments);
            foreach ($dept as $tmp)
                $data['departments'][] = array('id' => $tmp, 'name' => CommonHelper::enumValueById($tmp));

            return $data;
        } else
            return false;
    }

    public static function getFilterUser($fid)
    {
        $rec = DocsFilters::find($fid);
        if ($rec) {
            $data = array("desigs" => array(), "departs" => array(), "users" => array());
            $tmp = explode(",", $rec->desigs);
            foreach ($tmp as $tr)
                $data['desigs'][] = array('id' => $tr, 'name' => CommonHelper::enumValueById($tr));
            $tmp = explode(",", $rec->departs);
            foreach ($tmp as $tr)
                $data['departs'][] = array('id' => $tr, 'name' => CommonHelper::enumValueById($tr));
            $tmp = explode(",", $rec->users);
            foreach ($tmp as $tr)
                $data['users'][] = array('id' => $tr, 'name' => CommonHelper::getUsername($tr));
            return $data;
        } else {
            return false;
        }
    }

    public static function getFilterSpawn($fid)
    {
        $rec = DocsFilters::where('parent', $fid)->get();
        if ($rec) {
            $data = array();
            foreach ($rec as $tr)
                $data[] = array('id' => $tr->id, 'name' => $tr->name);
            return $data;
        } else {
            return false;
        }
    }

    public static function createFilter($type, $pid, $name, $details, $desig, $depart, $user)
    {
        $name = strtoupper($name);
        $type = trim(strtoupper($type));
        $rec = new DocsFilters;
        $rec->type = $type;
        $rec->parent = $pid;
        $rec->name = $name;
        $rec->remark = $details;
        $rec->desigs = implode(",", $desig);
        $rec->departs = implode(",", $depart);
        $rec->users =  implode(",", $user);
        $rec->save();

        if ($rec)
            return $rec->id;
        else
            return false;
    }



    public static function addDocument($file, $type, $cat, $roles = null, $dept = null, $users = null)
    {
        $data = array();
        return $data;
    }



    public static function getFilters($type)
    {
        $type = strtoupper($type);
        $clist = DocsFilters::where('type', $type)->get();
        $data = array();
        foreach ($clist as $crec) {
            $row = array();
            $row['id'] = $crec->id;
            $row['parent'] = self::getParentPath($crec->parent);
            $row['name'] = $crec->name;
            $row['remark'] = $crec->remark;
            $row['designations'] = array();
            $row['departments'] = array();
            $row['users'] = explode(",", $crec->users);
            $row['doc_count'] = $crec->docs;
            $tmp = explode(",", $crec->desigs);
            foreach ($tmp as $tid)
                $row['designations'][] = CommonHelper::enumValueById($tid);
            $tmp = explode(",", $crec->departs);
            foreach ($tmp as $tid)
                $row['departments'][] = CommonHelper::enumValueById($tid);
            $data[] = $row;
        }
        return $data;
    }

    public static function getParentPath($id)
    {
        $data = array();
        $rec = DocsFilters::find($id);
        if ($rec) {
            if ($rec->parent == 0)
                return $rec->name;
            else
                return self::getParentPath($rec->parent) . " > " . $rec->name;
        } else
            return false;
    }
}
