<?php

namespace App\Helpers;

use App\User;

use Auth;

use App\Models\Branches;





class BranchHelper

{

	public static function getBranches()

	{

		$branches = Branches::select('id', 'name')->where('parent', 0)->get();

		return $branches->toarray();
	}



	public static function getLocations($bid)

	{



		if (empty($bid)) {

			$data = Branches::select('id', 'name', 'parent')->where('parent', '<>', 0)->orderBy('name', 'asc')->get();
		} else {

			$data = array();

			$bids = explode(",", $bid);

			foreach ($bids as $brid) {

				$branch = Branches::find($brid);

				if ($branch) {

					$locations = Branches::select('id', 'name')->where('parent', $brid)->orderBy('name', 'asc')->get();

					foreach ($locations as $loc) {

						$data[] = array("id" => $loc->id, "name" => $loc->name, "parent" => $brid);
					}
				}
			}
		}

		return $data;
	}



	public static function getBranchId($branch)

	{

		$brd = Branches::select('id', 'name')->where('parent', 0)->where('name', $branch)->first();

		if (!$brd)

			$brd = Branches::create(['parent' => 0, 'name' => $branch]);

		return $brd->id;
	}



	public static function getLocationId($branch, $location)

	{

		if ($branch == "ALL") {

			$bar = "1,2";
		} elseif (strpos($branch, ",") >= 0) {

			$bar = explode(",", $branch);
		} else

			$bar = array($branch);

		if ($bar)

			$brd = Branches::select('id', 'name')->where('parent', 0)->whereIn('name', $bar)->get();

		else

			$brd = Branches::select('id', 'name')->where('parent', 0)->get();

		if ($brd) {

			$bar = array();

			$lar = array();

			$lcn = "";

			foreach ($brd as $btm) {

				$bar[] = $btm->id;
			}

			if ($location == "ALL") {

				$lcd = Branches::select('id', 'name')->whereIn('parent', $bar)->get();

				foreach ($lcd as $ltm) {

					if ($lcn == "")

						$lcn = $ltm->id;

					else

						$lcn .= "," . $ltm->id;
				}
			} else {



				if (strpos($location, ",") >= 0) {

					$lar  = explode(",", $location);
				} else {

					$lar[] = $location;
				}



				foreach ($lar as $ltm) {

					$lcd = Branches::select('id', 'name')->where('parent', '<>', 0)->where('name', $ltm)->first();

					if ($lcd) {

						if ($lcn == "")

							$lcn = $lcd->id;

						else

							$lcn .= "," . $lcd->id;
					} else {

						$lcd = Branches::create(['name' => $ltm, 'parent' => $bar[0]]);

						if ($lcn == "")

							$lcn = $lcd->id;

						else

							$lcn .= "," . $lcd->id;
					}
				}
			}
		} else

			return false;



		return $lcn;
	}
}
