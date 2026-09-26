<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Receipt {{ $receipt->type_number ?? $receipt->id }}
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>

        /* =========================================================
           PAGE
           ========================================================= */

        @page {
            size: A4 landscape;
            margin: 0;
        }

        @font-face {
            font-family: 'HindiFont';
            src: url("{{ asset('fonts/Lohit-Devanagari.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 297mm;
            min-height: 210mm;
            background: #f0f0f0;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            font-size: 9px;
        }


        /* =========================================================
           PRINT CONTROLS
           ========================================================= */

        .print-controls {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 99999;

            display: flex;
            gap: 10px;

            background: rgba(255, 255, 255, 0.95);
            padding: 10px;

            border: 1px solid #ccc;
            border-radius: 6px;

            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.15);
        }

        .print-controls button {
            border: none;
            padding: 9px 16px;

            font-size: 13px;
            font-weight: bold;

            border-radius: 4px;
            cursor: pointer;
        }

        .print-button {
            background: #198754;
            color: #fff;
        }

        .close-button {
            background: #dc3545;
            color: #fff;
        }

        .print-controls button:hover {
            opacity: 0.9;
        }


        /* =========================================================
           A4 PAGE
           ========================================================= */

        .a4-page {
            width: 297mm;
            min-height: 210mm;

            position: relative;

            padding-top: 17.5mm;
            padding-bottom: 17.5mm;

            overflow: visible;

            page-break-inside: avoid;
        }


        /* =========================================================
           RECEIPT CONTAINER
           ========================================================= */

        .receipt-container {
            width: 280mm;

            min-height: 175mm;
            height: auto;

            margin: 0 auto;

            background: #fff;

            border: 1.2px solid #000;

            padding: 1.5mm;

            color: #000;

            overflow: visible;

            page-break-inside: avoid;
        }


        /* =========================================================
           TABLE RESET
           ========================================================= */

        table {
            width: 100%;

            border-collapse: collapse;

            margin: 0;
            padding: 0;

            table-layout: fixed;
        }

        td,
        th {
            margin: 0;
            padding: 0;

            vertical-align: middle;
        }


        /* =========================================================
           HEADER
           ========================================================= */

        .header {
            min-height: 25mm;

            border-bottom: 1.2px solid #000;
        }

        .header-left {
            width: 55%;

            vertical-align: top;

            padding: 1mm 2mm 1mm 1mm;
        }

        .company-name {
            font-size: 17px;

            line-height: 19px;

            font-weight: 900;

            letter-spacing: .15px;

            white-space: nowrap;
        }

        .receipt-badge {
            display: inline-block;

            background: #333;

            color: #fff;

            font-size: 11px;

            font-weight: bold;

            padding: 1px 5px;

            margin-left: 3mm;

            letter-spacing: .5px;

            vertical-align: 2px;
        }

        .company-address {
            margin-top: 1.5mm;

            font-size: 8px;

            line-height: 9px;

            font-weight: 600;
        }

        .header-right {
            width: 45%;

            vertical-align: top;

            padding: 1mm 2mm 0 0;
        }

        .header-line {
            height: 5mm;

            text-align: right;

            white-space: nowrap;

            font-size: 9px;

            font-weight: bold;
        }

        .receipt-number {
            display: inline-block;

            margin-left: 4mm;

            color: #d32f2f;

            font-size: 15px;

            line-height: 15px;

            font-weight: 900;

            font-family: "Courier New", Courier, monospace;
        }

        .header-line .line {
            display: inline-block;

            width: 38mm;

            min-height: 4mm;

            margin-left: 3mm;

            border-bottom: 1px solid #000;

            vertical-align: bottom;

            text-align: center;
        }


        /* =========================================================
           MAIN BODY
           ========================================================= */

        .main-body {
            min-height: 91mm;

            height: auto;
        }

        .left-column {
            width: 64%;
            padding-right: 1.5mm;
            vertical-align: top;
        }

        .right-column {
            width: 36%;
            border: 1.2px solid #000;
            vertical-align: top;
        }


        /* =========================================================
           LEFT FIELDS
           ========================================================= */

        .field-row {
            min-height: 9.6mm;

            height: auto;

            border-bottom: 1.2px solid #000;

            padding: 1mm 0;

            font-size: 9px;

            line-height: 10px;

            font-weight: bold;
        }

        .field-row td {
            vertical-align: bottom;
        }

        .field-label {
            width: 39%;

            padding-left: 1mm;
            padding-right: 2mm;

            white-space: nowrap;

            vertical-align: bottom;
        }

        .field-value {
            width: 61%;

            padding-right: 1mm;

            white-space: normal;

            word-wrap: break-word;

            overflow-wrap: break-word;
        }

        .field-value .line {
            display: block;

            min-height: 5mm;

            height: auto;

            padding-bottom: 1px;

            border-bottom: 1px solid #000;

            white-space: normal;

            word-wrap: break-word;

            overflow-wrap: break-word;
        }


        /* =========================================================
           RECEIVED
           ========================================================= */

        .row-received {
            min-height: 10mm;
        }


        /* =========================================================
           TELEPHONE
           ========================================================= */

        .row-tel {
            min-height: 9.5mm;
        }

        .row-tel .tel-spacer {
            width: 62%;
        }

        .row-tel .tel-label {
            width: 10%;

            padding-right: 2mm;

            text-align: right;

            white-space: nowrap;
        }

        .row-tel .tel-value {
            width: 28%;

            padding-right: 1mm;
        }


        /* =========================================================
           CHEQUE
           ========================================================= */

        .row-cheque .cheque-label {
            width: 25%;
        }

        .row-cheque .cheque-value {
            width: 30%;
        }

        .row-cheque .date-label {
            width: 10%;
        }

        .row-cheque .date-value {
            width: 35%;
        }

        .row-cheque .cheque-value .line,
        .row-cheque .date-value .line {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
        }


        /* =========================================================
           ON / BANK
           ========================================================= */

        .row-on .on-label {
            width: 8%;

            padding-left: 1mm;

            white-space: nowrap;
        }

        .row-on .on-value {
            width: 72%;

            padding-right: 3mm;
        }

        .row-on .bank-label {
            width: 20%;

            text-align: left;

            padding-right: 1mm;

            white-space: nowrap;
        }


        /* =========================================================
           DENOMINATION
           ========================================================= */

        .denom-header {
            height: 8mm;

            font-weight: bold;

            font-size: 9px;

            line-height: 10px;

            text-align: center;

            border-bottom: 1.2px solid #000;
        }

        .denom-header td {
            padding: 1mm 1px;

            border-right: 1px solid #000;
        }

        .denom-header td:last-child {
            border-right: none;
        }

        .col-denom {
            width: 55%;
        }

        .col-rupees {
            width: 38%;
        }

        .col-ps {
            width: 7%;
        }

        .denom-row {
            height: 6.55mm;

            font-size: 8px;

            font-weight: bold;

            border-bottom: 1px solid #000;
        }

        .denom-row td {
            border-right: 1px solid #000;
        }

        .denom-row td:last-child {
            border-right: none;
        }

        .denom-label {
            width: 55%;

            text-align: right;

            padding-right: 4mm;
        }

        .denom-value {
            width: 38%;

            text-align: center;
        }

        .denom-ps {
            width: 7%;

            text-align: center;
        }

        .denom-total {
            height: 7mm;

            font-size: 9px;

            font-weight: bold;

            border-top: 1.2px solid #000;
        }

        .total-label {
            width: 55%;

            text-align: right;

            padding-right: 4mm;

            border-right: 1px solid #000;
        }

        .total-value {
            width: 38%;

            text-align: center;

            border-right: 1px solid #000;
        }

        .total-ps {
            width: 7%;

            text-align: center;
        }


        /* =========================================================
           RUPEES
           ========================================================= */

        .rupees-row {
            min-height: 10mm;

            height: auto;

            border-top: 1.2px solid #000;

            border-bottom: 1px solid #000;
        }

        .rupees-label {
            width: 12%;

            padding-left: 2mm;

            font-size: 10px;

            font-weight: bold;

            white-space: nowrap;
        }

        .rupees-value {
            width: 78%;

            padding: 0 2mm;

            font-size: 10px;

            font-weight: bold;

            white-space: normal;

            word-wrap: break-word;

            overflow-wrap: break-word;

            border-bottom: 1px solid #000;

            min-height: 6mm;

            vertical-align: bottom;
        }

        .rupees-only {
            width: 10%;

            padding-right: 2mm;

            font-size: 10px;

            font-weight: bold;

            text-align: right;

            white-space: nowrap;
        }


        /* =========================================================
           FOOTER
           ========================================================= */

        .footer-table {
            width: 100%;
        }

        .footer-info {
            width: 70%;

            padding: 1.5mm 2mm 0 2mm;

            vertical-align: top;

            font-size: 6.5px;

            line-height: 7.5px;

            font-weight: bold;

            word-wrap: break-word;

            overflow-wrap: break-word;
        }

        .footer-info > div {
            margin-bottom: 1mm;
        }

        .hindi-text {
            font-family: 'HindiFont', DejaVu Sans, sans-serif;

            font-size: 7px;

            line-height: 8px;

            font-weight: normal;

            margin-top: 1mm;
        }

        .signature-area {
            width: 30%;

            padding: 1.5mm 2mm 0 2mm;

            vertical-align: top;

            text-align: right;

            font-size: 8px;

            line-height: 9px;

            font-weight: bold;
        }

        .auth-text {
            margin-top: 9mm;

            padding-top: 1.5mm;

            border-top: 1px solid #000;

            text-align: center;
        }

        .customer-signature {
            height: 8mm;

            border-top: 1.2px solid #000;

            padding: 2mm;

            font-size: 9px;

            line-height: 10px;

            font-weight: bold;
        }


        /* =========================================================
           SCREEN VIEW
           ========================================================= */

        @media screen {

            .a4-page {
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .receipt-container {
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            }
        }


        /* =========================================================
           PRINT
           ========================================================= */

        @media print {

            html,
            body {
                width: 297mm !important;
                min-height: 210mm !important;

                margin: 0 !important;
                padding: 0 !important;

                background: #fff !important;
            }

            .no-print {
                display: none !important;
            }

            .a4-page {
                width: 297mm !important;

                min-height: 210mm !important;

                margin: 0 !important;

                padding-top: 17.5mm !important;
                padding-bottom: 17.5mm !important;

                overflow: visible !important;
            }

            .receipt-container {
                width: 280mm !important;

                min-height: 175mm !important;

                height: auto !important;

                margin: 0 auto !important;

                box-shadow: none !important;

                overflow: visible !important;

                page-break-inside: avoid !important;
            }

            table,
            tr,
            td {
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }

            .receipt-container,
            .header,
            .main-body,
            .footer-table {
                page-break-inside: avoid !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

    </style>

</head>


<body>


    <!-- =========================================================
         PRINT CONTROLS
         ========================================================= -->

    <div class="print-controls no-print">

        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            🖨 Print Receipt
        </button>

        <button
            type="button"
            class="close-button"
            onclick="window.close()"
        >
            ✕ Close
        </button>

    </div>


    @php

        /* =========================================================
           RECEIPT DATA
           ========================================================= */

        $receiptNo = $receipt->type_number ?? $receipt->id;


        /* =========================================================
           RECEIPT DATE
           ========================================================= */

        $receiptDate = '';

        if (!empty($receipt->date)) {

            try {

                $receiptDate = \Carbon\Carbon::parse(
                    $receipt->date
                )->format('d-m-Y');

            } catch (\Throwable $e) {

                $receiptDate = $receipt->date;

            }

        }


        /* =========================================================
           CUSTOMER
           ========================================================= */

        $customerName = strtoupper(
            $receipt->customer_name
            ?? $receipt->name
            ?? ''
        );


        /* =========================================================
           CARE OF
           ========================================================= */

        $careOfTypeMap = [
            1 => 'S/O',
            2 => 'D/O',
            3 => 'W/O',
            4 => 'GUARDIAN',
        ];

        $careOfType = $careOfTypeMap[
            $receipt->care_of_type ?? ''
        ] ?? 'S/O';


        $careOf = strtoupper(
            $receipt->care_of ?? ''
        );


        /* =========================================================
           ADDRESS
           ========================================================= */

        $address = strtoupper(
            $receipt->address ?? ''
        );


        /* =========================================================
           MOBILE
           ========================================================= */

        $mobile = $receipt->mobile ?? '';


        /* =========================================================
           ACCOUNT OF
           ========================================================= */

        $accountOf = '';

        if (!empty($receipt->account_of)) {

            try {

                $accountOf = strtoupper(

                    app(\App\Services\OrgService::class)
                        ->getKeyValueById(
                            (int) $receipt->account_of
                        )
                        ?->value
                        ?? (string) $receipt->account_of

                );

            } catch (\Throwable $e) {

                $accountOf = strtoupper(
                    (string) $receipt->account_of
                );

            }

        }


        /* =========================================================
           PAYMENT MODE
           ========================================================= */

        $paymentMode = '';

        if (!empty($receipt->mode)) {

            try {

                $paymentMode = strtoupper(

                    app(\App\Services\OrgService::class)
                        ->getKeyValueById(
                            (int) $receipt->mode
                        )
                        ?->value
                        ?? (string) $receipt->mode

                );

            } catch (\Throwable $e) {

                $paymentMode = strtoupper(
                    (string) $receipt->mode
                );

            }

        }


        /* =========================================================
           AMOUNT
           ========================================================= */

        $amount = (float) (
            $receipt->amount ?? 0
        );


        /* =========================================================
           INSTRUMENT
           ========================================================= */

        $instrumentNo = strtoupper(
            $receipt->instrument_no ?? ''
        );


        /* =========================================================
           TRANSACTION DATE
           ========================================================= */

        $transactionDate = '';

        if (!empty($receipt->trans_date)) {

            try {

                $transactionDate = \Carbon\Carbon::parse(
                    $receipt->trans_date
                )->format('d-m-Y');

            } catch (\Throwable $e) {

                $transactionDate = $receipt->trans_date;

            }

        }


        /* =========================================================
           BANK
           ========================================================= */

        $bankName = strtoupper(
            $receipt->bank ?? ''
        );


        /* =========================================================
           VOTF
           ========================================================= */

        $votfNo = strtoupper(
            $receipt->otf_no ?? ''
        );


        /* =========================================================
           NUMBER TO WORDS
           ========================================================= */

        $numberToWords = function ($number) {

            $ones = [
                '',
                'ONE',
                'TWO',
                'THREE',
                'FOUR',
                'FIVE',
                'SIX',
                'SEVEN',
                'EIGHT',
                'NINE',
                'TEN',
                'ELEVEN',
                'TWELVE',
                'THIRTEEN',
                'FOURTEEN',
                'FIFTEEN',
                'SIXTEEN',
                'SEVENTEEN',
                'EIGHTEEN',
                'NINETEEN'
            ];


            $tens = [
                '',
                '',
                'TWENTY',
                'THIRTY',
                'FORTY',
                'FIFTY',
                'SIXTY',
                'SEVENTY',
                'EIGHTY',
                'NINETY'
            ];


            $convert = function ($num) use (
                &$convert,
                $ones,
                $tens
            ) {

                if ($num < 20) {

                    return $ones[$num];

                }


                if ($num < 100) {

                    return $tens[
                        intdiv($num, 10)
                    ]
                    . (
                        ($num % 10)
                            ? ' ' . $ones[$num % 10]
                            : ''
                    );

                }


                if ($num < 1000) {

                    return $ones[
                        intdiv($num, 100)
                    ]
                    . ' HUNDRED'
                    . (
                        ($num % 100)
                            ? ' AND ' . $convert($num % 100)
                            : ''
                    );

                }


                if ($num < 100000) {

                    return $convert(
                        intdiv($num, 1000)
                    )
                    . ' THOUSAND'
                    . (
                        ($num % 1000)
                            ? ' ' . $convert($num % 1000)
                            : ''
                    );

                }


                if ($num < 10000000) {

                    return $convert(
                        intdiv($num, 100000)
                    )
                    . ' LAKH'
                    . (
                        ($num % 100000)
                            ? ' ' . $convert($num % 100000)
                            : ''
                    );

                }


                return $convert(
                    intdiv($num, 10000000)
                )
                . ' CRORE'
                . (
                    ($num % 10000000)
                        ? ' ' . $convert($num % 10000000)
                        : ''
                );

            };


            return $number > 0
                ? $convert((int) $number)
                : 'ZERO';

        };


        $amountWords =
            'RUPEES '
            . $numberToWords($amount)
            . ' ONLY';

    @endphp



    <!-- =========================================================
         A4 PAGE
         ========================================================= -->

    <div class="a4-page">


        <!-- =====================================================
             RECEIPT
             ===================================================== -->

        <div class="receipt-container">


            <!-- =================================================
                 HEADER
                 ================================================= -->

            <table class="header">

                <tr>

                    <td class="header-left">

                        <div class="company-name">

                            BIKANER MOTORS PRIVATE LIMITED

                            <span class="receipt-badge">
                                RECEIPT
                            </span>

                        </div>


                        <div class="company-address">

                            Regd. Office :
                            6th Km. Stone, N.H. 11,
                            Jaipur Road,
                            BIKANER (Raj.)

                            <br>

                            B.O. :
                            6th K.M. Stone,
                            Ratangarh Road,
                            CHURU (Raj.)

                        </div>

                    </td>


                    <td class="header-right">


                        <div class="header-line">

                            <span>
                                No.
                            </span>

                            <span class="receipt-number">
                                {{ $receiptNo }}
                            </span>

                        </div>


                        <div class="header-line">

                            <span>
                                DATE
                            </span>

                            <span class="line">
                                {{ $receiptDate }}
                            </span>

                        </div>


                        <div class="header-line">

                            <span>
                                VOTF No.
                            </span>

                            <span class="line">
                                {{ $votfNo }}
                            </span>

                        </div>


                        <div class="header-line">

                            <span>
                                LEDGER FOLIO
                            </span>

                            <span class="line"></span>

                        </div>


                    </td>

                </tr>

            </table>



            <!-- =================================================
                 MAIN BODY
                 ================================================= -->

            <table class="main-body">

                <tr>


                    <!-- =========================================
                         LEFT COLUMN
                         ========================================= -->

                    <td class="left-column">


                        <table>


                            <!-- RECEIVED -->
                            <tr class="field-row row-received">

                                <td class="field-label">

                                    RECEIVED WITH THANKS FROM

                                </td>

                                <td class="field-value">

                                    <span class="line">
                                        {{ $customerName }}
                                    </span>

                                </td>

                            </tr>


                            <!-- CARE OF -->
                            <tr class="field-row">

                                <td class="field-label">

                                    {{ $careOfType }}

                                </td>

                                <td class="field-value">

                                    <span class="line">
                                        {{ $careOf }}
                                    </span>

                                </td>

                            </tr>


                            <!-- ADDRESS -->
                            <tr class="field-row">

                                <td class="field-label">

                                    ADDRESS

                                </td>

                                <td class="field-value">

                                    <span class="line">
                                        {{ $address }}
                                    </span>

                                </td>

                            </tr>


                            <!-- TELEPHONE -->
                            <tr class="field-row row-tel">

                                <td class="tel-spacer">

                                    <span class="line"></span>

                                </td>


                                <td class="tel-label">

                                    TEL No.

                                </td>


                                <td class="tel-value">

                                    <span class="line">
                                        {{ $mobile }}
                                    </span>

                                </td>

                            </tr>


                            <!-- ACCOUNT OF -->
                            <tr class="field-row">

                                <td class="field-label">

                                    On A/c of
                                    Booking/Dues/Against Delivery

                                </td>

                                <td class="field-value">

                                    <span class="line">
                                        {{ $accountOf }}
                                    </span>

                                </td>

                            </tr>


                            <!-- HYPO -->
                            <tr class="field-row">

                                <td class="field-label">

                                    HYPO BY

                                </td>

                                <td class="field-value">

                                    <span class="line">
                                        {{ $bankName }}
                                    </span>

                                </td>

                            </tr>


                            <!-- CHEQUE / PAYMENT -->
                            <tr class="field-row row-cheque">

                                <td class="cheque-label">

                                    BY {{ $paymentMode ?: 'CASH' }}

                                    @if(!empty($instrumentNo))
                                        No.
                                    @endif

                                </td>


                                <td class="cheque-value">

                                    <span class="line">
                                        {{ $instrumentNo }}
                                    </span>

                                </td>


                                <td class="date-label">

                                    @if(!empty($transactionDate))
                                        DATE
                                    @endif

                                </td>


                                <td class="date-value">

                                    <span class="line">
                                        {{ $transactionDate }}
                                    </span>

                                </td>

                            </tr>


                            <!-- BANK -->
                            <tr
                                class="field-row row-on"
                                style="border-bottom:none;"
                            >

                                <td class="on-label">

                                    ON

                                </td>


                                <td class="on-value">

                                    <span class="line">
                                        {{ $bankName }}
                                    </span>

                                </td>


                                <td class="bank-label">

                                    @if(!empty($bankName))
                                        (BANK)
                                    @endif

                                </td>

                            </tr>


                        </table>


                    </td>



                    <!-- =========================================
                         RIGHT COLUMN
                         ========================================= -->

                    <td class="right-column">


                        <table>


                            <!-- DENOMINATION HEADER -->
                            <tr class="denom-header">

                                <td class="col-denom">

                                    DENOMINATION DETAILS

                                </td>

                                <td class="col-rupees">

                                    RUPEES

                                </td>

                                <td class="col-ps">

                                    PS

                                </td>

                            </tr>


                            <!-- DENOMINATIONS -->

                            @foreach([500, 200, 100, 50, 20, 10, 5, 2, 1] as $denomination)

                                <tr class="denom-row">

                                    <td class="denom-label">

                                        × {{ $denomination }}

                                    </td>

                                    <td class="denom-value"></td>

                                    <td class="denom-ps"></td>

                                </tr>

                            @endforeach


                            <!-- TOTAL -->
                            <tr class="denom-total">

                                <td class="total-label">

                                    TOTAL

                                </td>

                                <td class="total-value">

                                    {{ number_format($amount, 2) }}

                                </td>

                                <td class="total-ps"></td>

                            </tr>


                        </table>


                    </td>

                </tr>

            </table>



            <!-- =================================================
                 RUPEES
                 ================================================= -->

            <table>

                <tr class="rupees-row">

                    <td class="rupees-label">

                        RUPEES

                    </td>


                    <td class="rupees-value">

                        {{ $amountWords }}

                    </td>


                    <td class="rupees-only">

                        ONLY

                    </td>

                </tr>

            </table>



            <!-- =================================================
                 FOOTER
                 ================================================= -->

            <table class="footer-table">

                <tr>


                    <!-- FOOTER INFORMATION -->
                    <td class="footer-info">


                        <div>

                            1. This receipt is issued subject to
                            realisation of Cheque/Demand draft.

                            &nbsp;&nbsp;

                            2. Rates will be charged as per the
                            prevailing price at the time of delivery.

                        </div>


                        <div>

                            3. For refund amounts, payment will be made
                            only through bank transfer to the bank account
                            of the person named on the original receipt.
                            Cash refunds are not permitted.

                        </div>


                        <div class="hindi-text">

                            नोट :
                            "यदि यहाँ दर्ज किया गया कोई भी सुधार साख्य है
                            संस्थित पाया जाने के कारण कम्पनी/अधिकारी द्वारा
                            हस्ताक्षरित, दिनांक अंकित किए जाने पर, तो कृपया
                            अग्र राशि को प्राप्त मानें तथा इसके 2 दिनों के
                            भीतर कार्यालय में संपर्क करें। अधिकृत अधिकारी
                            द्वारा हस्ताक्षरित रसीद ही मान्य होगी।"

                        </div>


                    </td>


                    <!-- SIGNATURE -->
                    <td class="signature-area">


                        <div>

                            For :
                            BIKANER MOTORS PRIVATE LIMITED

                        </div>


                        <div class="auth-text">

                            AUTHORISED SIGNATORY/CASHIER

                        </div>


                    </td>

                </tr>


                <!-- CUSTOMER SIGNATURE -->
                <tr>

                    <td
                        colspan="2"
                        class="customer-signature"
                    >

                        SIGNATURE OF CUSTOMER

                    </td>

                </tr>

            </table>


        </div>

    </div>



    <!-- =========================================================
         OPTIONAL: OPEN PRINT DIALOG AUTOMATICALLY
         ========================================================= -->

    <script>

        /*
         * First test:
         * Keep this commented.
         *
         * After confirming the page looks correct,
         * you can uncomment it if you want the browser
         * print dialog to open automatically.
         */

        // window.addEventListener('load', function () {
        //     setTimeout(function () {
        //         window.print();
        //     }, 500);
        // });


        /*
         * After printing, browser returns to this page.
         * This is optional.
         */

        window.onafterprint = function () {

            console.log('Receipt printing completed.');

        };

    </script>

</body>

</html>