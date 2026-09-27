<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Receipt {{ $receipt->type_number ?? $receipt->id }}
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* =========================================================
           TAILWIND CUSTOM CONFIG & FONT
           ========================================================= */
        @font-face {
            font-family: 'HindiFont';
            src: url("{{ asset('fonts/Lohit-Devanagari.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* Custom utilities that Tailwind can't easily generate */
        .font-hindi {
            font-family: 'HindiFont', DejaVu Sans, sans-serif;
        }

        .receipt-number-font {
            font-family: "Courier New", Courier, monospace;
        }

        /* Page setup */
        @page {
            size: A4 landscape;
            margin: 0;
        }

        /* Screen preview background */
        body {
            background: #f0f0f0;
        }

        /* Print adjustments */
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

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        hindi: ['HindiFont', 'DejaVu Sans', 'sans-serif'],
                        mono: ['"Courier New"', 'Courier', 'monospace'],
                    },
                    screens: {
                        print: { raw: 'print' },
                    },
                }
            }
        }
    </script>

</head>


<body class="m-0 p-0 w-[297mm] min-h-[210mm] bg-[#f0f0f0] text-black font-[Arial,Helvetica,sans-serif] text-[9px]">


    <!-- =========================================================
         PRINT CONTROLS
         ========================================================= -->

    <div class="print-controls no-print fixed top-[15px] right-[15px] z-[99999] flex gap-[10px] bg-white/95 p-[10px] border border-[#ccc] rounded-[6px] shadow-[0_3px_12px_rgba(0,0,0,0.15)]">

        <button
            type="button"
            class="print-button bg-[#198754] text-white border-none px-[16px] py-[9px] text-[13px] font-bold rounded-[4px] cursor-pointer hover:opacity-90"
            onclick="window.print()"
        >
            🖨 Print Receipt
        </button>

        <button
            type="button"
            class="close-button bg-[#dc3545] text-white border-none px-[16px] py-[9px] text-[13px] font-bold rounded-[4px] cursor-pointer hover:opacity-90"
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

    <div class="a4-page w-[297mm] min-h-[210mm] relative pt-[17.5mm] pb-[17.5mm] overflow-visible break-inside-avoid">


        <!-- =====================================================
             RECEIPT
             ===================================================== -->

        <div class="receipt-container w-[280mm] min-h-[175mm] h-auto mx-auto bg-white border-[1.2px] border-black p-[1.5mm] text-black overflow-visible break-inside-avoid">


            <!-- =================================================
                 HEADER
                 ================================================= -->

            <table class="header w-full border-collapse table-fixed border-b-[1.2px] border-black">

                <tr>

                    <td class="header-left w-[62%] align-top p-[1mm_2mm_1mm_1mm]">

                        <div class="company-name text-[17px] leading-[19px] font-black tracking-[.15px] whitespace-nowrap">

                            BIKANER MOTORS PRIVATE LIMITED

                            <span class="receipt-badge inline-block bg-[#333] text-white text-[11px] font-bold px-[5px] py-[1px] ml-[36mm] tracking-[.5px] align-[2px]">
                                RECEIPT
                            </span>

                        </div>


                        <div class="company-address mt-[1.5mm] text-[8px] leading-[9px] font-semibold">

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


                    <td class="header-right w-[38%] align-top p-[1mm_2mm_0_0]">


    <!-- No. (unchanged, top line) -->
    <div class="header-line h-[5mm] text-right whitespace-nowrap text-[9px] font-bold">

        <span>
            No.
        </span>

        <span class="receipt-number inline-block ml-[4mm] text-[#d32f2f] text-[15px] leading-[15px] font-black receipt-number-font">
            {{ $receiptNo }}
        </span>

    </div>


    <!-- Date | VOTF No. | Ledger Folio (single line) -->
    <div class="header-line h-[5mm] text-right whitespace-nowrap text-[9px] font-bold flex justify-end items-end gap-[3mm]">

        <span>
            DATE
            <span class="line inline-block w-[22mm] min-h-[4mm] ml-[1mm] border-b border-black align-bottom text-center">
                {{ $receiptDate }}
            </span>
        </span>

        <span>
            VOTF No.
            <span class="line inline-block w-[22mm] min-h-[4mm] ml-[1mm] border-b border-black align-bottom text-center">
                {{ $votfNo }}
            </span>
        </span>

        <span>
            LEDGER FOLIO
            <span class="line inline-block w-[22mm] min-h-[4mm] ml-[1mm] border-b border-black align-bottom text-center"></span>
        </span>

    </div>


</td>

                </tr>

            </table>



            <!-- =================================================
                 MAIN BODY
                 ================================================= -->

            <table class="main-body w-full border-collapse table-fixed min-h-[91mm] h-auto">

                <tr>


                    <!-- =========================================
                         LEFT COLUMN
                         ========================================= -->

                    <td class="left-column w-[64%] pr-[1.5mm] align-top">


                        <table class="w-full border-collapse table-fixed border-[1.2px] border-black">


                            <!-- RECEIVED -->
                            <tr class="field-row row-received min-h-[10mm] h-auto border-b-[1.2px] py-[1mm] text-[9px] leading-[10px] font-bold mb-3">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    RECEIVED WITH THANKS FROM

                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $customerName }}
                                    </span>

                                </td>

                            </tr>


                            <!-- CARE OF -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px]  py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    {{ $careOfType }}

                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $careOf }}
                                    </span>

                                </td>

                            </tr>


                            <!-- ADDRESS -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px] py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">
                                    ADDRESS
                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">
                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $address }}
                                    </span>
                                </td>

                            </tr>


                            <!-- EMPTY ROW (address badhne ke liye extra line) -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px] border-black py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom"></td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">
                                    <span class="line block min-h-[5mm] h-auto pb-[1px] whitespace-normal break-words"></span>
                                </td>

                            </tr>


                            <!-- TELEPHONE (normal line, same as RECEIVED row style) -->
                            <tr class="field-row min-h-[9.5mm] h-auto border-b-[1.2px] py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">
                                    TEL No.
                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">
                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $mobile }}
                                    </span>
                                </td>

                            </tr>


                            <!-- ACCOUNT OF -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px] py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    On A/c of
                                    Booking/Dues/Against Delivery

                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $accountOf }}
                                    </span>

                                </td>

                            </tr>


                            <!-- HYPO -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px] py-[1mm] text-[9px] leading-[10px] font-bold">

                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    HYPO BY

                                </td>

                                <td class="field-value w-[61%] pr-[1mm] whitespace-normal break-words align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $bankName }}
                                    </span>

                                </td>

                            </tr>


                            <!-- BY CHEQUE No. (single line) -->
                            <tr class="field-row row-cheque min-h-[9.6mm] h-auto border-b-[1.2px]  py-[1mm] text-[9px] leading-[10px] font-bold">

                                <!-- BY CHEQUE No. label -->
                                <td class="cheque-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    BY {{ $paymentMode ?: 'CASH' }}

                                    @if(!empty($instrumentNo))
                                        No.
                                    @endif

                                </td>


                                <!-- Cheque No. value -->
                                <td class="cheque-value w-[61%] pr-[1mm] align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-nowrap overflow-visible text-clip">
                                        {{ $instrumentNo }}
                                    </span>

                                </td>

                            </tr>


                            <!-- DATE (next line, single line) -->
                            <tr class="field-row min-h-[9.6mm] h-auto border-b-[1.2px]  py-[1mm] text-[9px] leading-[10px] font-bold">

                                <!-- DATE label -->
                                <td class="field-label w-[39%] pl-[1mm] pr-[2mm] whitespace-nowrap align-bottom">

                                    @if(!empty($transactionDate))
                                        DATE
                                    @endif

                                </td>


                                <!-- Date value -->
                                <td class="field-value w-[61%] pr-[1mm] align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-nowrap overflow-visible text-clip">
                                        {{ $transactionDate }}
                                    </span>

                                </td>

                            </tr>


                            <!-- ON / BANK (single line) -->
                            <tr class="field-row row-on min-h-[9.6mm] h-auto py-[1mm] text-[9px] leading-[10px] font-bold" style="border-bottom:none;">

                                <!-- ON label -->
                                <td class="on-label w-[8%] pl-[1mm] whitespace-nowrap align-bottom">

                                    ON

                                </td>


                                <!-- Bank name value -->
                                <td class="on-value w-[72%] pr-[3mm] align-bottom">

                                    <span class="line block min-h-[5mm] h-auto pb-[1px] border-b border-black whitespace-normal break-words">
                                        {{ $bankName }}
                                    </span>

                                </td>



                            </tr>


                        </table>


                    </td>



                    <!-- =========================================
                         RIGHT COLUMN
                         ========================================= -->

                    <td class="right-column w-[36%] align-top">


                        <table class="w-full border-collapse table-fixed border-[1.2px] border-black">


                            <!-- DENOMINATION HEADER -->
                            <tr class="denom-header h-[8mm] font-bold text-[9px] leading-[10px] text-center border-b-[1.2px] border-black">

                                <td class="col-denom w-[55%] p-[1mm_1px] border-r border-black">

                                    DENOMINATION DETAILS

                                </td>

                                <td class="col-rupees w-[38%] p-[1mm_1px] border-r border-black">

                                    RUPEES

                                </td>

                                <td class="col-ps w-[7%] p-[1mm_1px]">

                                    PS

                                </td>

                            </tr>


                            <!-- DENOMINATIONS -->

                            @foreach([500, 200, 100, 50, 20, 10, 5, 2, 1] as $denomination)

                                <tr class="denom-row h-[6.55mm] text-[8px] font-bold border-b border-black">

                                    <td class="denom-label w-[55%] text-right pr-[4mm] border-r border-black">

                                        × {{ $denomination }}

                                    </td>

                                    <td class="denom-value w-[38%] text-center border-r border-black"></td>

                                    <td class="denom-ps w-[7%] text-center"></td>

                                </tr>

                            @endforeach


                            <!-- TOTAL -->
                            <tr class="denom-total h-[7mm] text-[9px] font-bold border-t-[1.2px] border-black">

                                <td class="total-label w-[55%] text-right pr-[4mm] border-r border-black">

                                    TOTAL

                                </td>

                                <td class="total-value w-[38%] text-center border-r border-black">

                                    {{ number_format($amount, 2) }}

                                </td>

                                <td class="total-ps w-[7%] text-center"></td>

                            </tr>


                        </table>


                    </td>

                </tr>

            </table>



            <!-- =================================================
                 RUPEES
                 ================================================= -->

            <table class="w-full border-collapse table-fixed border-[1.2px] border-black">

                <tr class="rupees-row min-h-[10mm] h-auto border-t-[1.2px] border-black border-b border-black">

                    <td class="rupees-label w-[12%] pl-[2mm] text-[10px] font-bold whitespace-nowrap">

                        RUPEES

                    </td>


                    <td class="rupees-value w-[78%] px-[2mm] text-[10px] font-bold whitespace-normal break-words border-b border-black min-h-[6mm] align-bottom">

                        {{ $amountWords }}

                    </td>


                    <td class="rupees-only w-[10%] pr-[2mm] text-[10px] font-bold text-right whitespace-nowrap">

                        ONLY

                    </td>

                </tr>

            </table>



            <!-- =================================================
                 FOOTER
                 ================================================= -->

            <table class="footer-table w-full border-collapse table-fixed">

                <tr>


                    <!-- FOOTER INFORMATION -->
                    <td class="footer-info w-[70%] p-[1.5mm_2mm_0_2mm] align-top text-[6.5px] leading-[7.5px] font-bold break-words">


                        <div class="mb-[1mm]">

                            1. This receipt is issued subject to
                            realisation of Cheque/Demand draft.

                            &nbsp;&nbsp;

                            2. Rates will be charged as per the
                            prevailing price at the time of delivery.

                        </div>


                        <div class="mb-[1mm]">

                            3. For refund amounts, payment will be made
                            only through bank transfer to the bank account
                            of the person named on the original receipt.
                            Cash refunds are not permitted.

                        </div>


                        <div class="font-hindi text-[7px] leading-[8px] font-normal mt-[1mm]">

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
                    <td class="signature-area w-[30%] p-[1.5mm_2mm_0_2mm] align-top text-right text-[8px] leading-[9px] font-bold">


                        <div>

                            For :
                            BIKANER MOTORS PRIVATE LIMITED

                        </div>


                        <div class="auth-text mt-[9mm] pt-[1.5mm] border-t border-black text-center">

                            AUTHORISED SIGNATORY/CASHIER

                        </div>


                    </td>

                </tr>


                <!-- CUSTOMER SIGNATURE -->
                <tr>

                    <td
                        colspan="2"
                        class="customer-signature h-[8mm] border-t-[1.2px] border-black p-[2mm] text-[9px] leading-[10px] font-bold"
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