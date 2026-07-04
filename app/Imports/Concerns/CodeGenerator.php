<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Str;

trait CodeGenerator
{
    private int $workLocationSeq = 0;

    public function branchCode(string $name): string
    {
        $name = strtoupper(trim($name));
        return match (true) {
            str_contains($name, 'BIK')  => 'BKN',
            str_contains($name, 'CHU')  => 'CHR',
            default                     => strtoupper(substr($name, 0, 3)),
        };
    }

    public function deptCode(string $name): string
    {
        $map = [
            'ACCOUNTS'   => 'ACCT',
            'ADMIN'      => 'ADMN',
            'HR'         => 'HR',
            'INSURANCE'  => 'INSR',
            'SALES'      => 'SALE',
            'SERVICE'    => 'SRVC',
        ];
        $upper = strtoupper(trim($name));
        return $map[$upper] ?? strtoupper(substr(preg_replace('/[^A-Z]/', '', $upper), 0, 4));
    }

    public function divisionCode(string $deptCode, string $divName): string
    {
        $slug = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($divName)), 0, 4));
        return "{$deptCode}-{$slug}";
    }

    public function designationCode(string $name): string
    {
        $map = [
            'DIRECTOR'               => 'DIR',
            'GENERAL MANAGER'        => 'GM',
            'CEO'                    => 'CEO',
            'SERVICE MANAGER'        => 'SM',
            'SERVICE ADVISOR'        => 'SA',
            'TEAM LEADER'            => 'TL',
            'SENIOR TECHNICIAN'      => 'STECH',
            'TECHNICIAN'             => 'TECH',
            'APPRENTICE TECHNICIAN'  => 'ATECH',
            'BODY SHOP MANAGER'      => 'BSM',
            'SALES MANAGER'          => 'SMGR',
            'SENIOR SALES EXECUTIVE' => 'SSE',
            'SALES EXECUTIVE'        => 'SE',
            'SENIOR SALES CONSULTANT'=> 'SSC',
            'SALES CONSULTANT'       => 'SC',
            'CRM MANAGER'            => 'CRMM',
            'CRM EXECUTIVE'          => 'CRME',
            'ACCOUNTS MANAGER'       => 'AMGR',
            'ACCOUNTANT'             => 'ACNT',
            'HR MANAGER'             => 'HRM',
            'HR EXECUTIVE'           => 'HRE',
            'INSURANCE MANAGER'      => 'INMGR',
            'INSURANCE EXECUTIVE'    => 'INE',
            'PARTS MANAGER'          => 'PM',
            'PARTS EXECUTIVE'        => 'PE',
            'WASHING BOY'            => 'WB',
        ];

        $upper = strtoupper(trim($name));
        if (isset($map[$upper])) return $map[$upper];

        // Fallback: initials of each word, max 8 chars
        $words = explode(' ', $upper);
        $code  = '';
        foreach ($words as $w) {
            $code .= substr(preg_replace('/[^A-Z]/', '', $w), 0, 2);
            if (strlen($code) >= 6) break;
        }
        return substr($code, 0, 8);
    }

    public function verticalCode(string $name): string
    {
        $upper = strtoupper(trim($name));
        return match (true) {
            str_contains($upper, 'NEW')  => 'VC-NC',
            str_contains($upper, 'USED') => 'VC-UC',
            default                      => 'VC-' . strtoupper(substr($name, 0, 2)),
        };
    }

    public function segmentCode(string $name): string
    {
        $upper = strtoupper(trim($name));
        return match (true) {
            str_contains($upper, 'BEV') => 'SEG-BEV',
            str_contains($upper, 'COM') => 'SEG-COM',
            str_contains($upper, 'LMM') => 'SEG-LMM',
            str_contains($upper, 'PER') => 'SEG-PER',
            default                     => 'SEG-' . strtoupper(substr($name, 0, 3)),
        };
    }

    public function subSegmentCode(string $name): string
    {
        $upper = strtoupper(trim($name));
        return match (true) {
            str_contains($upper, 'XUV') && str_contains($upper, 'NON') => 'SS-NXUV',
            str_contains($upper, 'XUV')                                  => 'SS-XUV',
            default                                                       => 'SS-' . strtoupper(substr($name, 0, 4)),
        };
    }

     /**
     * Short segment code for DB insert — max 5 chars to fit varchar(5).
     * Used ONLY when no existing record found by name.
     */
    public function segmentCodeShort(string $name): string
    {
        $upper = strtoupper(trim($name));
        return match (true) {
            str_contains($upper, 'BEV') => 'BEV',
            str_contains($upper, 'COM') => 'COM',
            str_contains($upper, 'LMM') => 'LMM',
            str_contains($upper, 'PER') => 'PER',
            default                     => strtoupper(substr(preg_replace('/[^A-Z]/', '', $upper), 0, 5)),
        };
    }

    /**
     * Short sub-segment code for DB insert — max 5 chars to fit varchar(5).
     */
    public function subSegmentCodeShort(string $name): string
    {
        $upper = strtoupper(trim($name));
        return match (true) {
            str_contains($upper, 'NON') && str_contains($upper, 'XUV') => 'NXUV',
            str_contains($upper, 'XUV')                                  => 'XUV',
            default                                                       => strtoupper(substr(preg_replace('/[^A-Z]/', '', $upper), 0, 5)),
        };
    }

    public function nextWorkLocationCode(): string
    {
        $this->workLocationSeq++;
        return 'WL-' . str_pad($this->workLocationSeq, 3, '0', STR_PAD_LEFT);
    }

    public function postCode(
        string  $desigCode,
        string  $branchCode,
        string  $deptCode,
        ?string $divCode,
        int     $seq = 1
    ): string {
        $base = "{$desigCode}-{$branchCode}-{$deptCode}";
        return $seq > 1 ? "{$base}-{$seq}" : $base;
    }

    public function locationCode(string $shortName): string
    {
        return strtoupper(
            substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($shortName)), 0, 5)
        );
    }
}
