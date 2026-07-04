<?php
	namespace App\Helpers;
	use App\User;
	use Auth;
	use DB;
	
	use App\Models\CorpBonusRate;
	use App\Models\Accessories;
	use App\Models\AccPacks;
	use App\Models\Colors;
	use App\Models\VehicleColorPivot;
	use App\Models\ShieldRate;
	use App\Models\RSARate;
	use App\Models\XchangeBonusRate;
	use App\Models\Approver;
	use App\Models\Vehicle;
	use App\Models\VehicleMeta;
	use App\Models\PriceList;
	use App\Models\EnumMaster;
	use Carbon\Carbon;
	
	use CommonHelper;
	use ExtrasHelper;
	use ColorHelper;
	
	class VehicleHelper
	{
		public static function findApprover($vid,$branch)
		{
			//print_r("<br><Br>Showing result for Vehicle : $vid of Branch : $branch<br><Br>");
			$vh = Vehicle::find($vid);
			$cmid = $vh->cm_id;
			$tmp = Approver::select('user_id','emp_code','level','branch','discounting_limit','od_highest')->whereRaw("find_in_set($cmid,model)")->whereRaw("find_in_set($branch,branch)")->orderby('level')->get();
			
			$lvl = 1;
			$apps = array();
			$users = User::with('person')->get();
			foreach($tmp as $app)
			{
				//print_r("<br><br>");
				//print_r($app->toarray());
				
				$apps[$app->level] = array('uid' => $app->user_id, 'level' => $app->level, 'dlimit' => $app->discounting_limit, 'olimit' => $app->od_highest);
				if($apps[$app->level]['dlimit'] == 0)
				$apps[$app->level]['uid'] = 0;
			}
			
			//dd($apps);
			return $apps;
		}
		
		public static function vehicleSyncTime()
		{
			$utime=array();
			$updated = "";
			$utime['vehicle'] = Carbon::parse(Vehicle::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['meta'] = Carbon::parse(VehicleMeta::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['color'] = Carbon::parse(VehicleColorPivot::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['apack'] = Carbon::parse(Accessories::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['corp'] = Carbon::parse(CorpBonusRate::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['xchange'] = Carbon::parse(XchangeBonusRate::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['rsa'] = Carbon::parse(RSARate::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			$utime['shield'] = Carbon::parse(ShieldRate::orderBy('updated_at','DESC')->first()->updated_at)->format('Y-m-d H:i:s');
			foreach($utime as $updt)
			{
				if($updt > $updated)
				$updated = $updt;
			}
			return $updated;
		}
		
		public static function getAllCM()
		{
			$cms = CommonHelper::getCM();
			$vhs = Vehicle::select('id','local_name','mg','mg_id','cm1','cm_id','status')->where('head_type',4)->orderby('cm1')->get();
			$vehicles = $data = array();
			foreach($vhs as $vh)
			{
				if(empty($vh->cm1))
				{
					$vh->cm1 = "UnDefined";
					$vh->cm_id = 0;
				}
				if(!isset($vehicles[$vh->cm_id]))
				$vehicles[$vh->cm_id] = array();
				$vehicles[$vh->cm_id][] = array('name' => $vh->local_name, 'status' => $vh->status, 'mg' => $vh->mg, 'mgid' => $vh->mg_id);
			}
			//dd($cms);
			foreach($cms as $cm)
			{
				//dd($cm);
				$tmp = array("id" => $cm['id'], "model" => $cm['mg'], "cm" => $cm['name'], "avhs" => "", "dvhs" => "", "status" => "Inactive");
				if(isset($vehicles[$cm['id']]))
				{
					
					$flag= 'Inactive';
					foreach($vehicles[$cm['id']] as $itm)
					{
						//$tmp['vehicles'] .= " ".$itm['name'];
						if($itm['status']==1)
						{
							$flag = 'Active';
							if($tmp['avhs'] == "")
							$tmp['avhs'] = $itm['name'];
							else
							$tmp['avhs'] .= ",".$itm['name'];
						}
						else
						{
							if($tmp['dvhs'] == "")
							$tmp['dvhs'] = $itm['name'];
							else
							$tmp['dvhs'] .= ",".$itm['name'];
						}
					}
					$tmp['status']=$flag;
					//CommonHelper::updateCM($key,$vehicles[$key][0]['mg'],$vehicles[$key][0]['mgid']);
				}
				
				$data[] = $tmp;
			}
			array_multisort(
			array_column($data, 'model'), SORT_ASC,
			array_column($data, 'cm'), SORT_ASC,
			$data);
			return $data;
		}
		
		
		public static function getAllMG()
		{
			$vhs = Vehicle::select('id','code','name','local_name','mg','mg_id','cm1','cm_id','status')->where('head_type',2)->get();
			$data =array();
			foreach($vhs as $vh)
			{
				$data[$vh->id] = $vh->code;
			}
			return $data;
		}
		
		
		public static function getAllCMG()
		{
			$cmg = array();
			$vhs = Vehicle::select('id','name','local_name','head_type','parent','mg','mg_id','cm1','cm_id','status')->orderby('head_type')->get();
			$vehicles = $data = array();
			foreach($vhs as $vh)
			{
				if($vh->head_type == 2)
				{
					$cmg[$vh->id]=array('id' => $vh->id, 'mg' => $vh->name, 'cmg' => $vh->local_name);
				}
				if($vh->head_type == 4)
				{
					
					if(!isset($vehicles[$vh->mg_id]))
					$vehicles[$vh->mg_id] = array();
					$vehicles[$vh->mg_id][] = array('name' => $vh->local_name, 'status' => $vh->status, 'mg' => $vh->mg);
				}
			}
			//print_r($cms);
			foreach($cmg as $key=>$val)
			{
				$tmp = array("id" => $key, "mg" => $val['mg'], "cmg" => $val['cmg'], "avhs" => "", "dvhs" => "", "status" => "Inactive");
				if(isset($vehicles[$key]))
				{
					
					$flag= 'Inactive';
					foreach($vehicles[$key] as $itm)
					{
						//$tmp['vehicles'] .= " ".$itm['name'];
						if($itm['status']==1)
						{
							$flag = 'Active';
							if($tmp['avhs'] == "")
							$tmp['avhs'] = $itm['name'];
							else
							$tmp['avhs'] .= ",".$itm['name'];
						}
						else
						{
							if($tmp['dvhs'] == "")
							$tmp['dvhs'] = $itm['name'];
							else
							$tmp['dvhs'] .= ",".$itm['name'];
						}
					}
					$tmp['status']=$flag;
				}
				
				
				$data[] = $tmp;
			}
			array_multisort(
			array_column($data, 'mg'), SORT_ASC,
			array_column($data, 'cmg'), SORT_ASC,
			$data);
			return $data;
		}
		
		
		public static function getFeatures($vid)
		{
			$data = array();
			$mdata = VehicleMeta::where('cm_id',$vid)->where('keyword_id',26)->get();
			if($mdata)
			{
				foreach($mdata as $row)
				{
					$data[] = array(
					'head'=>CommonHelper::enumValueById($row['head_id']),
					'subhead'=>CommonHelper::enumValueById($row['head_id']),
					'value'=>CommonHelper::enumValueById($row['head_id']),
					);
				}
				array_multisort(
				array_column($data, 'head'), SORT_ASC,
				array_column($data, 'subhead'), SORT_ASC,
				$data);
				return $data;
			}
			else
			return false;
		}
		
		
		public static function getSpecification($cm)
		{
			$data = array();
			$mdata = VehicleMeta::where('custom_model',$cm)->where('keyword_id',25)->get();
			if($mdata)
			{
				foreach($mdata as $row)
				{
					$data[] = array(
					'head'=>CommonHelper::enumValueById($row['head_id']),
					'subhead'=>CommonHelper::enumValueById($row['head_id']),
					'value'=>CommonHelper::enumValueById($row['head_id']),
					);
				}
				array_multisort(
				array_column($data, 'head'), SORT_ASC,
				array_column($data, 'subhead'), SORT_ASC,
				$data);
				return $data;
			}
			else
			return false;	
		}
		
		
		public static function getVehicle($vhl)
		{
			$res = Vehicle::find($vhl)->toArray();
			return Response::json($res);
		}
		
		public static function getPendingCount()
		{
			$list = Vehicle::select('id')->where('head_type',4)->where('status',2)->get();
			$count = count($list);
			return $count;
		}
		
		public static function getSegVids($seg,$type = "ACTIVE")
		{
			$vhlist = Vehicle::select('id','cm1')->where('subsegment',$seg)->where('head_type',4)->where('cm1','<>','0')->OrderBy('cm1')->OrderBy('fuel_type_id')->OrderBy('transmission_type')->get(); 
			$data = array();
			foreach($vhlist as $vh)
			$data[] = $vh->id;
			return $data;
		}
		
		public static function getVline($vid)
		{
			$vline = array();
			////print_r("<br>Searching for VID : $vid<br>");
			$vh = Vehicle::select('id','name','code','local_name','segment','subsegment','permit_id','weight','bodymake_id','bodytype_id','fuel_type_id','wheels','cc_capacity','color','status','head_type','parent')->where('id',$vid)->first()->toArray();
			
			$vline['variant'] = (array) $vh;
			////print_r("<br>Level 4<br>");
			////print_r($vline);
			$vh = Vehicle::select('id','name','code','local_name','segment','subsegment','permit_id','weight','bodymake_id','bodytype_id','fuel_type_id','wheels','cc_capacity','color','status','head_type','parent')->where('id',$vh['parent'])->first()->toArray();
			$vline['model'] = (array) $vh;
			
			return $vline;
		}  
		
		public static function getActiveVehilces()
		{
			$vhs = Vehicle::where('head_type',4)->where('status',1)->get();
			
			$accpd = $rsad = $xchd = $shieldd = $corpd = $vmd = $featd = $specd = array();
			//Fetch RSA
			$tmp = RSARate::where('vehicle_id',0)->orderby('cm1')->orderby('coverage')->get();
			$trans = CommonHelper::GetKeyValues("TRANSMISSION");
			$segs = CommonHelper::GetKeyValues("SEGMENT");
			$permits = CommonHelper::GetKeyValues("PERMIT");
			$bts = CommonHelper::GetKeyValues("BODY-TYPE");
			$bms = CommonHelper::GetKeyValues("BODY-MAKE");
			$fuels = CommonHelper::GetKeyValues("FUEL-TYPE");
			$mcolors = $vcolors = array();
			/* 
				foreach($tmp as $dt)
				{
				if(!isset($rsad[$dt->cm1]))
				$rsad[$dt->cm1] = array();
				$rsad[$dt->cm1][] = array('years' => $dt->coverage, 'price' => $dt->amount);
			} */
			//dd($rsad);
			
			//Fetch Master Colors
			$tmp = Colors::get();
			foreach($tmp as $dt)
			{
				$mcolors[$dt->id] = $dt->toarray();
			}
			
			
			//Fetch Vehicle Active Colors
			$tmp = VehicleColorPivot::get();
			foreach($tmp as $dt)
			{
				if($dt->status == 1)
				{
					if(!isset($vcolors[$dt->vehicle_id]))
					$vcolors[$dt->vehicle_id] = array();
					$vcolors[$dt->vehicle_id][] = array(
					'id' => $dt->color_id,
					'code' => $mcolors[$dt->color_id]['code'],
					'name' => $mcolors[$dt->color_id]['color'],
					'hexcode' => $mcolors[$dt->color_id]['hexcode']
					);
				}
			}	
			
			
			
			//Fetch Accessories Packs
			
			/* $tmp = AccPacks::orderby('model')->get();
				foreach($tmp as $dt)
				{
				if(!isset($accpd[$dt->id]))
				$accpd[$dt->id] = array(
				'id' => $dt->id, 'for' => $dt->model, 
				"name" => $dt->name, 'mrp' => $dt->mrp, 
				'msp' => $dt->mpp,
				'pack' => array(), 'extra' => array());
				}
				
				//Fetch Accessories
				$tmp = Accessories::orderby('pack_id')->orderby('fixed')->orderby('name')->get();
				foreach($tmp as $dt)
				{
				
				if($dt->fixed == 1)
				{
				$mndt = ($dt->mendatory == 1)?true:false;
				$accpd[$dt->pack_id]['pack'][] = array('id' => $dt->id, "name" => $dt->name, 'price' => $dt->price, 'mendatory' => $mndt);
				}
				else
				$accpd[$dt->pack_id]['extra'][] = array('id' => $dt->id, "name" => $dt->name, 'price' => $dt->price);
				}
				//$accpd = ExtrasHelper::get_apack($vhs->apack_id); 
				//dd($accpd);
				//Fetch Exchange n Loyalty
				
				
				//dd($xchd);
				$shieldd = ShieldRate::where('vehicle_id',0)->orderby('cm_id')->get()->toarray();
				//dd($shieldd);
				$tmp = CorpBonusRate::where('vehicle_id',0)->orderby('base_model_id')->orderby('id')->get();
				foreach($tmp as $dt)
				{
				if(!isset($corpd[$dt->base_model_id]))
				$corpd[$dt->base_model_id] = array('id' => $dt->base_model_id, 'name' => strtoupper($dt->group_name), 'bonus' =>array());
				$tdisc = $dt->discount + $dt->dealer_discount;
				$corpd[$dt->base_model_id]['bonus'][] = array(
				'id'=> $dt->id,
				'category'=> $dt->category,
				'discount'=> $tdisc
				);
			} */
			//dd($corpd);
			$tmp = EnumMaster::select('id','master_id','value')->whereIn('master_id',[25,26])->get();
			foreach($tmp as $dt)
			$vmd[$dt->id] = $dt->value;
			//dd($vmd);
			//Fetch Specifications $featd = $specd =
			$tmp = VehicleMeta::select('id','head_id','subhead_id','value','cm_id','custom_model','keyword_id')->get();
			foreach($tmp as $dt)
			{
				//print_r("<h3>DT</h3>");print_r($dt->toarray());
				if($dt->keyword_id == 25)//Specifications
				{
					//print_r("<br>Adding Specification : ".$dt->id);
					if(!isset($specd[$dt->cm_id]))
					$specd[$dt->cm_id] = array('id' => $dt->cm_id, 'data' => array());
					if(isset($vmd[$dt->head_id]))
					$fg = $vmd[$dt->head_id];
					else
					$fg = "NA";
					if(isset($vmd[$dt->subhead_id]))
					$fsg = $vmd[$dt->subhead_id];
					else
					$fsg = "NA";
					$specd[$dt->cm_id]['data'][] = array(
					'id' => $dt->id,
					'head' => $fg,
					'subhead' => $fsg,
					'value' => $dt->value
					);
				}
				else
				{
					//print_r("<br>Adding Feature : ".$dt->id);
					if(!isset($featd[$dt->cm_id]))
					$featd[$dt->cm_id] = array('id' => $dt->cm_id, 'data' => array());
					if(isset($vmd[$dt->head_id]))
					$fg = $vmd[$dt->head_id];
					else
					$fg = "NA";
					if(isset($vmd[$dt->subhead_id]))
					$fsg = $vmd[$dt->subhead_id];
					else
					$fsg = "NA";
					$featd[$dt->cm_id]['data'][] = array(
					'id' => $dt->id,
					'feature_group' => $fg,
					'feature' => $fsg,
					'value' => $dt->value
					);
				}
			}
			//dd($specd);
			//dd($featd);
			$updated = null;
			
			$vehicles = array();
			$updated = self::vehicleSyncTime();
			foreach($vhs as $vh)
			{
				
				//$vdata = self::getVline($vh->id);
				//$bm = Vehicle::find($vdata['base_model']['id']);
				$vehicle = array();
				$vehicle['id'] = $vh->id;
				$vehicle['model_group_id'] = $vh->mg_id;
				$vehicle['model_group'] = $vh->mg;
				$vehicle['custom_model_id'] = $vh->cm_id;
				$vehicle['custom_model'] = $vh->cm1;
				$vehicle['name'] = $vh->local_name;
				$vehicle['code'] = $vh->code;
				if(isset($vcolors[$vh->id]))
				$vehicle['colors'] = $vcolors[$vh->id];
				else
				$vehicle['colors'] = Null;
				if(!empty($vh->csd_code))
				$vehicle['csd_index'] = $vh->csd_code;
				else
				$vehicle['csd_index'] = $vh->code;
				$vehicle['image'] = null;
				$vehicle['segment'] = $segs['bykey'][$vh->segment];
				$vehicle['base_permit'] = $permits['bykey'][$vh->permit_id];
				if(!empty($vh->permit2_id))
				$vehicle['alternate_permit'] = $permits['bykey'][$vh->permit2_id];
				else
				$vehicle['alternate_permit'] = "";
				$vehicle['bodymake'] = $bms['bykey'][$vh->bodymake_id];
				$vehicle['bodytype'] = $bts['bykey'][$vh->bodytype_id];
				$vehicle['fuel'] = $fuels['bykey'][$vh->fuel_type_id];
				$vehicle['transmission'] = $trans['bykey'][$vh->transmission_type];
				$vehicle['seating'] = $vh->seating;
				$vehicle['engine_cc'] = $vh->cc_capacity;
				$vehicle['height'] = $vh->height;
				$vehicle['width'] =  $vh->width;
				$vehicle['weight'] = $vh->weight;
				$vehicle['wheels'] = $vh->wheels;
				/* $vehicle['colors'] = ColorHelper::getVehicleColors($vh->id); */
				
				$vehicle['rsa'] = ExtrasHelper::getRSA($vh->cm1);
				$tmp1 = ExtrasHelper::get_apack($vh->apack_id);
				/* 	$tmp2 = array();
					foreach($tmp1["essential"] as $val)
					$tmp2[]=$val;
					$tmp1["essential"] = $tmp2;
					$tmp2 = array();
					foreach($tmp1["extra"] as $val)
					$tmp2[]=$val;
				$tmp1["extra"] = $tmp2; */
				$vehicle['accessories']= $tmp1;
				$vehicle['shield']= ExtrasHelper::getShield($vh->cm_id);
				$vehicle['corporate_bonus'] = ExtrasHelper::getCorporate($vh->cm_id);
				$vehicle['exchange_bonus'] = Null;
				$vehicle['loyalty_bonus']= Null;
				$xchd = ExtrasHelper::getXchange($vh->id);
				foreach($xchd as $tmp)
				{
					//print_r("<br><br>Processing : ");print_r($tmp);
					if($tmp['type'] == "Exchange")//'Loyalty':'Exchange'
					{
						if($vehicle['exchange_bonus']== Null)
						$vehicle['exchange_bonus']= array();
						$vehicle['exchange_bonus'][] = array(
						'id' => $tmp['id'],
						'scheme' => $tmp['scheme'],
						'old_vehicle' => $tmp['old_vehicle'],
						'amount' => $tmp['amount']
						);
						
					}
					else
					{
						if($vehicle['loyalty_bonus']== Null)
						$vehicle['loyalty_bonus']= array();
						$vehicle['loyalty_bonus'][] = array(
						'id' => $tmp['id'],
						'scheme' => $tmp['scheme'],
						'old_vehicle' => $tmp['old_vehicle'],
						'amount' => $tmp['amount']
						);
					}
					
				}
				
				$vehicle['specifications']= Null;
				if(isset($specd[$vh->cm_id]))
				{
					if($vehicle['specifications']== Null)
					$vehicle['specifications']= array();
					$vehicle['specifications']=$specd[$vh->cm_id];
				}
				$vehicle['features']= Null;
				
				if(isset($featd[$vh->id]))
				{	
					if($vehicle['features']== Null)
					$vehicle['features']= array();
					$vehicle['features']=$featd[$vh->id];
				}
				$burl = url('');
				$vehicle['media'] = array(
				'img_front' => $burl."/data/c1.jpg",
				'img_back' => $burl."/data/c2.jpg",
				'img_left' => $burl."/data/c3.jpg",
				'img_right' => $burl."/data/c4.jpg",
				'brochure' => $burl."/data/brochure.pdf",
				'video' => "https://www.youtube.com/watch?v=JosmGAwxdHI",
				);
				$vehicles[]= $vehicle;
			}
			return array($vehicles,$updated);
		}
		
		
		public static function getSpecificationHeads()
		{
			$info = CommonHelper::enumGetValues("VH-SPEC-TYPE");////print_r($info);
			$tmp = array();
			$theads=array();
			foreach($info as $itm)
			{
				if($itm['parent_id'] == 0)
				{
					if(!isset($theads[$itm['id']]))
					$theads[$itm['id']]=$itm['value'];
				}
				else
				{
					$tmp[]=array("head_id" => $itm['parent_id'],"head" => $theads[$itm['parent_id']], "subhead_id" => $itm['id'], "subhead" => $itm['value']);
				}
			}
			array_multisort(
			array_column($tmp, 'head'), SORT_ASC,
			array_column($tmp, 'subhead'), SORT_ASC,
			$tmp);
			$data = $tmp;
			
			return $data;
		}
		
		
		public static function compare($kw,$vids)
		{
			$list = VehicleMeta::whereIn('vehicle_id',$vids)->where('keyword_id',$kw)->get();
			$data=array();
			$sdata = self::getFnSHeads($kw,$vids);
			$heads = $sdata["heads"];
			array_multisort(
			array_column($heads, 'head'), SORT_ASC,
			array_column($heads, 'subhead'), SORT_ASC,
			$heads);
			$vdata = $sdata["data"];
			//$headings = self::getFnSHeadings($type,$seg,$vid);
			foreach($heads as $head)
			{
				$tmp=array("head"=>$head['head'],"subhead"=>$head['subhead']);
				foreach($vids as $vh)
				{
					$flag = false;
					foreach($vdata as $itm)
					{
						if($itm["vehicle_id"] == $vh && $itm["subhead_id"] == $head["subhead_id"])
						{
							$flag = true;
							break;
						}
					}
					if($flag)
					$tmp[$vh] = $itm["value"];
					else
					$tmp[$vh] = "-NA-";
				}
				$data[]=$tmp;
			}
			return $data;
		}
		
		public static function getCustomModel($all = false, $segs = false)
		{
			//print_r("<br>In VH::getCustomModel with all: $all, segs : $segs");
			$vlist = Vehicle::select('id','name','local_name','cm1','cm_id','status','segment')->where('head_type',4);
			if($segs != false)
			{
				if(strpos($segs,",") >= 0)
				{
					$sar = explode(",",$segs);
					$vlist = $vlist->whereIn('segment',$sar);
				}
				else
				$vlist = $vlist->where('segment',$segs);
			}
			if(!$all)
			$vlist = $vlist->where('status',1);
			$vlist = $vlist->orderby('segment')->orderby('cm1')->get();
			//print_r("<br> Here is vlist : <br>");
			//print_r($vlist->toarray());
			$cms = array('bykey' =>array(),'byvalue' => array(),'byseg' => array());
			foreach($vlist as $item)
			{
				if(!isset($cms['wseg'][$item['segment']]))
				$cms['wseg'][$item['segment']]=array();
				if(!isset($cms[$item->cm_id]))
				{
					$cms['bykey'][$item->cm_id] = $item->cm1;
					$cms['byvalue'][$item->cm1] = $item->cm_id;
					$cms['byseg'][$item['segment']][$item->cm_id]= $item->cm_id;
				}
			}
			//dd($cms);
			//asort($cms['bykey']);
			//ksort($cms['byvalue']);
			return $cms;
		}
		
		public static function getModelGroup($all = false, $segment = false)
		{
			//print_r("<br>Getting model grp for $all, $segment <br>");
			if($segment==false)
			{
				if($all)
				$blist = Vehicle::select('id','name','local_name','status')->where('head_type',2)->get();
				else
				$blist = Vehicle::select('id','name','local_name','status')->where('head_type',2)->where('status',1)->get();
			}
			else
			{
				if($all)
				$blist = Vehicle::select('id','name','local_name','status')->where('segment',$segment)->where('head_type',2)->get();
				else
				$blist = Vehicle::select('id','name','local_name','status')->where('segment',$segment)->where('head_type',2)->where('status',1)->get();
			}
			//print_r($blist->toarray());
			$bms = array();
			foreach($blist as $bitem)
			{
				//print_r("<BR> Processing : ");print_r($bitem->toarray());
				$bms[$bitem->id] = $bitem->name;
			}
			asort($bms);
			return $bms;
		}
		
		
		public static function updateDisable($vh)
		{
			$mdl = Vehicle::find($vh->parent);
			$bm = Vehicle::find($mdl->parent);
			$sbs = Vehicle::where('parent',$vh->parent)->where('status',1)->first();
			if(!$sbs)
			{
				$mdl->status = 0;
				$mdl->save();
			}
			$sbs = Vehicle::where('parent',$mdl->parent)->where('status',1)->first();
			if(!$sbs)
			{
				$bm->status = 0;
				$bm->save();
				ExtrasHelper::deactivateMG($bm->id,$bm->name);
			}
			$sbs = Vehicle::where('head_type',4)->where('cm1',$vh->cm1)->where('status',1)->first();
			if(!$sbs)
			$cm = true;
			else
			$cm = false;
			
			if($cm)
			ExtrasHelper::deactivateCM($vh->cm1);
			
			ExtrasHelper::deactivateFnS($vh->id);
			return;
		}
		
		public static function getFeatureHeads($seg,$vid,$vids)
		{
			/* $data = array();
				////print_r("<br><br>  Vids : <br>");////print_r($vids);
				if($vid==0)
				{
				foreach($vids as $vhid)
				{
				$hlist = VehicleMeta::where('keyword_id',26)->where('vehicle_id',$vhid)->get();
				////print_r("<br><br>  hlist : <br>");////print_r($hlist->toArray());
				foreach($hlist as $itm)
				{
				if(!isset($data[$itm->subhead_id]))
				$data[$itm->subhead_id] = array("head_id" => $itm->head_id,"head" => CommonHelper::enumValueById($itm->head_id), "subhead_id" => $itm->subhead_id, "subhead" => CommonHelper::enumValueById($itm->subhead_id));
				}
				}
				}
			////print_r("<br><br>  data : <br>");////print_r($data); */
			$info = CommonHelper::enumGetValues("VH-FEATURES-TYPE");////print_r($info);
			$tmp = array();
			$theads=array();
			foreach($info as $itm)
			{
				if($itm['parent_id'] == 0)
				{
					if(!isset($theads[$itm['id']]))
					$theads[$itm['id']]=$itm['value'];
				}
				else
				{
					$tmp[]=array("head_id" => $itm['parent_id'],"head" => $theads[$itm['parent_id']], "subhead_id" => $itm['id'], "subhead" => $itm['value']);
				}
			}
			array_multisort(
			array_column($tmp, 'head'), SORT_ASC,
			array_column($tmp, 'subhead'), SORT_ASC,
			$tmp);
			$data = $tmp;
			
			return $data;
		}
		
		public static function getAllVehilces()
		{
			$vhs = Vehicle::where('head_type',4)->get();
			$vehicles = array();
			foreach($vhs as $vh)
			{
				$vdata = self::getVline($vh->id);
				$vehicle = array();
				$vehicle['id'] = $vh->id;
				$vehicle['basemodel_id'] = $vdata['base_model']['id'];
				$vehicle['basemodel'] = $vdata['base_model']['name'];
				$vehicle['model_id'] = $vdata['model']['id'];
				$vehicle['model'] = $vdata['model']['name'];
				$vehicle['custom_model_id'] = $vh->cm_id;
				$vehicle['custom_model'] = $vh->cm1;
				$vehicle['name'] = $vh->local_name;
				$vehicle['code'] = $vh->code;
				if(!empty($vh->csd_code))
				$vehicle['csd_index'] = $vh->csd_code;
				else
				$vehicle['csd_index'] = $vh->code;
				//$vehicle['csd_index'] = $vh->csd_code;
				$vehicle['image'] = null;
				$vehicle['segment'] = CommonHelper::enumValueById($vh->segment);
				$vehicle['subsegment'] = CommonHelper::enumValueById($vh->subsegment);
				$vehicle['base_permit'] = CommonHelper::enumValueById($vh->permit_id);
				$vehicle['alternate_permit'] = CommonHelper::enumValueById($vh->permit2_id);
				$vehicle['bodymake'] = CommonHelper::enumValueById($vh->bodymake_id);
				$vehicle['bodytype'] = CommonHelper::enumValueById($vh->bodytype_id);
				$vehicle['fuel'] = CommonHelper::enumValueById($vh->fuel_type_id);
				$vehicle['transmission'] = CommonHelper::enumValueById($vh->transmission_type);
				$vehicle['seating'] = $vh->seating;
				$vehicle['engine_cc'] = $vh->cc_capacity;
				$vehicle['height'] = $vh->height;
				$vehicle['width'] =  $vh->width;
				$vehicle['weight'] = $vh->weight;
				$vehicle['wheels'] = $vh->wheels;
				$vehicle['colors'] = ColorHelper::getVehicleColors($vh->id);
				$vehicle['rsa'] = ExtrasHelper::getRSA($vh->id);
				$vehicle['accessories']=ExtrasHelper::get_apack($vh->id);
				$vehicle['shield']=ExtrasHelper::getShield($vh->id);
				$vehicle['corporate_bonus']=ExtrasHelper::getCorporate($vh->id);
				$vehicle['exchange_loyalty']=ExtrasHelper::getXchange($vh->id);
				$vehicles[]= $vehicle;
			}
			return $vehicles;
		}
		
		
		//"TREO",[basemodel] => TREO [model] => ZOR GRAND [variant] => PU [code] => 1EV2DD1TUFLB1WD
		public static function getVehicleId($type,$mg,$bm,$variant)
		{
			//print_r("Received ( type = $type, MG = $basemodel, BM = $model, Var = $variant, Color = $color, Code = $code");
			/* if($type=="TREO")
				{
				$bmr = self::createVehicle(2,1,$basemodel);
				$mdlr = self::createVehicle(3,$bmr->id,$model);
				$varrec = self::createVehicle(4,$mdlr->id,$variant,"TREO",$code);//4,###,"PU","1EV2DD1TUFLB1WD"
				}
				else
			{ */
			$mgr = self::createVehicle(2,1,$mg);
			//$bmr = self::createVehicle(3,$mgr->id,$bm);
			
			$varrec = self::createVehicle(4,$mgr->id,$bm,$variant,$mgr->id,$mgr->local_name);
			/* } */
			return $varrec;
		}
		
		////////////////////////////////////////////////////////////////////////////////////////
		//
		// Create a new Vehicle Entry with supplied values
		//
		////////////////////////////////////////////////////////////////////////////////////////
		
		public static function createVehicle($lvl,$parent,$name,$code = NULL, $mgid = Null, $mg = Null)
		{
			
			////print_r("<h3>Vehicle Not Found Child of $parent:: Level - $lvl, Name : $name</h3>");
			/* if($type == "TREO")
				{
				//[lvl] = 4,[parent] = ###,[name] = "PU",[code] = "1EV2DD1TUFLB1WD"
				$data = Vehicle::where('parent',$parent)->where('code',$code)->first();
				
				if($data)
				{
				return $data;
				////print_r("<h3>Vehicle Found Child of $parent:: Level - $lvl, Name : $name</h3>");
				}
				else
				{
				$data = new Vehicle;
				$data->head_type = $lvl;
				$data->parent = $parent;
				$data->local_name = $data->name = $name;
				$data->code = $code;
				if($lvl == 4)
				$data->status = 2;
				else
				$data->status = 1;
				$data->save();
				}
				}
				else
			{ */
			$data = Vehicle::where('head_type',$lvl)->where('parent',$parent)->where('oem_name',$name)->first();
			
			if($data)
			{
				return $data;
				////print_r("<h3>Vehicle Found Child of $parent:: Level - $lvl, Name : $name</h3>");
			}
			else
			{
				$data = new Vehicle;
				$data->head_type = $lvl;
				$data->parent = $parent;
				$data->oem_name = $data->local_name =  $data->name = $name;
				$data->code = $code;
				if($lvl == 2)
				{
					$data->mg = $name;
					$data->mg_id = CommonHelper::getCMG($name);
				}
				else
				{
					$bm = Vehicle::find($parent);
					$data->mg = $bm->mg;
					$data->mg_id = $bm->mg_id;
				}
				if($lvl == 4)
				{
					$data->status = 2;
				}
				else
				$data->status = 1;
				$data->save();
			}
			/* } */
			
			return $data;
		}
		
		public static function updateCMG($mgid,$cmg)
		{
			$cmgid = CommonHelper::getCMG($cmg);
			$mg = Vehicle::find($mgid);
			$mg->mg = $mg->local_name = $cmg;
			$mg->mg_id = $cmgid;
			$mg->save();
			$vhs = Vehicle::where('parent',$mgid)->where('head_type',4)->get();
			foreach($vhs as $vh)
			{
				$vh->mg = $cmg;
				$vh->mg_id = $cmgid;
				$vh->save();	
			}
			return;	
		}
		
		public static function getApprover($branchid, $vid)
		{
			$data = array();
			$apdata = Approver::select('role_level','role_id','od_limit')->where('branch_id',$branchid)->where('vehicle_id',$vid)->orderBy('role_level')->get();
			foreach($apdata as $arec)
			{
				$cnt = $arec->role_level;
				$data['l'.$cnt] = Roles::find($arec->role_id)->name;
				$data['l'.$cnt.'_limit'] = $arec->od_limit;
			}
			return $data;
		}
		
		public static function getApproverData($vid)
		{
			$data=array( 
			"l1" => "-NA-", "l1_limit" => "-NA-",
			"l2" => "-NA-", "l2_limit" => "-NA-",
			"l3" => "-NA-", "l3_limit" => "-NA-",
			"l4" => "-NA-", "l4_limit" => "-NA-",
			);
			return $data;
		}
		
		
		
		public static function updateApprover($branchid, $vid, $l1a, $l2a, $l3a, $l4a, $l1l, $l2l, $l3l, $l4l)
		{
			$approvers = Approver::where('branch_id',$branchid)->where('vehicle_id',$vid)->OrderBy('role_level')->get();
			$roles = Roles::get();
			$appcnt = 0;
			
			foreach($approvers as $apprec)
			{
				$role_id = 0;
				$tmp = 'l'.$apprec->role_level.'a';
				$rname = $$tmp;
				$tmp = 'l'.$apprec->role_level.'l';
				$odlimit = $$tmp;
				foreach($roles as $roled)
				{
					if($roled->name == $rname)
					$role_id = $roled->id;
				}
				if($role_id > 0)
				{
					$appcnt++;
					$apprec->role_id = $role_id;
					$apprec->od_limit = $odlimit;
					$apprec->status = 1;
				}
				else
				{
					$apprec->status = 0;
				}
				$apprec->save();
			}
			return $appcnt;
		}
		
		
		
		////////////////////////////////////////////////////////////////////////////////////////
		//
		// function Name : 
		// purpose :
		// params :
		// return value : 0 for DISABED, 1 for Active, 2 for Inactive, 3 for Missing from latest Pricelist Import
		// remarks : 
		// used in :
		//
		////////////////////////////////////////////////////////////////////////////////////////
		
		public static function updateStatus($vid,$status)
		{
			if(self::Vehicle_Completion($vid) == 0)
			return $status;
			else
			return 3;
		}
		
		
		public static function getActiveCm($bid = null)
		{
			$data = array();
			if(empty($bid))
			{
				$cdata = Vehicle::select('cm_id','cm1')->where('head_type',4)->where('status',1)->get();
				foreach($cdata as $rec)
				{
					if(!isset($data[$rec->cm_id]))
					{
						$data[$rec->cm_id]=array("id"=>$rec->cm_id,"name"=>$rec->cm1);
					}
				}
			}
			else
			{
				$data = array();
				$bids = explode(",",$bid);
				foreach($bids as $brid)
				{
					$cdata = Vehicle::select('cm_id','cm1')->where('head_type',4)->where('segment',$brid)->where('status',1)->get();
					foreach($cdata as $rec)
					{
						if(!isset($data[$rec->cm_id]))
						{
							$data[$rec->cm_id]=array("id"=>$rec->cm_id,"name"=>$rec->cm1);
						}
					}
				}
			}
			return $data;
		}
		
		
		public static function updateAllVehicle()
		{
			//Status 1-OK, 2-InComplete, 3-Suspended, 0- disabled
			$vehicles = Vehicle::where('head_type',4)->get();
			$i=1;
			////print_r($vehicles->toarray());
			foreach($vehicles as $vehicle)
			{
				//print_r("<br>Cheking # $i : Vid - ".$vehicle->id.", current status : ".$vehicle->status);
				$i++;
				if($vehicle->status > 0)
				{
					$stt = self::Vehicle_Completion($vehicle->toArray());
					//print_r("<br>Received STT : $stt <br>");
					if($stt>0)
					{
						$vehicle->status = 2;
					}
					else
					$vehicle->status = 1;	
					$vehicle->save();
					//print_r("<br><b>NEW STATUS : ".$vehicle->status."</b><br><br>");
				}
				
			}
			return;
		}
		////////////////////////////////////////////////////////////////////////////////////////
		//
		// function Name : Vehicle_Completion
		// purpose :
		// params :
		// return value :
		// remarks :
		// used in :
		//
		////////////////////////////////////////////////////////////////////////////////////////
		public static function get_segments()
		{
			$recs = Vehicle::select('id','segment','cm1','cm_id','name','local_name','mg','mg_id')->where('head_type',4)->where('status',1)->orderby('segment')->orderby('id')->get()->toArray();
			$segments = array();
			$segs = CommonHelper::enumGetKeyValues("SEGMENT");
			$burl = url('');
			foreach($recs as $rec)
			{
				if(!isset($segments[$rec['segment']]))
				{
					$segments[$rec['segment']] = array(
					'id' => $rec['segment'],
					'name' => CommonHelper::enumValueById($rec['segment']),
					'url' => $burl."/icons/".strtolower(CommonHelper::enumValueById($rec['segment'])).".png",
					'model_group' => array()
					);
				}
				if(!isset($segments[$rec['segment']]['model_group'][$rec['mg_id']]))
				{
					$segments[$rec['segment']]['model_group'][$rec['mg_id']] = array(
					'id' => $rec['mg_id'],
					'name' => $rec['mg'],
					'url' => null,
					'custom_model' => array()
					);
				}
				if(!isset($segments[$rec['segment']]['model_group'][$rec['mg_id']]['custom_model'][$rec['cm_id']]))
				{
					$segments[$rec['segment']]['model_group'][$rec['mg_id']]['custom_model'][$rec['cm_id']] = array(
					'id' => $rec['cm_id'],
					'name' => $rec['cm1'],
					'url' => null
					);
				}
			}
			$segments[] = array(
			'id' => 0,
			'name' => 'CSD',
			'url' => $burl."/icons/csd.png",
			'model_group' => Null
			);
			$segments[] = array(
			'id' => 1,
			'name' => 'TAXI',
			'url' => $burl."/icons/taxi.png",
			'model_group' => $segments[753]['model_group'],
			);
			$data = array();
			foreach($segments as $key => $val)
			{
				$tmp = array(
				"id" => $val['id'],
				"name" => $val['name'],
				"url" => $val['url'],
				"model_group" => array()
				);
				if($val['model_group'] != Null)
				{
					foreach($val['model_group'] as $mgk => $mgv)
					{
						$mtmp = array(
						"id" => $mgv['id'],
						"name" => $mgv['name'],
						"url" => $mgv['url'],
						"custom_model" => array()
						);
						if(isset($mgv['custom_model']))
						{
							foreach($mgv['custom_model'] as $cmk => $cmv)
							{
								$ctmp = array(
								"id" => $cmv['id'],
								"name" => $cmv['name'],
								"url" => $cmv['url']
								);
								$mtmp["custom_model"][] = $ctmp;
							}
						}
						$tmp['model_group'][] = $mtmp;
					}
				}
				
				$data[] = $tmp;
			}
			$data[] = $segs;
			return $data;
		}
		
		
		public static function Vehicle_Completion($vrec)
		{
			$missing =0;
			$vinfo = array("cm1", "local_name", "transmission_type","fuel_type_id", "seating", "wheels", "bodymake_id", "bodytype_id",  "segment", "ins_zone_1", "permit_id", "inscomp_id", "carrier_type_id","ins_zone_2", "permit2_id", "inscomp2_id", "carrier_type2_id","cc_capacity","weight");
			$vid = $vrec['id'];
			//print_r($vrec);
			$fuel = CommonHelper::enumValueById($vrec["fuel_type_id"]);
			$permit = CommonHelper::enumValueById($vrec["permit_id"]);
			foreach($vinfo as $vi)
			{
				//print_r("<br>Checking $vi : ".$vrec[$vi]."...");
				if($fuel == "ELECTRIC" && ( $vi =="cc_capacity" || $vi =="weight"))
				{
					//print_r("<br>...Skipping CC for Electric...");
					continue;
				}
				elseif($permit != "GOODS" && $vi =="weight")
				{
					//print_r("<br>...Skipping GWV for NonGoods...");
					continue;
				}
				elseif(($permit == "MISC" || $permit == "GOODS" ||  empty($vrec->permit2_id)) && ($vi =="ins_zone_2" || $vi =="permit2_id" || $vi =="inscomp2_id" || $vi =="carrier_type2_id"))
				{
					//print_r("<br>...Skipping Second Set for $permit...");
					continue;
				}
				
				if(empty($vrec[$vi]))
				{
					$missing++;
					//print_r("<br>$vi is Missing, Value : ".$vrec[$vi]);
				}
			}
			
			//print_r(", Missing : $missing<br><br>");
			////print_r($vrec);
			//dd();
			return $missing;
		}
		
		
		public static function getchilds($id,$type)
		{
			if($type == 4)
			return $id;
			else
			{
				$ctyp = $type+1;
				$child = Vehicle::where('head_type',$ctype)->where('parent',$id)->get();
				$rtval="";
				foreach($child as $mm)
				{
					if($rtval=="")
					{
						$rtval = self::getchilds($mm->id,$ctype);
					}
					else
					{
						$rtval = ",".self::getchilds($mm->id,$ctype);
					}
				}
				return $rtval;
				
			}
		}
		
		/// downline($id,$level) :: get model_baseModel downline by ID and Level
		public static function downline($vid,$level)
		{
			$downline = array();
			$vh = Vehicle::select('id','name','code','local_name','segment','subsegment','permit_id','weight','body','bodytype','engine_type','wheels','cc_capacity','color','status','head_type','parent')->where('parent',$vid)->get()->toArray();
			if($vh)
			{
				foreach($vh as $dli)
				{
					$flag = true;
					$temp = $dli;
					if($level < 4)
					{
						if($dli->head_type == 2)
						{
							$tmp = DB::table('bmpl_custom_models')->select('id','base_model_id')->where('base_model_id',$dli['id'])->get()->toArray();
							////print_r($tmp);
							if($tmp->count() >= 1)
							$flag = false;
						}
						////print_r("<br>$flag<br>");
						if($flag)
						$temp['downline'] = self::downline($dli['id'],$level+1);
					}
					if($flag)
					$downline[]=$temp;
				}
			}
			return $downline;
		}
		
	}																																																																																														