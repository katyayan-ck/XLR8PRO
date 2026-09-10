<?php
//////////////////////////////
// How to Use
// php complete_vehicle_info.php storage/app/pricing-exports/{fileName}.xlsx
//////////////////////////////	
	
/**
 * complete_vehicle_info.php
 *
 * Fills a Vehicle Info export so rows pass VehicleService::missingFields()
 * (FRS v3.1 completeness gate). For staging / E2E only — not production master data.
 *
 * Usage:
 *   php complete_vehicle_info.php /path/to/Vehicle_Info.xlsx
 * Writes:
 *   /path/to/Vehicle_Info_COMPLETED.xlsx
 *
 * Always required:
 *   Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain,
 *   Body Make, Body Type, GST%, Permit, Taxi Price, Custom Model,
 *   Custom Variant, Display Name, Colour Name
 *
 * Conditional (insurance-aligned):
 *   Private+ICE or Passenger 4W+ICE → CC
 *   Private+EV  or Passenger 4W+EV  → Motor
 *   Goods → GVW
 *   Passenger 3W → none of CC / Motor / GVW
 *
 * Complete → Master Complete=Y, Is Incomplete=N, Inactive=N, Status=ACTIVE
 * Incomplete → Master Complete=N, Is Incomplete=Y, Inactive=Y, Status=INACTIVE
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$in = $argv[1] ?? null;
if (! $in || ! is_file($in)) {
    fwrite(STDERR, "Usage: php complete_vehicle_info.php /path/to/Vehicle_Info.xlsx\n");
    exit(1);
}

$out = preg_replace('/\.xlsx$/i', '_COMPLETED.xlsx', $in);

$reader = IOFactory::createReaderForFile($in);
$reader->setReadDataOnly(false);
$wb = $reader->load($in);
$ws = $wb->getSheetByName('Vehicle Info') ?? $wb->getSheet(0);

$headers = [];
$colCount = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
for ($c = 1; $c <= $colCount; $c++) {
    $label = trim((string) $ws->getCell([$c, 1])->getValue());
    if ($label !== '') {
        $headers[mb_strtolower($label)] = $c;
    }
}

$col = function (string ...$aliases) use ($headers): ?int {
    foreach ($aliases as $a) {
        $k = mb_strtolower(trim($a));
        if (isset($headers[$k])) {
            return $headers[$k];
        }
    }

    return null;
};

$C = [
    'model'    => $col('Model Code', 'OEM Code'),
    'oem_m'    => $col('OEM Model'),
    'oem_v'    => $col('OEM Variant'),
    'seg'      => $col('Segment'),
    'sub'      => $col('Sub Segment', 'Sub-Segment', 'SubSegment'),
    'fuel'     => $col('Fuel', 'Fuel Type'),
    'seat'     => $col('Seating', 'Seating Capacity', 'Seats'),
    'wheels'   => $col('Wheels'),
    'trans'    => $col('Transmission'),
    'drive'    => $col('Drivetrain', 'Drive Train'),
    'bmake'    => $col('Body Make'),
    'btype'    => $col('Body Type'),
    'cc'       => $col('CC', 'CC or Power', 'CC/Power'),
    'motor'    => $col('Motor'),
    'gvw'      => $col('GVW'),
    'gst'      => $col('GST%', 'GST', 'GST Percent'),
    'permit'   => $col('Permit'),
    'taxi'     => $col('Taxi Price'),
    'cmodel'   => $col('Custom Model'),
    'cvar'     => $col('Custom Variant'),
    'disp'     => $col('Display Name'),
    'colour'   => $col('Colour Name', 'Color Name', 'Colour', 'Color'),
    'status'   => $col('Status'),
    'inc'      => $col('Is Incomplete'),
    'master'   => $col('Master Complete (Y/N)', 'Master Complete'),
    'inact'    => $col('Inactive (Y/N)', 'Inactive'),
    'shield'   => $col('Shield Pack'),
];

if (! $C['model'] || ! $C['seg']) {
    fwrite(STDERR, "Missing Model Code or Segment column. Headers found: " . implode(', ', array_keys($headers)) . "\n");
    exit(1);
}

$val = function (array $C, $ws, string $key, int $r): string {
    $c = $C[$key] ?? null;
    if (! $c) {
        return '';
    }

    return trim((string) $ws->getCell([$c, $r])->getValue());
};

$set = function (array $C, $ws, string $key, int $r, $value): void {
    $c = $C[$key] ?? null;
    if ($c) {
        $ws->setCellValue([$c, $r], $value);
    }
};

$isBlank = fn ($v) => $v === null || trim((string) $v) === '';

$guessFuel = function (string $segment, string $oemVariant, string $oemModel): string {
    $ov = strtoupper($oemVariant . ' ' . $oemModel);
    $seg = strtoupper($segment);
    if ($seg === 'BEV' || str_contains($ov, 'ELECTRIC') || preg_match('/\bEV\b|\bBEV\b/', $ov)) {
        return 'ELECTRIC';
    }
    if (str_contains($ov, 'CNG')) {
        return 'CNG';
    }
    if (preg_match('/\bDSL\b|\bDIESEL\b|\bD\b/', $ov) || str_contains($ov, 'DSL')) {
        return 'DIESEL';
    }
    if (preg_match('/\bPET\b|\bPETROL\b/', $ov)) {
        return 'PETROL';
    }

    return match ($seg) {
        'BEV' => 'ELECTRIC',
        'CV', 'LMM' => 'DIESEL',
        default => 'PETROL',
    };
};

$guessPermit = function (string $segment, string $oemVariant, int $wheels): string {
    $seg = strtoupper($segment);
    $ov = strtoupper($oemVariant);
    if (str_contains($ov, 'TAXI') || $seg === 'TAXI') {
        return 'PRIVATE';
    }
    if ($wheels === 3 && (str_contains($ov, 'PASS') || $seg === 'PV')) {
        return 'PASSENGER';
    }
    if (in_array($seg, ['CV', 'LMM'], true)) {
        return 'GOODS';
    }

    return 'PRIVATE';
};

$guessDrivetrain = function (string $oemVariant, string $segment, string $existing): string {
    if ($existing !== '') {
        return strtoupper($existing);
    }
    $ov = strtoupper($oemVariant);
    if (str_contains($ov, 'AWD') || str_contains($ov, '4WD') || str_contains($ov, '4X4')) {
        return 'AWD';
    }
    if (str_contains($ov, 'FWD') || str_contains($ov, '2WD')) {
        return 'FWD';
    }

    return strtoupper($segment) === 'BEV' ? 'RWD' : 'RWD';
};

$guessTransmission = function (string $oemVariant, string $existing): string {
    if ($existing !== '') {
        return strtoupper($existing);
    }
    $ov = strtoupper($oemVariant);
    if (str_contains($ov, 'AMT') || str_contains($ov, 'AT') || str_contains($ov, 'AUTO')) {
        return 'AT';
    }

    return 'MT';
};

$guessWheels = function (string $segment, string $oemVariant, string $existing): int {
    if ($existing !== '' && (int) $existing > 0) {
        return (int) $existing;
    }
    $ov = strtoupper($oemVariant);
    if (str_contains($ov, '3W') || preg_match('/\b3\s*WH/', $ov)) {
        return 3;
    }
    if (in_array(strtoupper($segment), ['CV', 'LMM'], true) && (str_contains($ov, '6W') || str_contains($ov, '6TYRE'))) {
        return 6;
    }

    return 4;
};

$guessSeating = function (string $segment, int $wheels, string $permit, string $existing): int {
    if ($existing !== '' && (int) $existing > 0) {
        return (int) $existing;
    }
    if ($wheels === 3) {
        return $permit === 'PASSENGER' ? 4 : 1;
    }
    if (in_array(strtoupper($segment), ['CV', 'LMM'], true)) {
        return 2;
    }

    return 5;
};

$guessBodyType = function (string $segment, string $oemModel, string $existing): string {
    if ($existing !== '') {
        return strtoupper($existing);
    }
    $om = strtoupper($oemModel);
    $seg = strtoupper($segment);
    if (str_contains($om, 'PICK') || $seg === 'CV') {
        return 'PICKUP';
    }
    if ($seg === 'LMM') {
        return 'LCV';
    }
    if ($seg === 'BEV') {
        return 'SUV';
    }

    return 'SUV';
};

$isElectric = function (string $fuel): bool {
    $f = strtoupper($fuel);

    return str_contains($f, 'ELECTRIC') || $f === 'EV' || str_contains($f, 'BEV');
};

$highestRow = (int) $ws->getHighestDataRow();
$completed = 0;
$leftInactive = 0;
$missingCounts = [];

for ($r = 2; $r <= $highestRow; $r++) {
    $modelCode = $val($C, $ws, 'model', $r);
    if ($modelCode === '') {
        continue;
    }

    $segment = $val($C, $ws, 'seg', $r);
    $sub     = $val($C, $ws, 'sub', $r);
    $oemM    = $val($C, $ws, 'oem_m', $r);
    $oemV    = $val($C, $ws, 'oem_v', $r);
    $fuel    = $val($C, $ws, 'fuel', $r);
    $seat    = $val($C, $ws, 'seat', $r);
    $wheelsV = $val($C, $ws, 'wheels', $r);
    $trans   = $val($C, $ws, 'trans', $r);
    $drive   = $val($C, $ws, 'drive', $r);
    $bmake   = $val($C, $ws, 'bmake', $r);
    $btype   = $val($C, $ws, 'btype', $r);
    $cc      = $val($C, $ws, 'cc', $r);
    $motor   = $val($C, $ws, 'motor', $r);
    $gvw     = $val($C, $ws, 'gvw', $r);
    $gst     = $val($C, $ws, 'gst', $r);
    $permit  = $val($C, $ws, 'permit', $r);
    $taxi    = $val($C, $ws, 'taxi', $r);
    $cmodel  = $val($C, $ws, 'cmodel', $r);
    $cvar    = $val($C, $ws, 'cvar', $r);
    $disp    = $val($C, $ws, 'disp', $r);
    $colour  = $val($C, $ws, 'colour', $r);

    if ($sub === '' && $segment !== '') {
        $sub = $segment;
        $set($C, $ws, 'sub', $r, $sub);
    }

    if ($cmodel === '') {
        $cmodel = $oemM !== '' ? $oemM : $modelCode;
        $set($C, $ws, 'cmodel', $r, $cmodel);
    }
    if ($cvar === '') {
        $cvar = $oemV !== '' ? $oemV : $modelCode;
        $set($C, $ws, 'cvar', $r, $cvar);
    }
    if ($disp === '') {
        $disp = trim($cmodel . ' ' . $cvar) ?: $modelCode;
        $set($C, $ws, 'disp', $r, $disp);
    }
    if ($colour === '' && strlen($modelCode) >= 2) {
        $colour = strtoupper(substr($modelCode, -2));
        $set($C, $ws, 'colour', $r, $colour);
    }

    if ($fuel === '') {
        $fuel = $guessFuel($segment, $oemV, $oemM);
        $set($C, $ws, 'fuel', $r, $fuel);
    } else {
        $fuel = strtoupper($fuel);
        $set($C, $ws, 'fuel', $r, $fuel);
    }

    $wheels = $guessWheels($segment, $oemV, $wheelsV);
    $set($C, $ws, 'wheels', $r, $wheels);

    if ($permit === '' || ! in_array(strtoupper($permit), ['PRIVATE', 'GOODS', 'PASSENGER'], true)) {
        $permit = $guessPermit($segment, $oemV, $wheels);
    } else {
        $permit = strtoupper($permit);
        if ($permit === 'COMMERCIAL') {
            $permit = 'GOODS';
        }
    }
    $set($C, $ws, 'permit', $r, $permit);

    $drive = $guessDrivetrain($oemV, $segment, $drive);
    $set($C, $ws, 'drive', $r, $drive);

    $trans = $guessTransmission($oemV, $trans);
    $set($C, $ws, 'trans', $r, $trans);

    $seatN = $guessSeating($segment, $wheels, $permit, $seat);
    $set($C, $ws, 'seat', $r, $seatN);

    if ($bmake === '') {
        $bmake = 'MAHINDRA';
        $set($C, $ws, 'bmake', $r, $bmake);
    }
    $btype = $guessBodyType($segment, $oemM, $btype);
    $set($C, $ws, 'btype', $r, $btype);

    if ($gst === '') {
        $gst = $isElectric($fuel) ? '5' : '28';
        $set($C, $ws, 'gst', $r, $gst);
    }

    $taxiU = strtoupper($taxi);
    if (! in_array($taxiU, ['YES', 'NO', 'Y', 'N', '1', '0'], true)) {
        $taxiU = (strtoupper($segment) === 'TAXI' || str_contains(strtoupper($oemV), 'TAXI')) ? 'YES' : 'NO';
    } else {
        $taxiU = in_array($taxiU, ['YES', 'Y', '1'], true) ? 'YES' : 'NO';
    }
    $set($C, $ws, 'taxi', $r, $taxiU);

    $ev = $isElectric($fuel);
    $isPassenger = str_contains($permit, 'PASSENGER');
    $isPrivate   = $permit === 'PRIVATE';
    $isGoods     = $permit === 'GOODS';
    $is3w        = $wheels === 3;
    $is4w        = $wheels >= 4;

    if ($isGoods) {
        if ($isBlank($gvw)) {
            $gvw = $wheels >= 6 ? '3500' : '1500';
            $set($C, $ws, 'gvw', $r, $gvw);
        }
    } elseif ($isPrivate || ($isPassenger && $is4w)) {
        if ($ev) {
            if ($isBlank($motor)) {
                $motor = '80';
                $set($C, $ws, 'motor', $r, $motor);
            }
        } elseif ($isBlank($cc)) {
            $cc = '1497';
            $set($C, $ws, 'cc', $r, $cc);
        }
    }

    // Re-read after fills — same checklist as VehicleService::missingFields()
    $missing = [];
    $required = [
        'segment'        => $segment,
        'sub_segment'    => $sub,
        'fuel'           => $fuel,
        'seating'        => $seatN,
        'wheels'         => $wheels,
        'transmission'   => $trans,
        'drivetrain'     => $drive,
        'body_make'      => $bmake,
        'body_type'      => $btype,
        'gst_percent'    => $gst,
        'permit'         => $permit,
        'taxi_price'     => $taxiU,
        'custom_model'   => $cmodel,
        'custom_variant' => $cvar,
        'display_name'   => $disp,
        'colour_name'    => $colour,
    ];
    foreach ($required as $field => $value) {
        if ($isBlank($value) || $value === 0 || $value === '0') {
            if (in_array($field, ['seating', 'wheels'], true) && (int) $value > 0) {
                continue;
            }
            if (in_array($field, ['seating', 'wheels'], true)) {
                $missing[] = $field;
            } elseif ($isBlank($value)) {
                $missing[] = $field;
            }
        }
    }

    if ($isGoods) {
        if ($isBlank($gvw)) {
            $missing[] = 'gvw';
        }
    } elseif ($isPrivate || ($isPassenger && $is4w)) {
        if ($ev) {
            if ($isBlank($motor)) {
                $missing[] = 'motor';
            }
        } elseif ($isBlank($cc)) {
            $missing[] = 'cc';
        }
    }

    $complete = $missing === [];

    if ($complete) {
        $set($C, $ws, 'master', $r, 'Y');
        $set($C, $ws, 'inc', $r, 'N');
        $set($C, $ws, 'inact', $r, 'N');
        $set($C, $ws, 'status', $r, 'ACTIVE');
        $completed++;
    } else {
        $set($C, $ws, 'master', $r, 'N');
        $set($C, $ws, 'inc', $r, 'Y');
        $set($C, $ws, 'inact', $r, 'Y');
        $set($C, $ws, 'status', $r, 'INACTIVE');
        $leftInactive++;
        foreach ($missing as $m) {
            $missingCounts[$m] = ($missingCounts[$m] ?? 0) + 1;
        }
    }
}

$writer = IOFactory::createWriter($wb, 'Xlsx');
$writer->save($out);

echo "Written: {$out}\n";
echo "Completed (Active-ready): {$completed}\n";
echo "Left inactive: {$leftInactive}\n";
if ($missingCounts) {
    echo "Still missing after fill:\n";
    foreach ($missingCounts as $f => $n) {
        echo "  {$f}: {$n}\n";
    }
}