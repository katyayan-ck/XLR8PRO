@extends(backpack_view('blank'))

@php
    use App\Services\OrgService;
@endphp

@section('title', isset($quotation) ? 'Edit Quotation' : 'Quotation Form')

@push('after_styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        .header-logo-left img {
            height: 75px;
        }

        .header-logo-right img {
            max-height: 85px;
            max-width: 170px;
        }

        .header-title h3 {
            margin-bottom: 4px;
        }

        .header-title h4 {
            margin-top: 8px;
            font-weight: bold;
        }

        @media print {
            select {
                appearance: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                background: transparent !important;
                background-image: none !important;
                border: none !important;
                outline: none !important;
                padding-right: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .print-only-inline {
                display: inline-block !important;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .quotation-sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: 1px solid #000;
                display: flex;
                flex-direction: column;
            }

            /* Hide column 2 (OPTION in the price table, TYPE in the discount table) */
            .price-grid th:nth-child(2),
            .price-grid td:nth-child(2),
            .discount-grid th:nth-child(2),
            .discount-grid td:nth-child(2) {
                display: none !important;
            }

            /* Reassign widths for the 2 remaining columns per table - each totals 100% */
            .price-grid th:nth-child(1) {
                width: 53% !important;
            }

            .price-grid th:nth-child(3) {
                width: 47% !important;
            }

            .discount-grid th:nth-child(1) {
                width: 49% !important;
            }

            .discount-grid th:nth-child(3) {
                width: 51% !important;
            }

            .quotation-grid {
                width: 100% !important;
                table-layout: fixed !important;
            }

            .quotation-grid-split {
                gap: 6px !important;
            }

            .quotation-grid tr.print-hide {
                display: none !important;
            }

            .bank-print .quotation-grid tbody tr:not(.print-hide) {
                display: table-row !important;
            }

            .bank-print .quotation-grid tbody tr.print-hide {
                display: none !important;
            }

            .financier-discount-grid {
                display: none !important;
            }

            .accessories-note-row {
                display: block !important;
            }

            .quotation-sheet,
            .quotation-grid,
            .quotation-summary,
            .bill-table {
                width: 100% !important;
            }

            /* Fix summary alignment to match 4 columns */
            .quotation-summary {
                display: flex !important;
                align-items: stretch !important;
                width: 100% !important;
                border: 1px solid #000 !important;
            }

            .quotation-summary .total-row-cell,
            .quotation-summary .onroad-row-cell {
                display: flex !important;
                align-items: center !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                border-right: none !important;
                padding: 5px 8px !important;
                min-height: 30px !important;
            }

            .quotation-summary .total-row-cell:last-child,
            .quotation-summary .onroad-row-cell:last-child {
                border-right: 1px solid #000 !important;
            }

            .quotation-summary input {
                width: 100% !important;
                text-align: right !important;
                border: none !important;
                background: transparent !important;
                padding: 2px 5px !important;
            }

            /* Match the 4-column proportions */
            .quotation-summary .total-receivable-label {
                flex: 0 0 32% !important;
            }

            .quotation-summary .total-receivable-amount {
                flex: 0 0 18% !important;
                justify-content: flex-end !important;
            }

            .quotation-summary .total-discount-label {
                flex: 0 0 32% !important;
            }

            .quotation-summary .total-discount-amount {
                flex: 0 0 18% !important;
                justify-content: flex-end !important;
            }

            .quotation-summary .onroad-label {
                flex: 0 0 17% !important;
            }

            .quotation-summary .onroad-amount {
                flex: 0 0 13% !important;
                justify-content: center !important;
                align-items: center !important;
            }

            .quotation-summary .onroad-words {
                flex: 1 1 70% !important;
                justify-content: flex-end !important;
            }

        }

        .print-only-inline {
            display: none;
        }





        .quotation-sheet {

            background: #fff;

            border: 1px solid #000;

            padding: 15px;

        }


        .bill-table {

            width: 100%;

            border-collapse: collapse;

        }



        .bill-table .title {

            background: #f2f2f2;

            font-weight: bold;

        }



        .bill-table input:focus {

            outline: none;

        }

        .bill-table select {

            border: none;

            width: 100%;

            background: transparent;

        }

        .bill-table select:focus {

            outline: none;

        }

        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .bill-table td {
            border: 1px solid #000;
            padding: 2px 5px;

            height: 26px;

            font-size: 10px;
            vertical-align: middle;
        }

        .bill-table .title {
            background: #f2f2f2;
            font-weight: 600;
            white-space: nowrap;
        }

        .bill-table input {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 2px;
        }


        /* Hide Backpack UI */

        .page-header {
            display: none !important;
        }

        .navbar {
            display: none !important;
        }

        .main-header {
            display: none !important;
        }

        .sidebar {
            display: none !important;
        }

        .app-footer {
            display: none !important;
        }

        .breadcrumb {
            display: none !important;
        }

        .content-header {
            display: none !important;
        }

        .wrapper {
            padding-top: 0 !important;
        }

        .main-body {
            margin-top: 0 !important;
        }

        /* Select2 fixed height */
        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--multiple {
            min-height: 32px !important;
            height: 32px !important;
            overflow: hidden !important;
        }

        .select2-container--default .select2-selection__rendered {
            display: flex !important;
            align-items: center;
            height: 30px;
            overflow: hidden;
        }

        /* Hide selected chips */
        .select2-selection__choice {
            display: none !important;
        }

        .select2-search--inline {
            width: 100% !important;
        }

        .select2-search__field {
            width: 100% !important;
        }

        .row.align-items-stretch {
            align-items: stretch;
        }

        .note-table {
            flex: 1;
        }

        .note-table td {
            vertical-align: top;
        }

        .note-box {
            margin-top: 12px;
        }

        /* ================= Quotation Grid (Price / Discount) — now TWO independent
                                                                           tables/boxes placed side by side. Because each side is its own table with
                                                                           its own <tbody>, every row (price item or discount item) can be shown or
                                                                           hidden completely independently. When a field is blank / 0 / N/A, its row
                                                                           is simply removed from the flow (display:none) while printing, and the
                                                                           remaining rows in that box naturally move up to close the gap — the two
                                                                           boxes no longer need to stay row-for-row aligned with each other. ================= */

        .quotation-box {
            margin-bottom: 15px;
        }

        .quotation-grid-split {
            display: flex;
            gap: 3px;
            align-items: flex-start;
        }

        .quotation-grid-split>.quotation-grid-col {
            flex: 1 1 50%;
            min-width: 0;
        }

        .quotation-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .quotation-grid th,
        .quotation-grid td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 10px;
            height: 26px;
            vertical-align: middle;
            overflow: hidden;
        }

        .quotation-grid thead th {
            background: #d9d9d9;
            font-weight: bold;
            text-align: center;
        }

        /* Column widths are set on the <thead> cells (not a <colgroup>/<col>) because
                                                                           with table-layout:fixed the widths of the FIRST ROW's cells define every
                                                                           column's width for the whole table — this is the spec-defined, most
                                                                           reliably-supported way across browsers/print engines, unlike overriding
                                                                           <col> widths which some print renderers ignore. */
        .price-grid th:nth-child(1) {
            width: 32%;
        }

        .price-grid th:nth-child(2) {
            width: 40%;
        }

        .price-grid th:nth-child(3) {
            width: 28%;
        }

        .discount-grid th:nth-child(1) {
            width: 35%;
        }

        .discount-grid th:nth-child(2) {
            width: 31%;
        }

        .discount-grid th:nth-child(3) {
            width: 34%;
        }

        .quotation-grid td.cell-label {
            background: #f2f2f2;
            font-weight: 600;
        }



        .quotation-grid td.cell-label .group-select {
            background: #f2f2f2;
            font-weight: 600;
        }



        .quotation-grid input,
        .quotation-grid select {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 10px;
            padding: 2px;
        }

        .quotation-grid input:focus,
        .quotation-grid select:focus {
            outline: none;
        }

        /* ================= Quotation Summary (Total Receivable / Total Discount / On Road Price) ================= */
        /* ================= Quotation Summary (Total Receivable / Total Discount / On Road Price) ================= */
        .quotation-summary {
            display: flex;
            font-weight: bold;
            border: 1px solid #000;
            width: 100%;
        }

        .quotation-summary .total-row-cell {
            background: #f2f2f2;
            display: flex;
            align-items: center;
            padding: 5px 8px;
            min-height: 30px;
        }

        .quotation-summary .total-receivable-label {
            flex: 0 0 32%;
        }

        .quotation-summary .total-receivable-amount {
            flex: 0 0 18%;
            justify-content: flex-end;
        }

        .quotation-summary .total-discount-label {
            flex: 0 0 32%;
        }

        .quotation-summary .total-discount-amount {
            flex: 1 1 18%;
            justify-content: flex-end;
        }

        .quotation-summary .onroad-row-cell {
            background: #abb8ca;
            color: #000000;
            padding: 5px 8px;
            display: flex;
            align-items: center;
            min-height: 30px;
        }

        .quotation-summary .onroad-label {
            flex: 0 0 25%;
        }

        .quotation-summary .onroad-amount {
            flex: 1 1 18%;
            justify-content: center;
        }

        .quotation-summary input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            padding: 2px 5px;
        }

        /* ================= Financier Invoice / Discount Bifurcation — div based ================= */
        .financier-discount-grid {
            display: grid;
            grid-template-columns: 25% 25% 25% 25%;
            border-left: 1px solid #000;
            border-top: 1px solid #000;
            margin-bottom: 15px;
        }

        .financier-discount-grid>div {
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 3px 5px;
            font-size: 10px;
            min-height: 26px;
            display: flex;
            align-items: center;
        }

        .financier-discount-grid .fd-header {
            background: #d9d9d9;
            font-weight: bold;
            text-align: center;
            justify-content: center;
            grid-column: span 2;
        }

        .financier-discount-grid .fd-label {
            background: #f2f2f2;
            font-weight: 600;
        }

        .financier-discount-grid .fd-bold input {
            font-weight: bold;
        }

        .financier-discount-grid input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 10px;
        }


        .cell-label:has(.group-select) {
            background: #f2f2f2;
            font-weight: 600;
        }

        /* Accessories note line: hidden on screen, shown only in print above the Note box */
        .insurance-note-row,
        .accessories-note-row {
            display: block;
            padding: 4px 5px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        #insurance_print,
        #accessories_print {
            font-weight: normal;
            white-space: normal;
            word-break: break-word;
            display: inline;
        }

        @media print {

            /* OPTION and TYPE columns are always folded into the label / omitted for print.
                                                                               Each table now only has 3 columns of its own, so this is simply column 2. */
            .price-grid th:nth-child(2),
            .price-grid td:nth-child(2),
            .discount-grid th:nth-child(2),
            .discount-grid td:nth-child(2) {
                display: none !important;
            }

            /* Hiding column 2 above would otherwise leave each table using less than
                                                                               its full width (blank space on the right), so the box would look
                                                                               "shrunk". Re-assign the widths of the 2 remaining columns per table
                                                                               (on the <th> cells, since that's what actually drives
                                                                               table-layout:fixed column sizing) so they always add up to 100%
                                                                               while printing. Price and discount keep their own ratio. */
            .price-grid th:nth-child(1) {
                width: 70% !important;
            }

            .price-grid th:nth-child(3) {
                width: 30% !important;
            }

            .discount-grid th:nth-child(1) {
                width: 70% !important;
            }

            .discount-grid th:nth-child(3) {
                width: 30% !important;
            }

            /* Every price/discount item is its own independent row now. A row is
                                                                               hidden purely on its own value being blank / 0 / N/A — the other box
                                                                               is completely unaffected, and its own remaining rows just move up to
                                                                               close the gap since it's normal table flow. */
            .quotation-grid tr.print-hide {
                display: none !important;
            }

            .quotation-grid-split {
                gap: 3px !important;
            }

            /* Hide Financier Invoice / Discount Bifurcation box while printing */
            .financier-discount-grid {
                display: none !important;
            }

            /* Show the Accessories line above the Note box only while printing */
            .accessories-note-row {
                display: block !important;
            }

            /* Keep the boxes at full width, don't let them shrink when items are hidden */
            .quotation-sheet,
            .quotation-grid,
            .quotation-summary,
            .bill-table {
                width: 100% !important;
            }

            #accessories+.select2-container {
                display: none !important;
            }

            /* Sirf text dikhao */
            #accessories_print {
                display: block !important;
                white-space: normal;
                font-size: 11px;
                line-height: 15px;
            }
        }

        .quotation-grid input.numeric-only,
        .quotation-grid input.amount-field,
        .quotation-summary input,
        .financier-discount-grid input {
            text-align: right !important;
        }

        /* ================= Custom Multi-Select with Checkboxes ================= */
        .select2-container--default .select2-results__option {
            padding: 6px 12px;
            user-select: none;
        }

        .select2-container--default .select2-results__option .select2-checkbox {
            margin-right: 8px;
            width: 15px;
            height: 15px;
            accent-color: #0d6efd;
            cursor: pointer;
        }

        .select2-checkbox-label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        /* Disabled/Freezed options */
        .select2-container--default .select2-results__option--disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .select2-container--default .select2-results__option--disabled .select2-checkbox {
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* ================= HIDE CHIPS - ONLY SHOW COUNT ================= */
        /* Hide chips completely for both selects */
        .select2-selection--multiple .select2-selection__choice {
            display: none !important;
        }

        /* Show only the placeholder/count text */
        .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
        }

        .select2-selection--multiple .select2-selection__rendered .select2-selection__placeholder {
            color: #6c757d;
        }

        /* Selected count text style - override any other styles */
        .select2-selection--multiple .select2-selection__rendered .select2-selection__choice {
            display: none !important;
        }

        /* Fix for selection display */
        .select2-selection--multiple .select2-selection__rendered li {
            list-style: none;
        }

        .select2-selection--multiple .select2-selection__rendered .select2-selection__choice__remove {
            display: none !important;
        }

        /* ================= Discount Bifurcation Box ================= */
        .discount-bifurcation-box {
            margin-top: 10px;
        }

        .bifurcation-grid input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 10px;
            padding: 2px;
        }

        .bifurcation-grid input:focus {
            outline: none;
        }

        @media print {
            .discount-bifurcation-box {
                display: none !important;
            }

            #careof_name_print {
                font-weight: 500 !important;
                font-size: 10px !important;
                color: #000 !important;
                display: inline-block !important;
            }
        }

        /* ============================================================
                                                                       INSURANCE DROPDOWNS
                                                                       ============================================================ */

        /* Insurance Company */
        #insurance_company {
            width: 100% !important;
            height: 26px !important;

            appearance: auto !important;
            -webkit-appearance: auto !important;
            -moz-appearance: auto !important;

            background-color: #fff !important;
            background-image: var(--bs-form-select-bg-img) !important;
            background-repeat: no-repeat !important;
            background-position: right 6px center !important;
            background-size: 10px 7px !important;

            padding: 1px 22px 1px 5px !important;
            border: 1px solid #ccc !important;
            border-radius: 3px !important;

            font-size: 10px !important;
            line-height: 22px !important;
        }


        /* ============================================================
                                                                       Insurance Covers - Select2
                                                                       ============================================================ */

        /* ============================================================
                                                                       INSURANCE COVERS - FINAL SELECT2 FIX
                                                                       ============================================================ */

        #insurance_covers+.select2-container {
            width: 100% !important;
            height: 26px !important;
            position: relative !important;
        }

        /* Select2 outer box */
        #insurance_covers+.select2-container .select2-selection--multiple {
            width: 100% !important;
            height: 26px !important;
            min-height: 26px !important;

            border: 1px solid #ccc !important;
            border-radius: 3px !important;

            background: #fff !important;

            padding: 0 !important;
            margin: 0 !important;

            box-sizing: border-box !important;

            overflow: visible !important;
        }

        /* Rendered UL */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-selection__rendered {
            width: 100% !important;
            height: 24px !important;
            min-height: 24px !important;

            display: block !important;

            padding: 0 !important;
            margin: 0 !important;

            position: relative !important;

            overflow: visible !important;
        }

        /* Search LI */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-search--inline {
            position: absolute !important;

            left: 4px !important;
            right: 22px !important;
            top: 1px !important;

            width: auto !important;
            height: 22px !important;

            display: block !important;

            margin: 0 !important;
            padding: 0 !important;

            z-index: 10 !important;
        }

        /* ACTUAL SEARCH INPUT */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-search--inline .select2-search__field {
            display: block !important;

            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;

            height: 22px !important;

            margin: 0 !important;
            padding: 1px 0 !important;

            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;

            background: transparent !important;

            color: #212529 !important;
            -webkit-text-fill-color: #212529 !important;

            caret-color: #212529 !important;

            font-size: 10px !important;
            line-height: 20px !important;

            opacity: 1 !important;

            text-indent: 0 !important;

            visibility: visible !important;
        }

        /* Placeholder */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-search--inline .select2-search__field::placeholder {
            color: #6c757d !important;
            -webkit-text-fill-color: #6c757d !important;
            opacity: 1 !important;
        }

        /* Hide selected chips */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-selection__choice {
            display: none !important;
        }

        /* Small arrow - same visual weight as Insurance Company */
        #insurance_covers+.select2-container::after {
            content: "" !important;

            position: absolute !important;

            right: 7px !important;
            top: 50% !important;

            width: 4px !important;
            height: 4px !important;

            border-right: 1px solid #6c757d !important;
            border-bottom: 1px solid #6c757d !important;

            transform: translateY(-65%) rotate(45deg) !important;

            pointer-events: none !important;

            z-index: 100 !important;
        }


        /* Keep arrow visible even when Select2 is focused */
        #insurance_covers+.select2-container .select2-selection--multiple:focus,
        #insurance_covers+.select2-container .select2-selection--multiple.select2-selection--multiple {

            position: relative !important;
        }


        /* Don't let selected chips/search hide arrow */
        #insurance_covers+.select2-container .select2-selection--multiple .select2-selection__choice {

            display: none !important;
        }

        /* ============================================================
                                                                       ACCESSORIES - SELECT2 FINAL FIX
                                                                       Same UI as Insurance Covers
                                                                       ============================================================ */

        #accessories+.select2-container {
            width: 100% !important;
            height: 26px !important;
            position: relative !important;
        }

        /* Select2 outer box */
        #accessories+.select2-container .select2-selection--multiple {
            width: 100% !important;
            height: 26px !important;
            min-height: 26px !important;

            border: 1px solid #ccc !important;
            border-radius: 3px !important;

            background: #fff !important;

            padding: 0 !important;
            margin: 0 !important;

            box-sizing: border-box !important;
            overflow: visible !important;
        }

        /* Rendered UL */
        #accessories+.select2-container .select2-selection--multiple .select2-selection__rendered {

            width: 100% !important;
            height: 24px !important;
            min-height: 24px !important;

            display: block !important;

            padding: 0 !important;
            margin: 0 !important;

            position: relative !important;
            overflow: visible !important;
        }

        /* Search LI */
        #accessories+.select2-container .select2-selection--multiple .select2-search--inline {

            position: absolute !important;

            left: 4px !important;
            right: 22px !important;
            top: 1px !important;

            width: auto !important;
            height: 22px !important;

            display: block !important;

            margin: 0 !important;
            padding: 0 !important;

            z-index: 10 !important;
        }

        /* Actual Search Input */
        #accessories+.select2-container .select2-selection--multiple .select2-search--inline .select2-search__field {

            display: block !important;

            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;

            height: 22px !important;

            margin: 0 !important;
            padding: 1px 0 !important;

            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;

            background: transparent !important;

            color: #212529 !important;
            -webkit-text-fill-color: #212529 !important;

            caret-color: #212529 !important;

            font-size: 10px !important;
            line-height: 20px !important;

            opacity: 1 !important;
            text-indent: 0 !important;
            visibility: visible !important;
        }

        /* Placeholder */
        #accessories+.select2-container .select2-selection--multiple .select2-search--inline .select2-search__field::placeholder {

            color: #6c757d !important;
            -webkit-text-fill-color: #6c757d !important;
            opacity: 1 !important;
        }

        /* Hide selected chips */
        #accessories+.select2-container .select2-selection--multiple .select2-selection__choice {

            display: none !important;
        }

        /* Small dropdown arrow */
        #accessories+.select2-container::after {

            content: "" !important;

            position: absolute !important;

            right: 7px !important;
            top: 50% !important;

            width: 4px !important;
            height: 4px !important;

            border-right: 1px solid #6c757d !important;
            border-bottom: 1px solid #6c757d !important;

            transform: translateY(-65%) rotate(45deg) !important;

            pointer-events: none !important;

            z-index: 100 !important;
        }

        /* Keep arrow visible */
        #accessories+.select2-container .select2-selection--multiple:focus,
        #accessories+.select2-container .select2-selection--multiple.select2-selection--multiple {

            position: relative !important;
        }

        @media print {
            .net-receivable-container {
                display: block !important;
                width: 100% !important;
                border: 2px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                page-break-inside: avoid !important;
            }

            .net-receivable-container>div:first-child {
                background: #f2f2f2 !important;
                border-bottom: 2px solid #000 !important;
            }

            .net-receivable-container input {
                font-size: 13px !important;
                font-weight: bold !important;
                text-align: right !important;
            }

            .net-receivable-container #net_receivable_words {
                font-style: italic !important;
                font-weight: bold !important;
            }
        }

        .terms-notes-container {
            width: 100%;
            border: 1px solid #000;
            margin-top: 6px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .terms-notes-header {
            background: #f2f2f2;
            border-bottom: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            padding: 3px 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .terms-notes-body {
            display: flex;
            width: 100%;
        }

        .notes-column {
            flex: 1 1 50%;
            padding: 4px 6px 4px 20px;
            box-sizing: border-box;
        }

        .notes-column:first-child {
            border-right: 1px solid #000;
        }

        .notes-list-split {
            margin: 0;
            padding: 0 0 0 10px;
            font-size: 9px;
            line-height: 1.35;
            font-weight: normal;
            text-align: left;
        }

        .notes-list-split li {
            margin-bottom: 1px;
            padding-left: 2px;
        }

        @media print {
            .terms-notes-container {
                border: 1px solid #000 !important;
                margin-top: 4px !important;
            }

            .terms-notes-header {
                background: #f2f2f2 !important;
                border-bottom: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .notes-column:first-child {
                border-right: 1px solid #000 !important;
            }
        }

        /* ================= GROUP DROPDOWNS ================= */
        /* Same visual weight as normal form text */
        select.group-select {
            width: 100% !important;
            height: 100% !important;
            min-height: 24px !important;

            background-color: #f2f2f2 !important;
            color: #495261 !important;

            font-family: inherit !important;
            font-size: 10px !important;
            font-weight: 600 !important;
            line-height: 22px !important;

            border: none !important;
            outline: none !important;
            box-shadow: none !important;

            margin: 0 !important;
            padding: 1px 18px 1px 5px !important;

            vertical-align: middle !important;
            cursor: pointer !important;

            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;

            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236c757d' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 6px center !important;
            background-size: 8px 6px !important;
        }

        /* Parent cell should have same normal text weight */
        .bill-table td:has(.group-select),
        .quotation-grid td:has(.group-select) {
            padding: 0 !important;
            background-color: #f2f2f2 !important;
            font-weight: 500 !important;
        }

        select.group-select:focus {
            outline: none !important;
            box-shadow: none !important;
            background-color: #f2f2f2 !important;
            font-weight: 500 !important;
        }

        /* ================= PRINT ================= */
        @media print {

            select.group-select {
                font-weight: 600 !important;
                color: #495261 !important;
                background-color: transparent !important;
                background-image: none !important;
            }

            .bill-table td:has(.group-select),
            .quotation-grid td:has(.group-select) {
                font-weight: 600 !important;
                color: #495261 !important;
            }

            #careof_relation_print {
                font-weight: 600 !important;
                color: #495261 !important;
            }

            #careof_name_print {
                font-weight: 500 !important;
                color: #000 !important;
            }

            .alert {
                display: none !important;
            }

            #charger_discount_label {
                display: none !important;
            }

            #charger_swapping_option {
                display: none !important;
            }

            #charger_swapping_option_print {
                display: inline-block !important;
                font-weight: 600 !important;
                color: #495261 !important;
                margin-left: 5px !important;
            }
        }

        /* View Mode: Disable all interactions */
        .view-mode input,
        .view-mode select,
        .view-mode textarea,
        .view-mode .select2-selection {
            pointer-events: none !important;
            cursor: default !important;
        }

        /* Highlight financier field when empty */
        #financier.field-error {
            border: 2px solid #dc3545 !important;
            background-color: #fff8f8 !important;
        }


        /* Make frozen amount fields visibly lighter */
        .quotation-grid input.frozen-field {
            color: #999 !important;
            -webkit-text-fill-color: #999 !important;
            opacity: 0.55 !important;
            font-weight: 400 !important;
            background-color: #f8f8f8 !important;
            cursor: not-allowed !important;
        }

        /* Slightly lighter placeholder also */
        .quotation-grid input.frozen-field::placeholder {
            color: #aaa !important;
            -webkit-text-fill-color: #aaa !important;
            opacity: 0.7 !important;
        }

        /* Frozen field should not look like an active input */
        .quotation-grid input.frozen-field:disabled {
            color: #999 !important;
            -webkit-text-fill-color: #999 !important;
            opacity: 0.55 !important;
            background-color: #f8f8f8 !important;
            box-shadow: none !important;
            text-decoration: none !important;
        }
    </style>
@endpush

@section('content')
    @php
        // Debug - check if data is available
        if ($selectedEnquiry) {
            \Log::info('Quotation Form - Enquiry Data:', [
                'id' => $selectedEnquiry->id,
                'segment_code' => $selectedEnquiry->segment_code,
                'model_code' => $selectedEnquiry->model_code,
                'variant_code' => $selectedEnquiry->variant_code,
                'color_code' => $selectedEnquiry->color_code,
            ]);
        }
    @endphp

    @php
        $viewMode = $viewMode ?? false;
    @endphp

    <div class="quotation-form {{ $viewMode ? 'view-mode' : '' }}">
        <div class="container-fluid">

            <div class="card shadow-sm mb-3">
                <div class="card-body py-2 px-3">

                    @php
                        $segment = strtoupper(optional($selectedEnquiry)->segment_code);

                        if ($segment == 'LMM') {
                            $mahindraLogo = asset('images/mahindra-lmm-logo.png');
                        } elseif ($segment == 'BEV') {
                            $mahindraLogo = asset('images/mahindra-ev-logo.png');
                        } else {
                            $mahindraLogo = asset('images/mahindra-pv-cv-logo.png');
                        }
                    @endphp

                    <div class="row align-items-center">
                        <!-- Left Logo -->
                        <div class="col-2 text-center">
                            <img src="{{ asset('images/bikaner_logo.png') }}" style="height:75px;">
                        </div>

                        <!-- Center Text -->
                        <div class="col-8 text-center">
                            <h3 class="fw-bold mb-1">BIKANER MOTORS PRIVATE LIMITED</h3>
                            <div style="font-size:13px;">Regd. Office : Sunehri Chhabil Mansion, NH-11, Jaipur Road,
                                Bikaner-334022</div>
                            <div style="font-size:13px;">Branch Office : 6th KM Stone, Ratangarh Road, Churu-331001</div>
                            <h4 class="mt-2 mb-0 fw-bold text-uppercase">Vehicle Quotation</h4>
                        </div>

                        <!-- Right Logo -->
                        <div class="col-2 text-center">
                            <img src="{{ $mahindraLogo }}" style="max-width:110px; max-height:60px;">
                        </div>
                    </div>

                </div>
            </div>

            @php
                $formAction = isset($quotation) ? route('sales.quotation.update', $quotation->id) : route('sales.quotation.store');

                $formMethod = isset($quotation) ? 'PUT' : 'POST';

                $quotationData = $quotationData ?? [];

                $isEditMode = isset($quotation);

                $savedInsuranceCompany = $quotationData['insurance_company'] ?? '';

                $savedInsuranceCovers = $quotationData['insurance_covers'] ?? [];

                $savedAccessories = $quotationData['accessories'] ?? [];

                // Determine group selections based on existing data
                $groupASelected = 'cash_scheme_oem';

                if (
                    !empty($quotationData['csd_discount']) &&
                    !in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])
                ) {
                    $groupASelected = 'csd_discount';
                } elseif (
                    !empty($quotationData['fame_subsidy']) &&
                    !in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])
                ) {
                    $groupASelected = 'fame_subsidy';
                } elseif (
                    !empty($quotationData['cash_scheme_oem']) &&
                    !in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])
                ) {
                    $groupASelected = 'cash_scheme_oem';
                }

                // Group B
                $groupBSelected = 'corporate_discount';

                // Group C
                $groupCSelected = 'exchange_bonus';

                if (
                    !empty($quotationData['green_bonus']) &&
                    $quotationData['green_bonus'] != '0' &&
                    $quotationData['green_bonus'] != 'N/A'
                ) {
                    $groupCSelected = 'green_bonus';
                } elseif (
                    !empty($quotationData['welcome_bonus']) &&
                    $quotationData['welcome_bonus'] != '0' &&
                    $quotationData['welcome_bonus'] != 'N/A'
                ) {
                    $groupCSelected = 'welcome_bonus';
                } elseif (
                    !empty($quotationData['loyalty_bonus']) &&
                    $quotationData['loyalty_bonus'] != '0' &&
                    $quotationData['loyalty_bonus'] != 'N/A'
                ) {
                    $groupCSelected = 'loyalty_bonus';
                }
            @endphp

            <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data"
                @if ($viewMode) onsubmit="return false;" @endif>
                <input type="hidden" name="booking_id" value="{{ $bookingId ?? '' }}">
                @csrf
                @if (isset($quotation))
                    @method('PUT')
                @endif

                <div class="quotation-sheet">
                    <div class="form-section">

                        {{-- MOCK ENQUIRY TEST INPUT (NO-PRINT) - Only in create mode --}}
                        @if (!isset($quotation) && !$viewMode)
                            <div class="no-print mb-3 d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">🔍 Mock Enquiry</span>
                                            <input type="text" id="mock_enquiry_no" class="form-control"
                                                placeholder="Enter 001-018" value="019">
                                            <button type="button" id="btnFetchMock" class="btn btn-primary">
                                                <i class="la la-refresh"></i> Fetch
                                            </button>
                                            <button type="button" id="btnResetMock" class="btn btn-secondary">
                                                <i class="la la-undo"></i> Reset
                                            </button>
                                        </div>
                                        <small class="text-muted">Enter enquiry number (001-018) and click Fetch to load
                                            mock
                                            data</small>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ================= Customer Details ================= --}}
                        <table class="bill-table mb-2">
                            <tr>
                                <td class="title" width="18%">Enquiry No.</td>
                                <td width="32%">
                                    @php
                                        /*
                                        |--------------------------------------------------------------------------
                                        | ENQUIRY ID
                                        |--------------------------------------------------------------------------
                                        | Create page:
                                        |   request('id') = enquiry ID
                                        |
                                        | Edit page:
                                        |   request('id') = quotation ID
                                        |
                                        | Therefore, once an enquiry is loaded, ALWAYS use
                                        | $selectedEnquiry->id as the real enquiry ID.
                                        |--------------------------------------------------------------------------
                                        */

                                        $rawEnquiryId = $selectedEnquiry->id ?? '';

                                        $formattedEnquiryNo = $rawEnquiryId
                                            ? 'XENQ-' . $rawEnquiryId
                                            : '';
                                    @endphp

                                    <!-- Display Enquiry Number -->
                                    <input
                                        type="text"
                                        id="enquiry_id"
                                        value="{{ $formattedEnquiryNo }}"
                                        readonly
                                    >

                                    <!-- IMPORTANT: Submit ENQUIRY ID, NOT QUOTATION ID -->
                                    <input type="hidden"
                                        name="enquiry_id"
                                        id="enquiry_id_hidden"
                                        value="{{ $selectedEnquiry->id ?? '' }}">
                                </td>
                                <td class="title" width="18%">Customer Name</td>
                                <td width="32%">
                                    @php
    $displayCustomerName = old('customer_name');

    if (empty($displayCustomerName) && $selectedEnquiry) {
        $displayCustomerName = trim(
            ($selectedEnquiry->first_name ?? '') . ' ' .
            ($selectedEnquiry->last_name ?? '')
        );
    }

    if (empty($displayCustomerName) && $selectedEnquiry) {
        $displayCustomerName = trim((string) ($selectedEnquiry->full_name ?? ''));
    }

    if (empty($displayCustomerName) && $selectedEnquiry) {
        $displayCustomerName = trim((string) ($selectedEnquiry->customer_name ?? ''));
    }

    if (empty($displayCustomerName) && $selectedEnquiry) {
        $displayCustomerName = trim((string) ($selectedEnquiry->name ?? ''));
    }

    // IMPORTANT: quotation saved data fallback
    if (empty($displayCustomerName)) {
        $displayCustomerName = trim(
            (string) (
                $quotationData['customer_name']
                ?? $quotationData['customerName']
                ?? ''
            )
        );
    }
@endphp

<input type="text"
    id="customer_name"
    value="{{ $displayCustomerName }}"
    readonly>

<input type="hidden"
    name="customer_name"
    id="customer_name_hidden"
    value="{{ $displayCustomerName }}">
                                </td>
                            </tr>
                            <tr>
                                <td class="title">Mobile No.</td>
                                <td>
                                    <input type="text" id="mobile"
                                        value="{{ old('mobile', $selectedEnquiry->mobile ?? ($selectedEnquiry->phone ?? '')) }}"
                                        readonly>
                                    <input type="hidden" name="mobile" id="mobile_hidden"
                                        value="{{ old('mobile', $selectedEnquiry->mobile ?? ($selectedEnquiry->phone ?? '')) }}">
                                </td>

                                <!-- CARE OF LABEL CELL -->
                                <td class="title cell-label" style="width: 18%; padding: 0 !important;">
                                    <!-- Screen par sirf Dropdown dikhega -->
                                    <div class="no-print" style="width: 100%; height: 100%;">
                                        <select name="careof" id="careof" class="group-select">
                                            <option value="">Care Of</option>
                                            <option value="1"
                                                {{ old('careof', $quotationData['careof'] ?? '') == '1' ? 'selected' : '' }}>
                                                Son of</option>
                                            <option value="2"
                                                {{ old('careof', $quotationData['careof'] ?? '') == '2' ? 'selected' : '' }}>
                                                Daughter of</option>
                                            <option value="3"
                                                {{ old('careof', $quotationData['careof'] ?? '') == '3' ? 'selected' : '' }}>
                                                Married to</option>
                                            <option value="4"
                                                {{ old('careof', $quotationData['careof'] ?? '') == '4' ? 'selected' : '' }}>
                                                Guardian Name</option>
                                        </select>
                                    </div>
                                    <!-- Print ke waqt sirf plain text dikhega -->
                                    <span id="careof_relation_print" class="print-only-inline"
                                        style="padding-left: 5px;">Care Of</span>
                                </td>

                                <!-- CARE OF VALUE CELL -->
                                <!-- CARE OF VALUE CELL -->
                                <td>
                                    <!-- Screen par input box -->
                                    <div class="no-print">
                                        <input type="text" name="careofname" id="careofname" placeholder="Enter Name"
                                            style="width: 100%; font-size: 10px; padding: 2px;"
                                            value="{{ old('careofname', $quotationData['careofname'] ?? '') }}">
                                    </div>
                                    <!-- Print ke waqt sirf Customer/Relative ka Name (Same Weight & Padding) -->
                                    <span id="careof_name_print" class="print-only-inline"
                                        style="padding-left: 2px; font-weight: 600; color: #000;"></span>
                                </td>
                            </tr>
                            @php
                                $segmentCode = $selectedEnquiry?->segment_code ?? '';
                                $modelCode = $selectedEnquiry?->model_code ?? '';
                                $variantCode = $selectedEnquiry?->variant_code ?? '';
                                $colorCode = $selectedEnquiry?->color_code ?? '';
                            @endphp

                            <!-- Isko ensure karo ki yeh values enquiry se aa rahi hain -->
                            <tr>
                                <td class="title">Segment</td>
                                <td>
                                    <input type="text" id="segment" value="{{ $segmentName }}" readonly>

                                    <input type="hidden" name="segment_code" id="segment_code"
                                        value="{{ $segmentCode }}">
                                </td>

                                <td class="title">Model</td>
                                <td>
                                    <input type="text" id="model" value="{{ $modelName }}" readonly>

                                    <input type="hidden" name="model_code" id="model_code"
                                        value="{{ $modelCode }}">
                                </td>
                            </tr>

                            <tr>
                                <td class="title">Variant</td>
                                <td>
                                    <input type="text" id="variant" value="{{ $variantName }}" readonly>

                                    <input type="hidden" name="variant_code" id="variant_code"
                                        value="{{ $variantCode }}">
                                </td>

                                <td class="title">Color</td>
                                <td>
                                    <input type="text" id="color" value="{{ $colorName }}" readonly>

                                    <input type="hidden" name="color_code" id="color_code"
                                        value="{{ $colorCode }}">
                                </td>
                            </tr>
                            <tr>
                                <td class="title">Permit</td>
                                <td>
                                    <select name="permit" id="permit">
                                        <option value="">Select Permit</option>

                                        @foreach ($permit_map as $key => $value)
                                            <option value="{{ $key }}"
                                                {{ old('permit', $quotationData['permit'] ?? '') == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- <td class="title">OEM Code</td>
                            <td>
                                <input type="text" id="oem_code"
                                    value="{{ old('oem_code', $quotationData['oem_code'] ?? optional($selectedEnquiry)->oem_code ?? '') }}"
                                    readonly>
                                <input type="hidden" name="oem_code" id="oem_code_hidden"
                                    value="{{ old('oem_code', $quotationData['oem_code'] ?? optional($selectedEnquiry)->oem_code ?? '') }}">
                            </td> --}}
                                <td class="title">Financier</td>
                                <td>
                                    @if ($revisionPdf ?? false)
                                        <span style="color: #000 !important; font-weight: 400; font-size: 10px;">
                                            {{ $quotationData['financier_display'] ?? '-' }}
                                        </span>
                                    @else
                                        <select name="financier" id="financier">
                                            <option value="">Select Financier</option>

                                            @foreach ($financiers ?? [] as $financier)
                                                <option value="{{ $financier->id }}"
                                                    data-shortname="{{ $financier->short_name ?? '' }}"
                                                    {{ old('financier', $quotationData['financier'] ?? (optional($selectedEnquiry)->financier ?? '')) ==
                                                    $financier->id
                                                        ? 'selected'
                                                        : '' }}>
                                                    {{ $financier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        </table>

                        <div class="quotation-box">
                            <div class="quotation-grid-split">

                                {{-- ================= PRICE DETAILS BOX ================= --}}
                                <div class="quotation-grid-col">
                                    <table class="quotation-grid price-grid">
                                        <thead>
                                            <tr>
                                                <th>PRICE DETAILS</th>
                                                <th>OPTION</th>
                                                <th>AMOUNT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="grid-row">
                                                <td class="cell-label">Ex-Showroom Price</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input name="ex_showroom_price" id="ex_showroom_price"
                                                        class="numeric-only"
                                                        value="{{ old('ex_showroom_price', $quotationData['ex_showroom_price'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Insurance</td>
                                                <td class="cell-option">
                                                    <select id="insurance_company" name="insurance_company"
                                                        class="form-control form-select"
                                                        style="margin-bottom:2px !important;">
                                                        <option value="">Select Company</option>
                                                    </select>
                                                    <select id="insurance_covers" name="insurance_covers[]" class="form-control" multiple style="height:auto; min-height:30px;"></select>
                                                    <input type="hidden" id="insurance_covers_data" name="insurance_covers_data" value="">
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" id="insurance_amount" name="insurance_amount"
                                                        class="numeric-only" placeholder="0.00" readonly
                                                        value="{{ old('insurance_amount', $quotationData['insurance_amount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">
                                                    Registration
                                                    <span id="registration_details_print"
                                                        class="print-only-inline fw-bold ms-1"></span>
                                                </td>
                                                <td class="cell-option" style="padding: 2px 4px !important;">
                                                    <div
                                                        style="display: flex; gap: 4px; align-items: center; justify-content: space-between; width: 100%;">
                                                        <div style="flex: 1 1 38%;">
                                                            <select name="registration_no_type" id="registration_no_type"
                                                                class="form-select form-select-sm"
                                                                style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;">
                                                                <option value="">Select Type</option>
                                                                @foreach ($reg_no_type_map ?? [
            '1' => 'Regular',
            '2' => 'BH
                                                                                                                                Series',
            '3' => 'Special Number',
        ] as $key => $value)
                                                                    <option value="{{ $key }}"
                                                                        {{ old('registration_no_type', $quotationData['registration_no_type'] ?? '') == $key ? 'selected' : '' }}>
                                                                        {{ $value }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div style="flex: 1 1 38%;">
                                                            <select name="registration_category"
                                                                id="registration_category"
                                                                class="form-select form-select-sm"
                                                                style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;">
                                                                <option value="">Select Category</option>
                                                                @foreach ($registration_type_map as $key => $value)
                                                                    <option value="{{ $key }}"
                                                                        {{ old('registration_category', $quotationData['registration_category'] ?? '') == $key ? 'selected' : '' }}>
                                                                        {{ $value }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="no-print"
                                                            style="flex: 0 0 auto; display: flex; align-items: center; border: 1px solid #ccc; border-radius: 3px; padding: 1px; background: #fff; height: 22px;">
                                                            <span
                                                                style="font-size: 8px; font-weight: bold; margin-right: 3px; margin-left: 2px; color: #555; white-space: nowrap;">In-House:</span>
                                                            <label
                                                                style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                                <input type="radio" name="in_house_rto" value="1"
                                                                    {{ old('in_house_rto', $quotationData['in_house_rto'] ?? '0') == '1' ? 'checked' : '' }}
                                                                    {{ $viewMode ? 'disabled' : '' }}>
                                                                Yes
                                                            </label>
                                                            <label
                                                                style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                                <input type="radio" name="in_house_rto" value="0"
                                                                    {{ old('in_house_rto', $quotationData['in_house_rto'] ?? '0') == '0' ? 'checked' : '' }}
                                                                    {{ $viewMode ? 'disabled' : '' }}>
                                                                No
                                                            </label>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" id="registration_amount"
                                                        name="registration_amount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('registration_amount', $quotationData['registration_amount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Accessories</td>

                                                <td class="cell-option">
                                                    <select name="accessories[]" id="accessories" multiple>
                                                        @php
                                                            $selectedAccessories = old(
                                                                'accessories',
                                                                $quotationData['accessories'] ?? [],
                                                            );
                                                        @endphp

                                                        @foreach ($accessoryList as $accessory)
                                                            @php
                                                                $formattedItemName = ucwords(
                                                                    strtolower($accessory->item),
                                                                );
                                                            @endphp
                                                            <option value="{{ $accessory->part_no }}"
                                                                data-price="{{ $accessory->ndp }}"
                                                                {{ in_array($accessory->part_no, (array) $selectedAccessories) ? 'selected' : '' }}>
                                                                {{ $formattedItemName }}
                                                                (₹{{ number_format($accessory->ndp, 2) }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                <td class="cell-amount">
                                                    <input id="accessories_amount" name="accessories_amount"
                                                        class="numeric-only" readonly
                                                        value="{{ old('accessories_amount', $quotationData['accessories_amount'] ?? '0.00') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Maxicare</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="maxicare" name="maxicare" class="numeric-only"
                                                        value="{{ old('maxicare', $quotationData['maxicare'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">VLTD Device (GPS)</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="vltd_device" name="vltd_device" class="numeric-only"
                                                        value="{{ old('vltd_device', $quotationData['vltd_device'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Coating</td>
                                                <td class="cell-option">
                                                    <select id="coating" name="coating">
                                                        <option value=""
                                                            {{ old('coating', $quotationData['coating'] ?? '') == '' ? 'selected' : '' }}>
                                                            Please Select...</option>
                                                        <option value="Ceramic"
                                                            {{ old('coating', $quotationData['coating'] ?? '') == 'Ceramic' ? 'selected' : '' }}>
                                                            Ceramic</option>
                                                        <option value="Graphene"
                                                            {{ old('coating', $quotationData['coating'] ?? '') == 'Graphene' ? 'selected' : '' }}>
                                                            Graphene</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input id="coating_price" name="coating_price" class="numeric-only"
                                                        value="{{ old('coating_price', $quotationData['coating_price'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">PPF</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="ppf" name="ppf" class="numeric-only"
                                                        value="{{ old('ppf', $quotationData['ppf'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">RTO Yellow Tape</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="rto_yellow_tape" name="rto_yellow_tape"
                                                        class="numeric-only"
                                                        value="{{ old('rto_yellow_tape', $quotationData['rto_yellow_tape'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Kazam Charging Kit</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="kazam_charging_kit" name="kazam_charging_kit"
                                                        class="numeric-only"
                                                        value="{{ old('kazam_charging_kit', $quotationData['kazam_charging_kit'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Incidental Charges</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="incidental_charges" name="incidental_charges"
                                                        class="numeric-only"
                                                        value="{{ old('incidental_charges', $quotationData['incidental_charges'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Shield</td>
                                                <td class="cell-option">
                                                    <select id="shield" name="shield">
                                                        <option value="4th Year"
                                                            {{ old('shield', $quotationData['shield'] ?? '') == '4th Year' ? 'selected' : '' }}>
                                                            4th Year</option>
                                                        <option value="4th + 5th Year"
                                                            {{ old('shield', $quotationData['shield'] ?? '') == '4th + 5th Year' ? 'selected' : '' }}>
                                                            4th + 5th Year</option>
                                                        <option value="No Shield"
                                                            {{ old('shield', $quotationData['shield'] ?? '') == 'No Shield' ? 'selected' : '' }}>
                                                            No Shield</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input id="shield_price" name="shield_price" class="numeric-only"
                                                        value="{{ old('shield_price', $quotationData['shield_price'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">RSA</td>
                                                <td class="cell-option">
                                                    <select id="rsa" name="rsa">
                                                        <option value="1 Year"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == '1 Year' ? 'selected' : '' }}>
                                                            1 Year</option>
                                                        <option value="2 Year"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == '2 Year' ? 'selected' : '' }}>
                                                            2 Year</option>
                                                        <option value="3 Year"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == '3 Year' ? 'selected' : '' }}>
                                                            3 Year</option>
                                                        <option value="4 Year"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == '4 Year' ? 'selected' : '' }}>
                                                            4 Year</option>
                                                        <option value="5 Year"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == '5 Year' ? 'selected' : '' }}>
                                                            5 Year</option>
                                                        <option value="No RSA"
                                                            {{ old('rsa', $quotationData['rsa'] ?? '') == 'No RSA' ? 'selected' : '' }}>
                                                            No RSA</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input id="rsa_amount" name="rsa_amount" class="numeric-only"
                                                        value="{{ old('rsa_amount', $quotationData['rsa_amount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Fastag</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="fastag" name="fastag" class="numeric-only"
                                                        value="{{ old('fastag', $quotationData['fastag'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">COD Charges</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="cod_charges" name="cod_charges" class="numeric-only"
                                                        value="{{ old('cod_charges', $quotationData['cod_charges'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Charger Swapping</td>
                                                <td class="cell-option">
                                                    <select id="charger_swapping" name="charger_swapping">
                                                        <option value="N/A"
                                                            {{ old('charger_swapping', $quotationData['charger_swapping'] ?? '') == 'N/A' ? 'selected' : '' }}>
                                                            N/A</option>
                                                        <option value="NCH to 7.2 kW"
                                                            {{ old('charger_swapping', $quotationData['charger_swapping'] ?? '') == 'NCH to 7.2 kW' ? 'selected' : '' }}>
                                                            NCH to 7.2 kW</option>
                                                        <option value="NCH to 11.2 kW"
                                                            {{ old('charger_swapping', $quotationData['charger_swapping'] ?? '') == 'NCH to 11.2 kW' ? 'selected' : '' }}>
                                                            NCH to 11.2 kW</option>
                                                        <option value="7.2 kW to 11.2 kW"
                                                            {{ old('charger_swapping', $quotationData['charger_swapping'] ?? '') == '7.2 kW to 11.2 kW' ? 'selected' : '' }}>
                                                            7.2 kW to 11.2 kW</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input id="charger_swapping_amount" name="charger_swapping_amount"
                                                        class="numeric-only"
                                                        value="{{ old('charger_swapping_amount', $quotationData['charger_swapping_amount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row tcs-row">
                                                <td class="cell-label">TCS @1%</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="tcs" name="tcs" class="numeric-only" readonly
                                                        value="{{ old('tcs', $quotationData['tcs'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row total-row"
                                                style="background: #f2f2f2; font-weight: bold;">
                                                <td class="cell-label"
                                                    style="text-align: center; font-weight: bold; font-size: 11px; text-align:left;">
                                                    TOTAL
                                                    RECEIVABLE</td>
                                                <td class="cell-option"></td>
                                                <td class="cell-amount">
                                                    <input id="total_receivable" name="total_receivable" readonly
                                                        style="font-weight: bold; font-size: 11px; text-align: right;"
                                                        value="{{ old('total_receivable', $quotationData['total_receivable'] ?? '') }}">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- ================= DISCOUNT DETAILS BOX ================= --}}
                                <div class="quotation-grid-col">
                                    <table class="quotation-grid discount-grid">
                                        <thead>
                                            <tr>
                                                <th>DISCOUNT DETAILS</th>
                                                <th>TYPE</th>
                                                <th>AMOUNT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Group A: Cash Scheme OEM, CSD Discount, Fame Subsidy --}}
                                            <tr class="grid-row">
                                                <td class="cell-label" style="font-weight:500 !important;">
                                                    <select id="group_a_select" class="group-select">
                                                        <option value="cash_scheme_oem"
                                                            {{ old('group_a_select', $groupASelected) == 'cash_scheme_oem' ? 'selected' : '' }}>
                                                            Cash
                                                            Scheme OEM</option>
                                                        <option value="csd_discount"
                                                            {{ old('group_a_select', $groupASelected) == 'csd_discount' ? 'selected' : '' }}>
                                                            CSD
                                                            Discount</option>
                                                        <option value="fame_subsidy" id="fame_subsidy_option"
                                                            {{ old('group_a_select', $groupASelected) == 'fame_subsidy' ? 'selected' : '' }}>
                                                            Fame Subsidy (LMM)</option>
                                                    </select>
                                                </td>
                                                <td class="cell-type">
                                                    @php
                                                        $selectedGroupAType = 'INV';
                                                        if ($groupASelected === 'cash_scheme_oem') {
                                                            $selectedGroupAType =
                                                                $quotationData['cash_scheme_oem_type'] ?? 'INV';
                                                        } elseif ($groupASelected === 'csd_discount') {
                                                            $selectedGroupAType =
                                                                $quotationData['csd_discount_type'] ?? 'INV';
                                                        } elseif ($groupASelected === 'fame_subsidy') {
                                                            $selectedGroupAType =
                                                                $quotationData['fame_subsidy_type'] ?? 'INV';
                                                        }
                                                    @endphp
                                                    <select id="group_a_type">
                                                        <option value="INV_OE"
                                                            {{ old('group_a_type', $selectedGroupAType) == 'INV_OE' ? 'selected' : '' }}>
                                                            Inv Disc.
                                                            (OE)</option>
                                                        <option value="CN1"
                                                            {{ old('group_a_type', $selectedGroupAType) == 'CN1' ? 'selected' : '' }}>
                                                            CN1</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" id="group_a_amount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('group_a_amount', $quotationData[$groupASelected] ?? '') }}">
                                                    <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem"
                                                        value="{{ old('cash_scheme_oem', $quotationData['cash_scheme_oem'] ?? '') }}">
                                                    <input type="hidden" id="cash_scheme_oem_type"
                                                        name="cash_scheme_oem_type"
                                                        value="{{ old('cash_scheme_oem_type', $quotationData['cash_scheme_oem_type'] ?? '') }}">
                                                    <input type="hidden" id="csd_discount" name="csd_discount"
                                                        value="{{ old('csd_discount', $quotationData['csd_discount'] ?? '') }}">
                                                    <input type="hidden" id="csd_discount_type" name="csd_discount_type"
                                                        value="{{ old('csd_discount_type', $quotationData['csd_discount_type'] ?? '') }}">
                                                    <input type="hidden" id="fame_subsidy" name="fame_subsidy"
                                                        value="{{ old('fame_subsidy', $quotationData['fame_subsidy'] ?? '') }}">
                                                    <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type"
                                                        value="{{ old('fame_subsidy_type', $quotationData['fame_subsidy_type'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Cash Scheme Dealer</td>
                                                <td class="cell-type">
                                                    <select id="dealer_discount_type" name="dealer_discount_type">
                                                        <option value="INV"
                                                            {{ old('dealer_discount_type', $quotationData['dealer_discount_type'] ?? '') == 'INV' ? 'selected' : '' }}>
                                                            INV</option>
                                                        <option value="CN"
                                                            {{ old('dealer_discount_type', $quotationData['dealer_discount_type'] ?? '') == 'CN' ? 'selected' : '' }}>
                                                            CN</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="dealer_discount" id="dealer_discount"
                                                        class="numeric-only" placeholder="0.00"
                                                        value="{{ old('dealer_discount', $quotationData['dealer_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Accessories Scheme</td>
                                                <td class="cell-type">
                                                    <select id="accessories_discount_type"
                                                        name="accessories_discount_type">
                                                        <option value="INV"
                                                            {{ old('accessories_discount_type', $quotationData['accessories_discount_type'] ?? '') == 'INV'
                                                                ? 'selected'
                                                                : '' }}>
                                                            INV</option>
                                                        <option value="CN"
                                                            {{ old('accessories_discount_type', $quotationData['accessories_discount_type'] ?? '') == 'CN'
                                                                ? 'selected'
                                                                : '' }}>
                                                            CN</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="accessories_discount"
                                                        id="accessories_discount" class="numeric-only" placeholder="0.00"
                                                        value="{{ old('accessories_discount', $quotationData['accessories_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Shield Scheme</td>
                                                <td class="cell-type">
                                                    <select id="shield_scheme_type" name="shield_scheme_type">
                                                        <option value="INV"
                                                            {{ old('shield_scheme_type', $quotationData['shield_scheme_type'] ?? '') == 'INV' ? 'selected' : '' }}>
                                                            INV</option>
                                                        <option value="CN"
                                                            {{ old('shield_scheme_type', $quotationData['shield_scheme_type'] ?? '') == 'CN' ? 'selected' : '' }}>
                                                            CN</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="shield_scheme" id="shield_scheme"
                                                        class="numeric-only" placeholder="0.00"
                                                        value="{{ old('shield_scheme', $quotationData['shield_scheme'] ?? '') }}">
                                                </td>
                                            </tr>

                                            {{-- Group B: Corporate Discount ONLY (Loyalty Bonus moved to Group C) --}}
                                            <tr class="grid-row">
                                                <td class="cell-label">Corporate Discount</td>
                                                <td class="cell-type">
                                                    <input type="text" id="group_b_type" value="Inv Disc." readonly>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" id="group_b_amount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('group_b_amount', $quotationData['corporate_discount'] ?? '') }}">
                                                    <input type="hidden" id="corporate_discount"
                                                        name="corporate_discount"
                                                        value="{{ old('corporate_discount', $quotationData['corporate_discount'] ?? '') }}">
                                                    <input type="hidden" id="corporate_discount_type"
                                                        name="corporate_discount_type"
                                                        value="{{ old('corporate_discount_type', $quotationData['corporate_discount_type'] ?? 'INV') }}">
                                                </td>
                                            </tr>

                                            {{-- Group C: Exchange Bonus, Green Bonus, Welcome Bonus, Loyalty Bonus --}}
                                            <tr class="grid-row">
                                                <td class="cell-label">
                                                    <select id="group_c_select" class="group-select">
                                                        <option value="exchange_bonus"
                                                            {{ old('group_c_select', $groupCSelected) == 'exchange_bonus' ? 'selected' : '' }}>
                                                            Exchange
                                                            Bonus</option>
                                                        <option value="green_bonus"
                                                            {{ old('group_c_select', $groupCSelected) == 'green_bonus' ? 'selected' : '' }}>
                                                            Green Bonus
                                                        </option>
                                                        <option value="welcome_bonus"
                                                            {{ old('group_c_select', $groupCSelected) == 'welcome_bonus' ? 'selected' : '' }}>
                                                            Welcome
                                                            Bonus</option>
                                                        <option value="loyalty_bonus"
                                                            {{ old('group_c_select', $groupCSelected) == 'loyalty_bonus' ? 'selected' : '' }}>
                                                            Loyalty
                                                            Bonus</option>
                                                    </select>
                                                </td>
                                                <td class="cell-type">
                                                    <input type="text" id="group_c_type" value="CN2" readonly>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" id="group_c_amount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('group_c_amount', $quotationData[$groupCSelected] ?? '') }}">
                                                    <input type="hidden" id="exchange_bonus" name="exchange_bonus"
                                                        value="{{ old('exchange_bonus', $quotationData['exchange_bonus'] ?? '') }}">
                                                    <input type="hidden" id="exchange_bonus_type"
                                                        name="exchange_bonus_type"
                                                        value="{{ old('exchange_bonus_type', $quotationData['exchange_bonus_type'] ?? 'CN1') }}">
                                                    <input type="hidden" id="green_bonus" name="green_bonus"
                                                        value="{{ old('green_bonus', $quotationData['green_bonus'] ?? '') }}">
                                                    <input type="hidden" id="green_bonus_type" name="green_bonus_type"
                                                        value="{{ old('green_bonus_type', $quotationData['green_bonus_type'] ?? 'CN1') }}">
                                                    <input type="hidden" id="welcome_bonus" name="welcome_bonus"
                                                        value="{{ old('welcome_bonus', $quotationData['welcome_bonus'] ?? '') }}">
                                                    <input type="hidden" id="welcome_bonus_type"
                                                        name="welcome_bonus_type"
                                                        value="{{ old('welcome_bonus_type', $quotationData['welcome_bonus_type'] ?? 'CN1') }}">
                                                    <input type="hidden" id="loyalty_bonus" name="loyalty_bonus"
                                                        value="{{ old('loyalty_bonus', $quotationData['loyalty_bonus'] ?? '') }}">
                                                    <input type="hidden" id="loyalty_bonus_type"
                                                        name="loyalty_bonus_type"
                                                        value="{{ old('loyalty_bonus_type', $quotationData['loyalty_bonus_type'] ?? 'CN1') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Accessories Special Discount</td>
                                                <td class="cell-type">
                                                    <select id="accessories_spl_disc_type"
                                                        name="accessories_spl_disc_type">
                                                        <option value="INV_D"
                                                            {{ old('accessories_spl_disc_type', $quotationData['accessories_spl_disc_type'] ?? '') == 'INV_D'
                                                                ? 'selected'
                                                                : '' }}>
                                                            Inv Disc. (D)</option>
                                                        <option value="CN1"
                                                            {{ old('accessories_spl_disc_type', $quotationData['accessories_spl_disc_type'] ?? '') == 'CN1'
                                                                ? 'selected'
                                                                : '' }}>
                                                            CN1</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="accessories_spl_disc"
                                                        id="accessories_spl_disc" class="numeric-only" placeholder="0.00"
                                                        value="{{ old('accessories_spl_disc', $quotationData['accessories_spl_disc'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label" id="coating_discount_label">Coating Special
                                                    Discount
                                                </td>
                                                <td class="cell-type">
                                                    <select id="ceramic_discount_type" name="ceramic_discount_type">
                                                        <option value="INV_D"
                                                            {{ old('ceramic_discount_type', $quotationData['ceramic_discount_type'] ?? '') == 'INV_D' ? 'selected' : '' }}>
                                                            Inv Disc. (D)</option>
                                                        <option value="CN1"
                                                            {{ old('ceramic_discount_type', $quotationData['ceramic_discount_type'] ?? '') == 'CN1' ? 'selected' : '' }}>
                                                            CN1</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="ceramic_discount" id="ceramic_discount"
                                                        class="numeric-only" placeholder="0.00"
                                                        value="{{ old('ceramic_discount', $quotationData['ceramic_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">PPF Special Discount</td>
                                                <td class="cell-type">
                                                    <select id="ppf_discount_type" name="ppf_discount_type">
                                                        <option value="INV_D"
                                                            {{ old('ppf_discount_type', $quotationData['ppf_discount_type'] ?? '') == 'INV_D' ? 'selected' : '' }}>
                                                            Inv Disc. (D)</option>
                                                        <option value="CN1"
                                                            {{ old('ppf_discount_type', $quotationData['ppf_discount_type'] ?? '') == 'CN1' ? 'selected' : '' }}>
                                                            CN1</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="ppf_discount" id="ppf_discount"
                                                        class="numeric-only" placeholder="0.00"
                                                        value="{{ old('ppf_discount', $quotationData['ppf_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label" id="charger_discount_title"
                                                    style="white-space: nowrap;">
                                                    <span id="charger_discount_label" class="group-select"
                                                        style="margin-left: 5px;">
                                                        Charger Swapping Discount
                                                    </span>

                                                    <select id="charger_swapping_option" name="charger_swapping_option"
                                                        class="group-select"
                                                        style="display: inline-block;
                                                        width: 42% !important;
                                                        min-width: 0;
                                                        margin-left: 5px;
                                                        vertical-align: middle;">
                                                        <option value="7.2 kW to NCH"
                                                            {{ old('charger_swapping_option', $quotationData['charger_swapping_option'] ?? '') == '7.2 kW to NCH'
                                                                ? 'selected'
                                                                : '' }}>
                                                            7.2 kW to NCH
                                                        </option>
                                                        <option value="11.2 kW to NCH"
                                                            {{ old('charger_swapping_option', $quotationData['charger_swapping_option'] ?? '') == '11.2 kW to NCH'
                                                                ? 'selected'
                                                                : '' }}>
                                                            11.2 kW to NCH
                                                        </option>
                                                        <option value="11.2 kW to 7.2 kW"
                                                            {{ old('charger_swapping_option', $quotationData['charger_swapping_option'] ?? '') == '11.2 kW to 7.2 kW'
                                                                ? 'selected'
                                                                : '' }}>
                                                            11.2 kW to 7.2 kW
                                                        </option>
                                                    </select>

                                                    <span id="charger_swapping_option_print"
                                                        class="print-only-inline"></span>
                                                </td>

                                                <td class="cell-type">
                                                    <input type="text" id="charger_swapping_discount_type"
                                                        name="charger_swapping_discount_type" value="CN3" readonly
                                                        disabled>
                                                </td>

                                                <td class="cell-amount" id="charger_discount_cell">
                                                    <input type="text" id="charger_swapping_discount"
                                                        name="charger_swapping_discount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('charger_swapping_discount', $quotationData['charger_swapping_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Other Cash Discount</td>
                                                <td class="cell-type">
                                                    <select id="other_cash_discount_type" name="other_cash_discount_type">
                                                        <option value="INV_D"
                                                            {{ old('other_cash_discount_type', $quotationData['other_cash_discount_type'] ?? '') == 'INV_D'
                                                                ? 'selected'
                                                                : '' }}>
                                                            Inv Disc. (D)</option>
                                                        <option value="CN1"
                                                            {{ old('other_cash_discount_type', $quotationData['other_cash_discount_type'] ?? '') == 'CN1' ? 'selected' : '' }}>
                                                            CN1</option>
                                                    </select>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="other_cash_discount"
                                                        id="other_cash_discount" class="numeric-only" placeholder="0.00"
                                                        value="{{ old('other_cash_discount', $quotationData['other_cash_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row">
                                                <td class="cell-label">Special Cash Discount</td>
                                                <td class="cell-type">
                                                    <input type="text" id="special_cash_discount_type"
                                                        name="special_cash_discount_type" value="Inv Disc. (D)" readonly>
                                                </td>
                                                <td class="cell-amount">
                                                    <input type="text" name="special_cash_discount"
                                                        id="special_cash_discount" class="numeric-only"
                                                        placeholder="0.00"
                                                        value="{{ old('special_cash_discount', $quotationData['special_cash_discount'] ?? '') }}">
                                                </td>
                                            </tr>

                                            <tr class="grid-row total-row"
                                                style="background: #f2f2f2; font-weight: bold;">
                                                <td class="cell-label"
                                                    style="text-align: center; font-weight: bold; font-size: 11px; text-align:left;">
                                                    TOTAL
                                                    DISCOUNT</td>
                                                <td class="cell-type"></td>
                                                <td class="cell-amount">
                                                    <input id="total_discount_amount" readonly
                                                        style="font-weight: bold; font-size: 11px; text-align: right;"
                                                        value="{{ old('total_discount_amount', $quotationData['total_discount'] ?? '') }}">
                                                    <input type="hidden" id="total_discount" name="total_discount"
                                                        value="{{ old('total_discount', $quotationData['total_discount'] ?? '') }}">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="invoice-amount-box"
                                        style="width:100%; margin-top:3px; border:1px solid #000;">

                                        <table class="quotation-grid"
                                            style="width:100%; border-collapse:collapse; table-layout:fixed;">

                                            <tbody>
                                                <tr class="grid-row total-row"
                                                    style="background:#f2f2f2; font-weight:bold;">

                                                    <td class="cell-label"
                                                        style="width:50%; text-align:left; font-weight:bold; font-size:11px;">
                                                        INVOICE AMOUNT
                                                    </td>

                                                    <td class="cell-amount" style="width:50%;">
                                                        <input id="invoice_amount_display" readonly
                                                            style="font-weight:bold; font-size:11px; text-align:right;"
                                                            value="{{ old('invoice_amount', $quotationData['invoice_amount'] ?? '') }}">

                                                        <input type="hidden" id="invoice_amount" name="invoice_amount"
                                                            value="{{ old('invoice_amount', $quotationData['invoice_amount'] ?? '') }}">
                                                    </td>

                                                </tr>
                                            </tbody>

                                        </table>
                                    </div>



                                    {{-- ================= DISCOUNT BIFURCATION BOX ================= --}}
                                    <div class="discount-bifurcation-box"
                                        style="margin-top: 3px; border: 1px solid #000; width: 100%;">
                                        <table class="quotation-grid bifurcation-grid"
                                            style="width:100%; border-collapse: collapse; table-layout: fixed;">
                                            <thead>
                                                <tr>
                                                    <th
                                                        style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        DISCOUNT BIFURCATION</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        Inv Disc.</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        Inv Disc. (OE)</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        Inv Disc. (D)</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        CN1</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        CN2</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        CN3</th>
                                                    <th
                                                        style="width:12%; background: #d9d9d9; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        TOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td
                                                        style="background:#f2f2f2; border:1px solid #000; padding:3px 2px; font-size:9px; font-weight:bold; text-align:center;">
                                                        AMOUNT</td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="inv_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="inv_oe_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="inv_d_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="cn1_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="cn2_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td style="border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="cn3_discount_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                    <td
                                                        style="background:#f2f2f2; border:1px solid #000; padding:3px 2px; text-align:right;">
                                                        <input id="total_discount_bifurcation_display" readonly
                                                            style="font-weight:bold; font-size:10px; text-align:center; width:100%; border:none; background:transparent;">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Hidden inputs for form submission --}}
                                    <input type="hidden" id="invoiced_discount_summary"
                                        name="invoiced_discount_summary">
                                    <input type="hidden" id="inv_oe_discount_summary" name="inv_oe_discount_summary">
                                    <input type="hidden" id="inv_d_discount_summary" name="inv_d_discount_summary">
                                    <input type="hidden" id="credit_note_discount_summary"
                                        name="credit_note_discount_summary">
                                    <input type="hidden" id="cn1_discount_summary" name="cn1_discount_summary">
                                    <input type="hidden" id="cn2_discount_summary" name="cn2_discount_summary">
                                    <input type="hidden" id="cn3_discount_summary" name="cn3_discount_summary">
                                </div>
                            </div>

                            <!-- UNIFIED NET RECEIVABLE & AMOUNT IN WORDS BOX -->
                            <div class="net-receivable-container mt-2" style="border: 2px solid #000; width: 100%;">
                                <!-- Top Bar: NET RECEIVABLE & AMOUNT -->
                                <div
                                    style="display: flex; width: 100%; border-bottom: 2px solid #000; background: #f2f2f2;">
                                    <div
                                        style="flex: 0 0 50%; font-size: 13px; font-weight: bold; padding: 6px 10px;  color: #000;">
                                        NET RECEIVABLE AMOUNT (In Figures) :
                                    </div>
                                    <div
                                        style="flex: 0 0 50%; padding: 6px 10px; display: flex; justify-content: flex-end; align-items: center;">
                                        <input id="net_receivable_summary" name="net_receivable_summary" readonly
                                            style="font-weight: bold; font-size: 14px; text-align: right; background: transparent; border: none; width: 100%; color: #000; outline: none; padding: 0;"
                                            value="{{ old('net_receivable_summary', $quotationData['net_receivable_summary'] ?? '') }}">
                                    </div>
                                </div>

                                <!-- Bottom Bar: AMOUNT IN WORDS -->
                                <div
                                    style="
                                width: 100%;
                                padding: 5px 10px;
                                font-size: 12px;
                                background: #fff;
                                color: #000;
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                            ">
                                    <strong style="font-weight: bold; font-size: 12px;">
                                        Amount In Words :
                                    </strong>

                                    <span id="net_receivable_words"
                                        style="font-weight: bold; font-style: italic; text-align: right;">
                                        Zero Rupees Only
                                    </span>
                                </div>
                            </div>
                        </div>

                        @php
                            $insuranceNoteText = '';
                            $insuranceCovers = $quotationData['insurance_covers'] ?? [];

                            if (!empty($insuranceCovers) && is_array($insuranceCovers)) {
                                $insuranceNoteText = collect($insuranceCovers)
                                    ->map(function ($cover) {
                                        $name = $cover['name'] ?? '';
                                        if (is_string($cover)) {
                                            $name = $cover;
                                            $price = 0;
                                            if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                                                $price = floatval(str_replace(',', '', $matches[1]));
                                                $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                                            }
                                            return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                                        }
                                        $price = (float) ($cover['price'] ?? 0);
                                        return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                                    })
                                    ->filter()
                                    ->implode(', ');
                            }

                            if (
                                empty($insuranceNoteText) &&
                                !empty($quotationData['insurance_amount'] ?? '') &&
                                ($quotationData['insurance_amount'] ?? '0') != '0' &&
                                ($quotationData['insurance_amount'] ?? '0.00') != '0.00'
                            ) {
                                $insurancePolicyLabel = $insurance_type_map[$quotationData['policy_type'] ?? ''] ?? '';
                                $insuranceNoteText = trim(
                                    $insurancePolicyLabel .
                                        ' (₹' .
                                        number_format((float) ($quotationData['insurance_amount'] ?? 0), 2) .
                                        ')',
                                );
                            }
                        @endphp
                        <div class="insurance-note-row">
                            Insurance:
                            <span id="insurance_print"
                                style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">{{ $insuranceNoteText ?: '' }}&nbsp;</span>
                        </div>

                        {{-- ================= Accessories (shown only while printing) ================= --}}
                        <div class="accessories-note-row">
                            Accessories:
                            <span id="accessories_print"
                                style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">&nbsp;</span>
                        </div>

                        {{-- <table class="bill-table note-box flex-grow-1">
                        <tr>
                            <td>
                                <div style="font-weight:bold; font-size:8px; margin-bottom:3px;">NOTE:</div>
                                <ol id="quotation_notes_list"
                                    style="font-size:8px; font-weight:bold; line-height:1.4; text-align:justify; margin:0; padding-left:12px;">
                                    <li>Price quoted is current and subject to change without notice.</li>
                                    <li>The Price ruling at the time of delivery only will be applicable irrespective of
                                        when payment was made.</li>
                                    <li>All specifications, colors and features are subject to change without prior
                                        notice.</li>
                                    <li>TCS @ 1 % Will be collected on full invoice value, if value is equal to or
                                        exceeds INR 10 Lakhs.</li>
                                    <li>Delivery will be against full payment only.</li>
                                    <li>This is not a firm order and no claim for priority can be made on the basis of
                                        proforma invoice.</li>
                                    <li>All disputes shall be subject to Bikaner jurisdiction only.</li>
                                    <li>Booking need to be done with minimum INR 21,000.</li>
                                </ol>
                            </td>
                        </tr>
                    </table> --}}
                        <div class="terms-notes-container mt-1">
                            <div class="terms-notes-header">NOTES:</div>
                            <div class="terms-notes-body" style="font-weight: bold;">
                                <div class="notes-column">
                                    <ol class="notes-list-split">
                                        <li>Price quoted is current and subject to change without notice.</li>
                                        <li>Price ruling at time of delivery will apply irrespective of payment date.</li>
                                        <li>All specifications, colors, and features subject to change without notice.</li>
                                        <li>TCS @ 1% collected on full invoice value if value equals/exceeds INR 10 Lakhs.
                                        </li>
                                        <li>Delivery will be processed against full clearance of payment only.</li>
                                    </ol>
                                </div>
                                <div class="notes-column">
                                    <ol class="notes-list-split" start="6">
                                        <li>This is not a firm order; no priority claim can be made on proforma invoice.
                                        </li>
                                        <li>All disputes shall be subject strictly to Bikaner jurisdiction only.</li>
                                        <li>Booking needs to be completed with a minimum amount of INR 21,000.</li>
                                        <li id="rto_charges_note_item" style="display: none;">RTO Charges are completely
                                            subject to the vehicle's registration category.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-center mt-2 no-print">

                    @if ($viewMode)

                        @if (isset($quotation))
                            <a href="{{ backpack_url('sales/quotation/' . $quotation->id . '/edit') }}"
                            class="btn btn-warning">
                                Edit Quotation
                            </a>
                        @endif

                        <button type="button" class="btn btn-primary" onclick="printQuotation();">
                            Print Quotation
                        </button>

                        <button type="button" class="btn btn-success" onclick="printBankQuotation()">
                            Print Quotation without Credit Note
                        </button>

                        <a href="{{ backpack_url('sales/quotation') }}" class="btn btn-secondary">
                            Back
                        </a>
                    @else
                        <button type="submit" class="btn btn-success">
                            <i class="la la-save"></i>
                            {{ isset($quotation) ? 'Update Quotation' : 'Save Quotation' }}
                        </button>

                        <a href="{{ backpack_url('sales/quotation') }}" class="btn btn-secondary">
                            Cancel
                        </a>
                    @endif

                </div>
            </form>

        </div>
    </div>

@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ============================================================
        // 1. MOCK DATA DEFINITION
        // ============================================================

        const ENQUIRIES = {

            "019": {
                enquiry_no: "ENQ0019",
                customer: {
                    name: "Dr. Ananya Reddy",
                    mobile: "9876500019",
                    careOf: "3",
                    careOfName: "Dr. Suresh Reddy"
                },
                vehicle: {
                    segment_code: "BEV",
                    segment_name: "Electric Vehicle",
                    model_code: "BE6",
                    model_name: "BE6",
                    variant_code: "BM12AH515MB01D00",
                    variant_name: "BE6 One Above B59 R19 C11",
                    color_code: "QK",
                    color_name: "Galaxy Grey",
                    oem_code: "BEV-BE6-ONE-ABOVE-GALAXY-GREY"
                },
                pricingKey: "bev6Premium"
            }
        };
        let enquiryIdFromServer = @json($selectedEnquiry->id ?? '');

        let cleanId = enquiryIdFromServer
            ? String(enquiryIdFromServer)
            : '';

        let enquiryData = null;

        // Mock enquiry support
        if (cleanId && ENQUIRIES[cleanId]) {
            enquiryData = ENQUIRIES[cleanId];
        }

        // Always display real enquiry ID
        let finalEnquiryNo = cleanId
            ? 'XENQ-' + cleanId
            : '';

        // Display
        $('#enquiry_id').val(finalEnquiryNo);

        // Hidden field used for form submission
        $('#enquiry_id_hidden').val(cleanId);
        const PRICING = {
            bev6Premium: {
                permit: [
                    { type: "Private",   default: true  },
                    { type: "Goods",     default: false },
                    { type: "Passenger", default: false }
                ],

                receivables: {
                    exShowroom: 2499000,

                    // Insurance — one entry per permit CATEGORY
                    insurance: [
                        {
                            permit: "Private",
                            default: true,
                            companies: [
                                {
                                    insCo: "ICICI Lombard",
                                    default: true,
                                    price: [
                                        { head: "Basic OD + TP",       price: 42500, Nature: "M" },
                                        { head: "Nil Depreciation",    price: 8500,  Nature: "M" },
                                        { head: "Consumables",         price: 1800,  Nature: "M" },
                                        { head: "Battery Protect Plus",price: 12000, Nature: "O" },
                                        { head: "RTI",                 price: 6800,  Nature: "O" },
                                        { head: "Engine Protect",      price: 5500,  Nature: "O" },
                                        { head: "Key Protect",         price: 1200,  Nature: "O" },
                                        { head: "Tyre Protect",        price: 3200,  Nature: "O" },
                                        { head: "RSA Premium",         price: 2800,  Nature: "O" },
                                        { head: "NCB Protect Plus",    price: 4500,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "Bajaj Allianz",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",        price: 39800, Nature: "M" },
                                        { head: "Nil Depreciation",     price: 7800,  Nature: "M" },
                                        { head: "Consumables",          price: 1600,  Nature: "M" },
                                        { head: "Battery Protect Plus", price: 10500, Nature: "O" },
                                        { head: "RTI",                  price: 6200,  Nature: "O" },
                                        { head: "Key Protect",          price: 1100,  Nature: "O" },
                                        { head: "Tyre Protect",         price: 2800,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "United India (USGI)",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",     price: 41500, Nature: "M" },
                                        { head: "Nil Depreciation",  price: 8200,  Nature: "M" },
                                        { head: "Consumables",       price: 1700,  Nature: "M" },
                                        { head: "Battery Protect",   price: 9800,  Nature: "O" },
                                        { head: "RTI",               price: 6500,  Nature: "O" },
                                        { head: "Engine Protect",    price: 5200,  Nature: "O" },
                                        { head: "NCB Protect",       price: 3500,  Nature: "O" }
                                    ]
                                }
                            ]
                        },
                        {
                            permit: "Goods",
                            default: false,
                            companies: [
                                {
                                    insCo: "ICICI Lombard",
                                    default: true,
                                    price: [
                                        { head: "Basic OD + TP",       price: 42500, Nature: "M" },
                                        { head: "Nil Depreciation",    price: 8500,  Nature: "M" },
                                        { head: "Consumables",         price: 1800,  Nature: "M" },
                                        { head: "Battery Protect Plus",price: 12000, Nature: "O" },
                                        { head: "RTI",                 price: 6800,  Nature: "O" },
                                        { head: "Engine Protect",      price: 5500,  Nature: "O" },
                                        { head: "Key Protect",         price: 1200,  Nature: "O" },
                                        { head: "Tyre Protect",        price: 3200,  Nature: "O" },
                                        { head: "RSA Premium",         price: 2800,  Nature: "O" },
                                        { head: "NCB Protect Plus",    price: 4500,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "Bajaj Allianz",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",        price: 39800, Nature: "M" },
                                        { head: "Nil Depreciation",     price: 7800,  Nature: "M" },
                                        { head: "Consumables",          price: 1600,  Nature: "M" },
                                        { head: "Battery Protect Plus", price: 10500, Nature: "O" },
                                        { head: "RTI",                  price: 6200,  Nature: "O" },
                                        { head: "Key Protect",          price: 1100,  Nature: "O" },
                                        { head: "Tyre Protect",         price: 2800,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "United India (USGI)",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",     price: 41500, Nature: "M" },
                                        { head: "Nil Depreciation",  price: 8200,  Nature: "M" },
                                        { head: "Consumables",       price: 1700,  Nature: "M" },
                                        { head: "Battery Protect",   price: 9800,  Nature: "O" },
                                        { head: "RTI",               price: 6500,  Nature: "O" },
                                        { head: "Engine Protect",    price: 5200,  Nature: "O" },
                                        { head: "NCB Protect",       price: 3500,  Nature: "O" }
                                    ]
                                }
                            ]
                        },
                        {
                            permit: "Passenger",
                            default: false,
                            companies: [
                                {
                                    insCo: "ICICI Lombard",
                                    default: true,
                                    price: [
                                        { head: "Basic OD + TP",       price: 42500, Nature: "M" },
                                        { head: "Nil Depreciation",    price: 8500,  Nature: "M" },
                                        { head: "Consumables",         price: 1800,  Nature: "M" },
                                        { head: "Battery Protect Plus",price: 12000, Nature: "O" },
                                        { head: "RTI",                 price: 6800,  Nature: "O" },
                                        { head: "Engine Protect",      price: 5500,  Nature: "O" },
                                        { head: "Key Protect",         price: 1200,  Nature: "O" },
                                        { head: "Tyre Protect",        price: 3200,  Nature: "O" },
                                        { head: "RSA Premium",         price: 2800,  Nature: "O" },
                                        { head: "NCB Protect Plus",    price: 4500,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "Bajaj Allianz",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",        price: 39800, Nature: "M" },
                                        { head: "Nil Depreciation",     price: 7800,  Nature: "M" },
                                        { head: "Consumables",          price: 1600,  Nature: "M" },
                                        { head: "Battery Protect Plus", price: 10500, Nature: "O" },
                                        { head: "RTI",                  price: 6200,  Nature: "O" },
                                        { head: "Key Protect",          price: 1100,  Nature: "O" },
                                        { head: "Tyre Protect",         price: 2800,  Nature: "O" }
                                    ]
                                },
                                {
                                    insCo: "United India (USGI)",
                                    default: false,
                                    price: [
                                        { head: "Basic OD + TP",     price: 41500, Nature: "M" },
                                        { head: "Nil Depreciation",  price: 8200,  Nature: "M" },
                                        { head: "Consumables",       price: 1700,  Nature: "M" },
                                        { head: "Battery Protect",   price: 9800,  Nature: "O" },
                                        { head: "RTI",               price: 6500,  Nature: "O" },
                                        { head: "Engine Protect",    price: 5200,  Nature: "O" },
                                        { head: "NCB Protect",       price: 3500,  Nature: "O" }
                                    ]
                                }
                            ]
                        }
                    ],

                    // RTO Registration — one entry per permit CATEGORY
                    RTO: {
                        TRC: 1800,
                        TAX: [
                            { permit: "Private",   default: true,  amount: 245000 },
                            { permit: "Goods",     default: false, amount: 180000 },
                            { permit: "Passenger", default: false, amount: 220000 }
                        ]
                    },

                    // ─────────────────────────────────────────────
                    // Nothing below this line has changed
                    // ─────────────────────────────────────────────

                    coating: [
                        { title: "Ceramic",     price: 24999, default: true  },
                        { title: "Graphene",    price: 45999, default: false },
                        { title: "No Coating",  price: 0,     default: false }
                    ],

                    ppf: [
                        { title: "PPF Ultra",    price: 124999, default: true  },
                        { title: "PPF Premium",  price: 164999, default: false },
                        { title: "PPF Ultimate", price: 199999, default: false },
                        { title: "No PPF",       price: 0,      default: false }
                    ],

                    accessories: [
                        { item: "7.2 kW Home Charger",    mrp: 45000, discount: 5000, code: "HC-72"   },
                        { item: "11.2 kW Home Charger",   mrp: 65000, discount: 8000, code: "HC-112"  },
                        { item: "Dash Cam Dual Channel",  mrp: 8299,  discount: 1200, code: "DC-DUAL" },
                        { item: "Parking Assist 360°",    mrp: 15999, discount: 2500, code: "PA-360"  },
                        { item: "Leather Seat Covers",    mrp: 14990, discount: 2000, code: "LSC-7"   },
                        { item: "Premium Floor Mats",     mrp: 5899,  discount: 800,  code: "PFM-7"   },
                        { item: "Chrome Door Visors",     mrp: 4999,  discount: 500,  code: "CDV"     },
                        { item: "Alloy Wheel Locks",      mrp: 2999,  discount: 400,  code: "AWL"     },
                        { item: "Scuff Plates LED",       mrp: 3899,  discount: 600,  code: "SP-LED"  },
                        { item: "Mud Flaps",              mrp: 1299,  discount: 200,  code: "MF"      },
                        { item: "Perfume Dispenser",      mrp: 899,   discount: 0,    code: "PD-001"  },
                        { item: "Car Cover Premium",      mrp: 3499,  discount: 500,  code: "CC-PREM" },
                        { item: "Sun Shade Set",          mrp: 2499,  discount: 300,  code: "SS-4"    },
                        { item: "USB C Charging Kit",     mrp: 1899,  discount: 200,  code: "USB-C"   },
                        { item: "First Aid Kit",          mrp: 999,   discount: 0,    code: "FAK"     }
                    ],

                    maxicare: 34999,

                    shield: [
                        { title: "4th Year",        price: 34990, default: true  },
                        { title: "4th + 5th Year",  price: 52990, default: false },
                        { title: "No Shield",       price: 0,     default: false }
                    ],

                    rsa: [
                        { title: "1 Year",  price: 2499, default: true  },
                        { title: "2 Year",  price: 4599, default: false },
                        { title: "3 Year",  price: 6499, default: false },
                        { title: "No RSA",  price: 0,    default: false }
                    ],

                    vltd: { permit: "Private", price: 5499 },
                    kazam: 8500,
                    incidental: 5000,
                    "rto-tape": 1999,
                    fastag: 600,
                    COD: 2500,

                    "charger-swapping": [
                        { title: "No Swapping @ ₹0",             amount: 0,     default: false },
                        { title: "NCH to 7.2 kW @ ₹18,500",      amount: 18500, default: true  },
                        { title: "NCH to 11.2 kW @ ₹28,500",     amount: 28500, default: false },
                        { title: "7.2 kW to 11.2 kW @ ₹15,000",  amount: 15000, default: false }
                    ],

                    tcs: {
                        limit: 1000000,
                        rate: 1.0
                    }
                },

                // Deductibles / Discounts — unchanged
                deductibles: {
                    "oem-schemes": [
                        { key: "cash_scheme_oem", label: "Cash Scheme OEM",   amount: 8500, type: "INV_OE" },
                        { key: "csd_discount",    label: "CSD Discount",      amount: 3500, type: "INV_OE" },
                        { key: "fame_subsidy",    label: "Fame Subsidy (LMM)",amount: 1500, type: "INV_OE" }
                    ],

                    "dealer-scheme":    { amount: 25000, type: "CN1"    },
                    "accessory-scheme": { amount: 8000,  type: "INV_OE" },
                    "shield-scheme":    { amount: 3500,  type: "CN1"    },

                    "corp-scheme": [
                        { name: "Corporate Discount", amount: 60000, type: "INV" },
                        { name: "Loyalty Bonus",      amount: 35000, type: "INV" }
                    ],

                    "exchange-scheme": [
                        { name: "Exchange Bonus", amount: 45000, type: "CN2" },
                        { name: "Green Bonus",    amount: 25000, type: "CN2" },
                        { name: "Welcome Bonus",  amount: 15000, type: "CN2" }
                    ],

                    "accessories-spl-discount": { amount: 5000,  type: "INV_D" },
                    "coating-spl-discount":     { amount: 3000,  type: "INV_D" },
                    "ppf-spl-discount":         { amount: 10000, type: "CN1"   },
                    "charger-swapping-discount":{ amount: 7500,  type: "CN3", option: "7.2 kW to NCH" },
                    "other-cash-discount":      { amount: 2000,  type: "CN1"   },
                    "special-cash-discount":    { amount: 75000, type: "INV_D" }
                },

                conditional_rules: {
                    accessories_scheme: {
                        min_amount: 8000,
                        discount_percentage: 20,
                        freeze_message: 'Accessories above ₹8,000'
                    },
                    shield_scheme: {
                        min_amount: 6000,
                        discount_amount: 3000,
                        freeze_message: 'Shield above ₹6,000'
                    }
                }
            }
        };

        // Add this at the top of your script, after PRICING definition
        const ACCESSORY_NAME_MAP = {
            "7.2 kW Home Charger": "7.2 Kw Home Charger",
            "11.2 kW Home Charger": "11.2 Kw Home Charger",
            "Dash Cam Dual Channel": "Dash Cam Dual Channel",
            "Parking Assist 360°": "Parking Assist 360°",
            "Leather Seat Covers": "Leather Seat Covers",
            "Premium Floor Mats": "Premium Float Mats",
            "Chrome Door Visors": "Chrome Door Visors",
            "Alloy Wheel Locks": "Alloy Wheel Locks",
            "Scuff Plates LED": "Scuff Plates Led",
            "Mud Flaps": "Mud Flaps",
            "Perfume Dispenser": "Perfume Dispenser",
            "Car Cover Premium": "Car Cover Premium",
            "Sun Shade Set": "Magnetic Sun Shade Set",
            "USB C Charging Kit": "Usb Charging Kit",
            "First Aid Kit": "First Aid Kit"
        };

        // ============================================================
        // 2. HELPER FUNCTIONS
        // ============================================================

        function numberToIndianWords(num) {
            num = parseFloat(num) || 0;
            if (num === 0) return 'Zero Rupees Only';

            const a = [
                '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
                'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
            ];
            const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

            function inWords(n) {
                if (n < 20) return a[n];
                if (n < 100) return b[Math.floor(n / 10)] + (n % 10 !== 0 ? ' ' + a[n % 10] : '');
                if (n < 1000) return a[Math.floor(n / 100)] + ' Hundred' + (n % 100 !== 0 ? ' ' + inWords(n % 100) : '');
                if (n < 100000) return inWords(Math.floor(n / 1000)) + ' Thousand' + (n % 1000 !== 0 ? ' ' + inWords(n %
                    1000) : '');
                if (n < 10000000) return inWords(Math.floor(n / 100000)) + ' Lakh' + (n % 100000 !== 0 ? ' ' + inWords(n %
                    100000) : '');
                return inWords(Math.floor(n / 10000000)) + ' Crore' + (n % 10000000 !== 0 ? ' ' + inWords(n % 10000000) :
                    '');
            }

            // Split Integer and Decimal (Paise) parts
            let parts = num.toFixed(2).split('.');
            let rupees = parseInt(parts[0], 10);
            let paise = parseInt(parts[1], 10);

            let result = '';

            if (rupees > 0) {
                result += inWords(rupees) + ' Rupees';
            }

            if (paise > 0) {
                if (rupees > 0) result += ' ';
                result += inWords(paise) + ' Paise';
            }

            return result ? result + ' Only' : 'Zero Rupees Only';
        }

        const IS_EDIT_MODE = @json(isset($quotation));

        const SAVED_INSURANCE_COMPANY =
            @json($quotationData['insurance_company'] ?? '');

        const SAVED_INSURANCE_COVERS =
            @json($quotationData['insurance_covers'] ?? []);

        const SAVED_INSURANCE_AMOUNT =
            @json($quotationData['insurance_amount'] ?? '');

        const SAVED_ACCESSORIES =
            @json($quotationData['accessories'] ?? []);

        const SAVED_ACCESSORIES_AMOUNT =
            @json($quotationData['accessories_amount'] ?? '');

        // ============================================================
        // SAVED QUOTATION VALUES - EDIT MODE
        // ============================================================

        const SAVED_CHARGER_SWAPPING =
            @json($quotationData['charger_swapping'] ?? '');

        const SAVED_CHARGER_SWAPPING_AMOUNT =
            @json($quotationData['charger_swapping_amount'] ?? '');

        const SAVED_CHARGER_SWAPPING_OPTION =
            @json($quotationData['charger_swapping_option'] ?? '');

        const SAVED_COATING =
            @json($quotationData['coating'] ?? '');

        const SAVED_COATING_PRICE =
            @json($quotationData['coating_price'] ?? '');

        const SAVED_PPF =
            @json($quotationData['ppf'] ?? '');

        const SAVED_SHIELD =
            @json($quotationData['shield'] ?? '');

        const SAVED_SHIELD_PRICE =
            @json($quotationData['shield_price'] ?? '');

        const SAVED_RSA =
            @json($quotationData['rsa'] ?? '');

        const SAVED_RSA_AMOUNT =
            @json($quotationData['rsa_amount'] ?? '');

        const SAVED_MAXICARE =
            @json($quotationData['maxicare'] ?? '');

        const SAVED_VLTD_DEVICE =
            @json($quotationData['vltd_device'] ?? '');

        const SAVED_RTO_YELLOW_TAPE =
            @json($quotationData['rto_yellow_tape'] ?? '');

        const SAVED_KAZAM_CHARGING_KIT =
            @json($quotationData['kazam_charging_kit'] ?? '');

        const SAVED_INCIDENTAL_CHARGES =
            @json($quotationData['incidental_charges'] ?? '');

        const SAVED_FASTAG =
            @json($quotationData['fastag'] ?? '');

        const SAVED_COD_CHARGES =
            @json($quotationData['cod_charges'] ?? '');

        let currentInsurance = null;
        let currentPricing = null;
        let STARTER_PACK_ACCESSORIES = []; // Lock track karne ke liye
        let accessoriesRestoreInProgress = false;

        function refreshInsuranceSelect2() {

            const $insurance = $('#insurance_covers');

            if (!$insurance.length) {
                return;
            }

            // Select2 already initialized
            if ($insurance.hasClass('select2-hidden-accessible')) {
                $insurance.trigger('change.select2');
            }

            // Sync selected values with Select2
            $insurance.trigger('change');
        }

        function loadInsurance(company) {
            let total = 0;
            $("#insurance_covers").empty();

            company.price.forEach(function(item) {
                let isMandatory = (item.Nature == "M");
                let option = new Option(item.head + " (₹" + item.price + ")", item.head, isMandatory, isMandatory);
                $(option).attr("data-price", item.price);

                if (isMandatory) {
                    $(option).attr("data-mandatory", "true");
                    total += item.price;
                }
                $("#insurance_covers").append(option);
            });

            // ✅ FIX: Restore saved covers WITH their saved prices
            if (IS_EDIT_MODE && Array.isArray(SAVED_INSURANCE_COVERS) && SAVED_INSURANCE_COVERS.length) {
                SAVED_INSURANCE_COVERS.forEach(function(savedCover) {
                    let savedName = typeof savedCover === 'object' && savedCover !== null 
                        ? savedCover.name 
                        : savedCover;
                    let savedPrice = typeof savedCover === 'object' && savedCover !== null 
                        ? Number(savedCover.price || 0) 
                        : null;

                    let matched = false;

                    $("#insurance_covers option").each(function() {
                        // Match by exact name
                        if ($(this).val().trim().toLowerCase() === savedName.trim().toLowerCase()) {
                            $(this).prop("selected", true);
                            
                            if (
                                savedPrice !== null &&
                                savedPrice > 0 &&
                                !$(this).prop('disabled')
                            ) {
                                $(this).attr('data-price', savedPrice);

                                let optionText = savedName + ' (₹' + savedPrice.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ')';

                                $(this).text(optionText);
                            }
                            matched = true;
                        }
                    });

                    // If not found in mock options, add it with saved price
                    if (!matched) {
                        let priceToUse = savedPrice || 0;
                        let option = new Option(
                            savedName + ' (₹' + priceToUse + ')',
                            savedName,
                            true,
                            true
                        );
                        $(option).attr('data-price', priceToUse);
                        $('#insurance_covers').append(option);
                    }
                });
            } else {
                // Only auto-select mandatory if NOT in edit mode with saved covers
                $("#insurance_covers option").each(function() {
                    if ($(this).attr("data-mandatory") === "true") {
                        $(this).prop("selected", true);
                    }
                });
            }

            // Move all selected covers to TOP
            let $selectedIns = $('#insurance_covers option:selected');
            $('#insurance_covers').prepend($selectedIns);

            // IMPORTANT:
            // Refresh Select2 after dynamically adding/removing options
            $('#insurance_covers').trigger('change');

            if (IS_EDIT_MODE && SAVED_INSURANCE_AMOUNT !== '') {
                $('#insurance_amount').val(
                    Number(SAVED_INSURANCE_AMOUNT).toFixed(2)
                );
            }

            $('#insurance_covers').trigger('change.select2');
            updateInsurancePrintText();
        }

        function loadInsuranceByPermit() {
            let permit = $("#permit").val();

            const permitPricingType = @json($permit_pricing_map ?? []);

            let pricingPermit = permitPricingType[permit] || permit;

            let enquiry = ENQUIRIES[$("#mock_enquiry_no").val()];
            if (!enquiry) return;

            let pricing = PRICING[enquiry.pricingKey];

            currentInsurance = pricing.receivables.insurance.find(
                x => x.permit === pricingPermit
            );

            if (!currentInsurance) {
                currentInsurance = pricing.receivables.insurance[0];
            }

            $("#insurance_company").empty();

            currentInsurance.companies.forEach(function(company) {
                $("#insurance_company").append(
                    `<option value="${company.insCo}">${company.insCo}</option>`
                );
            });

            let selectedCompany = null;

            if (IS_EDIT_MODE && SAVED_INSURANCE_COMPANY) {
                selectedCompany = currentInsurance.companies.find(
                    x => x.insCo === SAVED_INSURANCE_COMPANY
                );
            }

            if (!selectedCompany) {
                selectedCompany =
                    currentInsurance.companies.find(x => x.default) ||
                    currentInsurance.companies[0];
            }

            if (selectedCompany) {
                $("#insurance_company").val(selectedCompany.insCo);

                loadInsurance(selectedCompany);
            }
        }

        function restoreSavedInsuranceCoversDirectly() {

    if (!IS_EDIT_MODE) {
        return;
    }

    const $insurance = $('#insurance_covers');

    if (!$insurance.length) {
        return;
    }

    // Clear existing options
    $insurance.empty();

    if (
        !Array.isArray(SAVED_INSURANCE_COVERS) ||
        SAVED_INSURANCE_COVERS.length === 0
    ) {
        return;
    }

    const selectedValues = [];

    SAVED_INSURANCE_COVERS.forEach(function(savedCover) {

        let name = '';
        let price = 0;

        if (typeof savedCover === 'object' && savedCover !== null) {
            name = String(savedCover.name || '').trim();
            price = Number(savedCover.price || 0);
        } else {
            name = String(savedCover || '').trim();
        }

        if (!name) {
            return;
        }

        const option = new Option(
            name + ' (₹' + price.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ')',
            name,
            true,
            true
        );

        $(option)
            .attr('data-price', price)
            .data('price', price);

        $insurance.append(option);

        selectedValues.push(name);
    });

    // Set selected values
    $insurance.val(selectedValues);

    // Refresh Select2
    $insurance.trigger('change.select2');

    // Trigger normal change so quotation calculations update
    $insurance.trigger('change');

    // IMPORTANT:
    // Don't allow the change event to replace the saved amount
    if (SAVED_INSURANCE_AMOUNT !== '') {
        $('#insurance_amount').val(
            Number(SAVED_INSURANCE_AMOUNT).toFixed(2)
        );
    }

    updateInsurancePrintText();
}
        // ============================================================
        // RESTORE SAVED QUOTATION VALUES IN EDIT MODE
        // ============================================================

        function restoreSavedQuotationValues() {

            if (!IS_EDIT_MODE) {
                return;
            }

            if (Array.isArray(SAVED_ACCESSORIES) && SAVED_ACCESSORIES.length > 0) {

    let selectedAccessories = [];

    let savedEnquiryNo = "{{ $quotationData['enquiry_no'] ?? ($quotation->enquiry_no ?? '') }}";
    let savedEnquiry = ENQUIRIES[savedEnquiryNo] || null;
    let savedPricing = savedEnquiry ? PRICING[savedEnquiry.pricingKey] : null;
    let mockAccessories = savedPricing?.receivables?.accessories || [];

    SAVED_ACCESSORIES.forEach(function(accessory) {

        let value = typeof accessory === 'object'
            ? (accessory.part_no ?? accessory.code ?? accessory.value ?? '')
            : accessory;

        value = String(value).trim();
        if (!value) return;

        let found = false;

        // 1. Try matching a DB option (part_no)
        $('#accessories option').each(function() {
            if (String($(this).val()).trim() === value) {
                $(this).prop('selected', true);

                // ✅ FIX: If saved as object with its own price, override the price
                if (typeof accessory === 'object' && accessory !== null) {
                    let savedPrice = Number(accessory.mrp ?? accessory.price ?? 0);
                    if (savedPrice > 0) {
                        $(this).attr('data-price', savedPrice);
                        $(this).data('price', savedPrice);
                    }
                }

                selectedAccessories.push($(this).val());
                found = true;
                return false;
            }
        });

        if (found) return;

        // 2. Try matching accessory from ALL pricing definitions
        // This prevents HC-72, HC-112, DC-DUAL etc. from becoming 0.

        let mockAccessory = null;

        Object.keys(PRICING || {}).some(function(pricingKey) {

            let pricingData = PRICING[pricingKey];

            let accessories =
                pricingData?.receivables?.accessories || [];

            let foundAccessory = accessories.find(function(acc) {

                return String(acc.code || '')
                    .trim()
                    .toLowerCase() ===
                    value.trim().toLowerCase();

            });

            if (foundAccessory) {
                mockAccessory = foundAccessory;
                return true;
            }

            return false;
        });

        if (mockAccessory) {
            let name  = mockAccessory.item || value;
            let price = Number(mockAccessory.mrp || 0);
            let option = new Option(
                name + ' (₹' + price.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ')',
                value, true, true
            );
            $(option).attr('data-price', price).data('price', price);
            $('#accessories').append(option);
            selectedAccessories.push(value);
            return;
        }

        // 3. ✅ FIX: Fallback — always add if not found anywhere
        let accName  = (typeof accessory === 'object' && accessory !== null)
            ? (accessory.item ?? accessory.name ?? value)
            : value;
        let accPrice = (typeof accessory === 'object' && accessory !== null)
            ? Number(accessory.mrp ?? accessory.price ?? 0)
            : 0;

        let option = new Option(
            accName + ' (₹' + accPrice.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ')',
            value, true, true
        );
        $(option).attr('data-price', accPrice).data('price', accPrice);
        $('#accessories').append(option);
        selectedAccessories.push(value);
    });

    accessoriesRestoreInProgress = true;

    $('#accessories')
        .val(selectedAccessories)
        .trigger('change');

    if (IS_EDIT_MODE && SAVED_ACCESSORIES_AMOUNT !== '') {
        $('#accessories_amount').val(
            Number(SAVED_ACCESSORIES_AMOUNT).toFixed(2)
        );
    } else {
        updateAccessoriesAmount();
    }

    updateAccessoriesPrintText();
    accessoriesRestoreInProgress = false;
}

            // --------------------------------------------------------
            // Charger Swapping
            // --------------------------------------------------------
            if (SAVED_CHARGER_SWAPPING) {
                $('#charger_swapping')
                    .val(SAVED_CHARGER_SWAPPING)
                    .prop('disabled', false);

                if (SAVED_CHARGER_SWAPPING_AMOUNT !== '') {
                    $('#charger_swapping_amount')
                        .val(SAVED_CHARGER_SWAPPING_AMOUNT)
                        .prop('disabled', false);
                }

                if (SAVED_CHARGER_SWAPPING_OPTION) {
                    $('#charger_swapping_option')
                        .val(SAVED_CHARGER_SWAPPING_OPTION)
                        .prop('disabled', false);
                }
            }

            // --------------------------------------------------------
            // Coating
            // --------------------------------------------------------
            if (SAVED_COATING !== '') {
                $('#coating').val(SAVED_COATING);
            }

            if (SAVED_COATING_PRICE !== '') {
                $('#coating_price')
                    .val(SAVED_COATING_PRICE)
                    .prop('disabled', SAVED_COATING_PRICE === 'N/A');
            }

            // --------------------------------------------------------
            // PPF
            // --------------------------------------------------------
            if (SAVED_PPF !== '') {
                $('#ppf')
                    .val(SAVED_PPF)
                    .prop('disabled', SAVED_PPF === 'N/A');
            }

            // --------------------------------------------------------
            // Shield
            // --------------------------------------------------------
            if (SAVED_SHIELD !== '') {
                $('#shield').val(SAVED_SHIELD);
            }

            if (SAVED_SHIELD_PRICE !== '') {
                $('#shield_price')
                    .val(SAVED_SHIELD_PRICE)
                    .prop('disabled', SAVED_SHIELD_PRICE === 'N/A');
            }

            // --------------------------------------------------------
            // RSA
            // --------------------------------------------------------
            if (SAVED_RSA !== '') {
                $('#rsa').val(SAVED_RSA);
            }

            if (SAVED_RSA_AMOUNT !== '') {
                $('#rsa_amount')
                    .val(SAVED_RSA_AMOUNT)
                    .prop('disabled', SAVED_RSA_AMOUNT === 'N/A');
            }

            // --------------------------------------------------------
            // Simple amount fields
            // --------------------------------------------------------
            if (SAVED_MAXICARE !== '') {
                $('#maxicare')
                    .val(SAVED_MAXICARE)
                    .prop('disabled', SAVED_MAXICARE === 'N/A');
            }

            if (SAVED_VLTD_DEVICE !== '') {
                $('#vltd_device')
                    .val(SAVED_VLTD_DEVICE)
                    .prop('disabled', SAVED_VLTD_DEVICE === 'N/A');
            }

            if (SAVED_RTO_YELLOW_TAPE !== '') {
                $('#rto_yellow_tape')
                    .val(SAVED_RTO_YELLOW_TAPE)
                    .prop('disabled', SAVED_RTO_YELLOW_TAPE === 'N/A');
            }

            if (SAVED_KAZAM_CHARGING_KIT !== '') {
                $('#kazam_charging_kit')
                    .val(SAVED_KAZAM_CHARGING_KIT)
                    .prop('disabled', SAVED_KAZAM_CHARGING_KIT === 'N/A');
            }

            if (SAVED_INCIDENTAL_CHARGES !== '') {
                $('#incidental_charges')
                    .val(SAVED_INCIDENTAL_CHARGES)
                    .prop('disabled', SAVED_INCIDENTAL_CHARGES === 'N/A');
            }

            if (SAVED_FASTAG !== '') {
                $('#fastag')
                    .val(SAVED_FASTAG)
                    .prop('disabled', SAVED_FASTAG === 'N/A');
            }

            if (SAVED_COD_CHARGES !== '') {
                $('#cod_charges')
                    .val(SAVED_COD_CHARGES)
                    .prop('disabled', SAVED_COD_CHARGES === 'N/A');
            }

            calculateQuotation();
            updateCoatingDiscountLabel();
            updateAccessoriesPrintText();
            updateInsurancePrintText();
            updateRegistrationPrintText();
        }

        function updateRegistrationAmount() {
            let permit = $("#permit").val();

            const permitPricingType = @json($permit_pricing_map ?? []);

            let pricingPermit = permitPricingType[permit] || permit;

            let enquiry = ENQUIRIES[$("#mock_enquiry_no").val()];
            if (!enquiry) return;

            let pricing = PRICING[enquiry.pricingKey];

            let tax = pricing.receivables.RTO.TAX.find(
                x => x.permit === pricingPermit
            ) || pricing.receivables.RTO.TAX[0];

            $('#registration_amount').val(
                (tax.amount + pricing.receivables.RTO.TRC).toFixed(2)
            );
        }

        function updateInsurancePrintText() {
            var list = [];
            $('#insurance_covers option:selected').each(function() {

                var $option = $(this);

                var name = String(
                    $option.val() || $option.text() || ''
                ).trim();

                // Remove already formatted price from text if present
                name = name.replace(/\s*\(₹.*?\)\s*$/, '').trim();

                var price = parseFloat($option.attr('data-price'));

                // Fallback to jQuery data()
                if (isNaN(price)) {
                    price = parseFloat($option.data('price'));
                }

                if (isNaN(price)) {
                    price = 0;
                }

                if (!name) {
                    return;
                }

                list.push(
                    name + ' (₹' + price.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ')'
                );
            });

            $('#insurance_print').text(list.join(', '));
        }

        function updateAccessoriesPrintText() {
            let list = [];
            $('#accessories option:selected').each(function() {
                let rawText = $(this).text().trim();
                let price = parseFloat($(this).attr('data-price') || 0);   // ← use attr
                let itemName = rawText.replace(/\(.*?\)/, '').trim();
                let formattedName = itemName.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
                list.push(formattedName + ' (₹' + price.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ')');
            });
            $('#accessories_print').text(list.join(', '));
        }

        function updateAccessoriesAmount() {
            let total = 0;

            $('#accessories option:selected').each(function() {
                let price = parseFloat($(this).attr('data-price'));

                if (isNaN(price)) {
                    price = parseFloat($(this).data('price'));
                }

                if (!isNaN(price)) {
                    total += price;
                }
            });

            $('#accessories_amount').val(total.toFixed(2));
        }

        

        function toggleRowVisibility() {

            // Price grid rows
            $('.price-grid tbody tr').each(function() {
                let $row = $(this);
                let $input = $row.find('td.cell-amount input').first();
                let value = $input.length ? ($input.val() || '').toString().trim() : '';

                // If the field currently has a real amount, mark this row as "present"
                if (value !== '' && value !== 'N/A' && Number(value) !== 0) {
                    $row.data('was-present', true);
                }

                // EXCEPT: Always show accessories_discount and shield_scheme rows in Edit Mode
                if (IS_EDIT_MODE) {
                    // Check if this is the accessories_discount row
                    let isAccessorySchemeRow = $row.find('#accessories_discount').length > 0;
                    let isShieldSchemeRow = $row.find('#shield_scheme').length > 0;

                    if (isAccessorySchemeRow || isShieldSchemeRow) {
                        // Always show these rows in Edit Mode, regardless of value
                        $row.show();
                        return;
                    }
                }

                if (
                    !$row.data('was-present') &&
                    (value === '' || value === 'N/A' || Number(value) === 0)
                ) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });

            // Discount grid rows
            $('.discount-grid tbody tr').each(function() {
                let $row = $(this);
                let $input = $row.find('td.cell-amount input').first();
                let value = $input.length ? ($input.val() || '').toString().trim() : '';

                // If the field currently has a real amount, mark this row as "present"
                if (value !== '' && value !== 'N/A' && Number(value) !== 0) {
                    $row.data('was-present', true);
                }

                // EXCEPT: Always show accessories_discount and shield_scheme rows in Edit Mode
                if (IS_EDIT_MODE) {
                    // Check if this is the accessories_discount or shield_scheme row
                    let isAccessorySchemeRow = $row.find('#accessories_discount').length > 0;
                    let isShieldSchemeRow = $row.find('#shield_scheme').length > 0;

                    if (isAccessorySchemeRow || isShieldSchemeRow) {
                        // Always show these rows in Edit Mode, regardless of value
                        $row.show();
                        return;
                    }
                }

                // Hide only if the field was never present
                if (
                    !$row.data('was-present') &&
                    (value === '' || value === 'N/A' || Number(value) === 0)
                ) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });

            // Hide TCS row if value is N/A or 0
            $('.tcs-row').each(function() {
                let $row = $(this);
                let tcsInput = $row.find('td.cell-amount input');
                let tcsValue = tcsInput.length ? (tcsInput.val() || '').toString().trim() : '';

                if (tcsValue === '' || tcsValue === 'N/A' || Number(tcsValue) === 0) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });
        }

        // ============================================================
        // 4. DYNAMIC GROUP A DISCOUNTS RENDERER
        // ============================================================

        // Group A "Type" cell: only "Cash Scheme OEM" genuinely has 2 valid types
        // (INV / CN), so it stays a dropdown. "CSD Discount" and "Fame Subsidy"
        // only ever have INV, so for those a dropdown is pointless — render a
        // plain readonly field instead. Keeps the same #group_a_type id either way.
        function renderGroupAType(key, presetType) {
            const $old = $('#group_a_type');
            let typeVal = presetType || 'INV_OE';
            if (typeVal === 'INV') typeVal = 'INV_OE';
            if (typeVal === 'CN') typeVal = 'CN1';

            let $new = $(
                '<select id="group_a_type">' +
                '<option value="INV_OE">Inv Disc. (OE)</option>' +
                '<option value="CN1">CN1</option>' +
                '</select>'
            );
            $new.val(typeVal);
            $old.replaceWith($new);
            return $new;
        }

        function renderGroupADiscounts(pricing) {

            // User agar manually edit kar raha hai to overwrite mat karo
            if ($('#group_a_amount').is(':focus')) return;

            let schemes = pricing.deductibles["oem-schemes"] || [];
            let activeSchemes = schemes.filter(s => Number(s.amount) > 0);

            // Clear hidden fields
            $('#cash_scheme_oem, #csd_discount, #fame_subsidy').val('');
            $('#cash_scheme_oem_type, #csd_discount_type, #fame_subsidy_type').val('');

            if (!activeSchemes.length) return;

            let firstScheme = activeSchemes[0];

            // Set visible values
            $('#group_a_select').val(firstScheme.key);
            renderGroupAType(firstScheme.key, firstScheme.type);
            $('#group_a_amount').val(firstScheme.amount);

            // Hidden values
            $('#' + firstScheme.key).val(firstScheme.amount);
            $('#' + firstScheme.key + '_type').val(firstScheme.type);

            // Additional schemes
            activeSchemes.slice(1).forEach(function(scheme) {
                $('#' + scheme.key).val(scheme.amount);
                $('#' + scheme.key + '_type').val(scheme.type);
            });

            $('#group_a_amount').trigger('change');
        }

        const SPECIAL_FOUR_CONFIG = [{
                id: 'accessories_spl_disc_type',
                selector: '#accessories_spl_disc_type'
            },
            {
                id: 'ceramic_discount_type',
                selector: '#ceramic_discount_type'
            },
            {
                id: 'ppf_discount_type',
                selector: '#ppf_discount_type'
            },
            {
                id: 'other_cash_discount_type',
                selector: '#other_cash_discount_type'
            }
        ];

        // Jab Group A "Inv Disc. (OE)" ho toh 4 discounts ko Readonly text banana
        function setSpecialFourAsReadonly() {
            SPECIAL_FOUR_CONFIG.forEach(function(field) {
                let $el = $(field.selector);
                if (!$el.length) return;

                if ($el.is('select')) {
                    let $input = $('<input>', {
                        type: 'text',
                        id: field.id,
                        name: field.id,
                        value: 'Inv Disc. (D)',
                        readonly: true
                    }).css({
                        'width': '100%',
                        'border': 'none',
                        'background': 'transparent',
                        'font-size': '10px',
                        'padding': '2px',
                        'text-align': 'left',
                        'cursor': 'default'
                    });
                    $el.replaceWith($input);
                } else if ($el.is('input')) {
                    $el.val('Inv Disc. (D)');
                }
            });
        }

        // Jab Group A "CN1" ho toh 4 discounts me Dropdown wapas lana
        function restoreSpecialFourDropdowns(defaultVal = 'CN1') {
            SPECIAL_FOUR_CONFIG.forEach(function(field) {
                let $el = $(field.selector);
                if (!$el.length) return;

                if ($el.is('input')) {
                    let $select = $('<select>', {
                        id: field.id,
                        name: field.id
                    }).append(
                        '<option value="INV_D">Inv Disc. (D)</option>' +
                        '<option value="CN1">CN1</option>'
                    );
                    $select.val(defaultVal);
                    $el.replaceWith($select);
                }
            });
        }

        function syncSpecialFourDiscountTypes(newVal) {
            if (newVal !== 'INV_D' && newVal !== 'CN1') {
                newVal = 'INV_D';
            }

            SPECIAL_FOUR_CONFIG.forEach(function(field) {
                let $el = $(field.selector);
                if ($el.is('select')) {
                    $el.val(newVal);
                } else {
                    $el.val(newVal === 'CN1' ? 'CN1' : 'Inv Disc. (D)');
                }
            });

            calculateQuotation();
            toggleRowVisibility();
        }

        // Event listener jab dropdown active ho
        $(document).on('change',
            '#accessories_spl_disc_type, #ceramic_discount_type, #ppf_discount_type, #other_cash_discount_type',
            function() {
                if ($(this).is('select')) {
                    syncSpecialFourDiscountTypes($(this).val());
                }
            });

        // ============================================================
        // GROUP A TYPE -> LINKED DISCOUNT TYPES
        // ============================================================
        function syncGroupALinkedTypes() {
            let groupAType = $('#group_a_type').val() || 'INV_OE';
            if (groupAType === 'INV') groupAType = 'INV_OE';
            if (groupAType === 'CN') groupAType = 'CN1';

            if (groupAType !== 'INV_OE' && groupAType !== 'CN1') {
                return;
            }

            const selectedGroupA = $('#group_a_select').val();
            if (selectedGroupA) {
                $('#' + selectedGroupA + '_type').val(groupAType);
            }

            // 1. Dealer, Accessories Scheme, Shield Scheme ka name change
            const displayLabel = (groupAType === 'INV_OE') ? 'Inv Disc. (OE)' : 'CN1';
            setLinkedSchemeType('#dealer_discount_type', 'dealer_discount_type', displayLabel);
            setLinkedSchemeType('#accessories_discount_type', 'accessories_discount_type', displayLabel);
            setLinkedSchemeType('#shield_scheme_type', 'shield_scheme_type', displayLabel);

            // 2. Naya Rule: Inv Disc. (OE) par dropdown gayab aur Inv Disc. (D) lock
            if (groupAType === 'INV_OE') {
                setSpecialFourAsReadonly();
            } else {
                restoreSpecialFourDropdowns('CN1');
            }

            calculateQuotation();
            toggleRowVisibility();
        }

        // Group A change hote hi function trigger hoga
        $(document).on('change', '#group_a_type', function() {
            syncGroupALinkedTypes();
        });

        // ============================================================
        // Render linked scheme type as plain text/input
        // ============================================================

        function setLinkedSchemeType(selector, id, value) {

            const $old = $(selector);

            if (!$old.length) {
                return;
            }

            // If already converted to input, just update value
            if ($old.is('input')) {
                $old.val(value);
                return;
            }

            // Replace SELECT with INPUT
            const $new = $('<input>', {
                type: 'text',
                id: id,
                name: id,
                value: value,
                readonly: true
            });

            // Keep same styling as quotation grid
            $new.css({
                'width': '100%',
                'border': 'none',
                'background': 'transparent',
                'font-size': '10px',
                'padding': '2px',
                'text-align': 'left',
                'cursor': 'default'
            });

            $old.replaceWith($new);
        }

        // ============================================================
        // 5. POPULATE ACCESSORIES - MAP MOCK CODES TO ACTUAL VALUES
        // ============================================================

        // ============================================================
        // 5. POPULATE ACCESSORIES - SMART MATCH FOR MOCK CODES & REAL PART NUMBERS
        // ============================================================

        function populateAccessories(accessoriesList) {
            if (!accessoriesList || accessoriesList.length === 0) {
                STARTER_PACK_ACCESSORIES = [];
                $('#accessories').val([]).trigger('change');
                return;
            }

            // Deselect all first & clear locks
            $('#accessories option').prop('selected', false).removeAttr('data-locked');
            STARTER_PACK_ACCESSORIES = [];

            accessoriesList.forEach(function(acc) {
                // Handle object or string
                let code = typeof acc === 'object' ? acc.code : acc;
                let name = typeof acc === 'object' ? acc.item : acc;
                let price = typeof acc === 'object' ? acc.mrp : 0;

                let $matchedOption = null;

                // Smart Exact/First Match (Prevents 30+ items getting selected)
                $('#accessories option').each(function() {
                    let optVal = $(this).val();
                    let optText = $(this).text().toLowerCase();
                    if (optVal === code || optText.includes(name.toLowerCase())) {
                        if (!$matchedOption) $matchedOption = $(this); // Select ONLY the first matched item
                    }
                });

                if ($matchedOption) {
                    $matchedOption.prop('selected', true).attr('data-locked', 'true');
                    STARTER_PACK_ACCESSORIES.push($matchedOption.val());
                } else {
                    // Append missing mock item
                    let newOption = new Option(name + ' (₹' + price + ')', code, true, true);
                    $(newOption).attr('data-price', price).attr('data-locked', 'true');
                    $('#accessories').append(newOption);
                    STARTER_PACK_ACCESSORIES.push(code);
                }
            });

            // ✅ Move all selected accessories to TOP
            let $selectedAcc = $('#accessories option:selected');
            $('#accessories').prepend($selectedAcc);

            $('#accessories').trigger('change');
        }

        // ============================================================
        // 6. FETCH MOCK DATA
        // ============================================================

        $('#btnFetchMock').click(function() {
            let no = $('#mock_enquiry_no').val().trim();

            if (!ENQUIRIES[no]) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Enquiry',
                    text: 'Please enter a valid enquiry number (001-013)'
                });
                return;
            }

            let enquiry = ENQUIRIES[no];
            let pricing = PRICING[enquiry.pricingKey];
            currentPricing = pricing;

            // ---- Populate Permit ----
            const PERMIT_MAP = @json($permit_map ?? []);

            $("#permit").empty();
            $("#permit").append('<option value="">Select Permit</option>');

            Object.entries(PERMIT_MAP).forEach(function([key, label]) {

                let selectedPermit = "{{ $quotationData['permit'] ?? '' }}";

                /*
                 * Backward compatibility for old quotations which stored
                 * permit names instead of numeric permit IDs.
                 */
                const legacyPermitMap = {
                    'Private': '1',
                    'Passenger': '9',
                    'Goods': '4'
                };

                if (legacyPermitMap[selectedPermit]) {
                    selectedPermit = legacyPermitMap[selectedPermit];
                }

                let isSel = selectedPermit === key;

                $("#permit").append(
                    `<option value="${key}" ${isSel ? 'selected' : ''}>${label}</option>`
                );
            });

            loadInsuranceByPermit();

            // Mock data (customer/vehicle/pricing) loads as-is from ENQUIRIES,
            // but the enquiry number shown on screen is always forced into the
            // "XENQ-<id>" display format (real URL id takes priority; falls
            // back to the mock number if no real id is present) instead of
            // the mock's own hardcoded enquiry_no (e.g. "ENQ0019").
            let displayEnquiryId = cleanId || no;
            $('#enquiry_id').val(displayEnquiryId ? 'XENQ-' + displayEnquiryId : enquiry.enquiry_no);
            $('#enquiry_no_hidden').val(no);

            console.log('Vehicle from Enquiry:', {
                segment: $('#segment').val(),
                model: $('#model').val(),
                variant: $('#variant').val(),
                color: $('#color').val()
            });

            // $('#oem_code').val(enquiry.vehicle.oem_code);
            // $('#oem_code_hidden').val(enquiry.vehicle.oem_code);
            // ---- Populate Receivables ----
            $('#ex_showroom_price').val(pricing.receivables.exShowroom);
            updateRegistrationAmount();

            // Maxicare
            if (pricing.receivables.maxicare !== undefined && pricing.receivables.maxicare > 0) {
                $('#maxicare').val(pricing.receivables.maxicare).prop('disabled', false);
            } else {
                $('#maxicare').val('N/A').prop('disabled', true);
            }

            // VLTD Device
            if (pricing.receivables.vltd && pricing.receivables.vltd.price > 0) {
                $('#vltd_device').val(pricing.receivables.vltd.price).prop('disabled', false);
            } else {
                $('#vltd_device').val('N/A').prop('disabled', true);
            }

            // Coating
            if (pricing.receivables.coating) {
                let coating = pricing.receivables.coating.find(x => x.default) || pricing.receivables.coating[0];
                $("#coating").val(coating.title);
                $("#coating_price").val(coating.price > 0 ? coating.price : 'N/A');
                $("#coating_price").prop('disabled', coating.price === 0);
            }

            // PPF
            if (pricing.receivables.ppf) {
                let ppf = pricing.receivables.ppf.find(x => x.default) || pricing.receivables.ppf[0];
                $("#ppf").val(ppf.price > 0 ? ppf.price : 'N/A');
                $("#ppf").prop('disabled', ppf.price === 0);
            }

            // RTO Yellow Tape
            if (pricing.receivables["rto-tape"] > 0) {
                $('#rto_yellow_tape').val(pricing.receivables["rto-tape"]).prop('disabled', false);
            } else {
                $('#rto_yellow_tape').val('N/A').prop('disabled', true);
            }

            // Kazam Charging Kit
            if (pricing.receivables.kazam > 0) {
                $('#kazam_charging_kit').val(pricing.receivables.kazam).prop('disabled', false);
            } else {
                $('#kazam_charging_kit').val('N/A').prop('disabled', true);
            }

            // Incidental Charges
            if (pricing.receivables.incidental > 0) {
                $('#incidental_charges').val(pricing.receivables.incidental).prop('disabled', false);
            } else {
                $('#incidental_charges').val('N/A').prop('disabled', true);
            }

            // Shield
            if (pricing.receivables.shield) {
                let shield = pricing.receivables.shield.find(x => x.default) || pricing.receivables.shield[0];
                $('#shield').val(shield.title);
                $('#shield_price').val(shield.price > 0 ? shield.price : 'N/A');
                $('#shield_price').prop('disabled', shield.price === 0);
            }

            // RSA
            if (pricing.receivables.rsa) {
                let rsa = pricing.receivables.rsa.find(x => x.default) || pricing.receivables.rsa[0];
                $('#rsa').val(rsa.title);
                $('#rsa_amount').val(rsa.price > 0 ? rsa.price : 'N/A');
                $('#rsa_amount').prop('disabled', rsa.price === 0);
            }

            // Fastag
            if (pricing.receivables.fastag > 0) {
                $('#fastag').val(pricing.receivables.fastag).prop('disabled', false);
            } else {
                $('#fastag').val('N/A').prop('disabled', true);
            }

            // COD Charges
            if (pricing.receivables.COD > 0) {
                $('#cod_charges').val(pricing.receivables.COD).prop('disabled', false);
            } else {
                $('#cod_charges').val('N/A').prop('disabled', true);
            }

            if (pricing.receivables["charger-swapping"] && pricing.receivables["charger-swapping"].length > 0) {
                $('#charger_swapping').empty().prop('disabled', false);
                pricing.receivables["charger-swapping"].forEach(function(item) {
                    let selected = item.default ? 'selected' : '';
                    $('#charger_swapping').append(
                        `<option value="${item.title}" data-amount="${item.amount}" ${selected}>${item.title}</option>`
                    );
                    if (item.default) {
                        $('#charger_swapping_amount').val(item.amount > 0 ? item.amount : 'N/A');
                        $('#charger_swapping_amount').prop('disabled', item.amount === 0);
                    }
                });
                $('#charger_swapping_discount').prop('disabled', false);
                $('#charger_swapping_discount_type').prop('disabled', false);
                $('#charger_swapping_option').prop('disabled', false);
            } else {
                $('#charger_swapping').val('N/A').prop('disabled', true);
                $('#charger_swapping_amount').val('N/A').prop('disabled', true);
                $('#charger_swapping_discount').val('N/A').prop('disabled', true);
                $('#charger_swapping_discount_type').val('').prop('disabled', true);
                $('#charger_swapping_option').val('').prop('disabled', true);
                $('#charger_swapping_option_print').text('');
            }

            $('#tcs').val('N/A').prop('disabled', true);

            if (pricing.receivables.accessories && pricing.receivables.accessories.length > 0) {
                populateAccessories(pricing.receivables.accessories);
            } else {
                $('#accessories').val([]).trigger('change');
            }

            renderGroupADiscounts(pricing);

            const groupASelected = $('#group_a_select').val();
            const groupAAmount = $('#group_a_amount').val();
            const groupAType = $('#group_a_type').val();
            if (groupASelected && groupAAmount) {
                $('#' + groupASelected).val(groupAAmount);
                $('#' + groupASelected + '_type').val(groupAType);
            }

            if (pricing.deductibles["dealer-scheme"]) {
                let val = pricing.deductibles["dealer-scheme"].amount;

                $('#dealer_discount').val(
                    val > 0 ? val : 'N/A'
                );
            }

            if (pricing.deductibles["accessory-scheme"]) {
                let val = pricing.deductibles["accessory-scheme"].amount;

                $('#accessories_discount').val(
                    val > 0 ? val : 'N/A'
                );
            }

            if (pricing.deductibles["shield-scheme"]) {
                let val = pricing.deductibles["shield-scheme"].amount;

                $('#shield_scheme').val(
                    val > 0 ? val : 'N/A'
                );
            }

            syncGroupALinkedTypes();
            if (pricing.deductibles["corp-scheme"] && pricing.deductibles["corp-scheme"].length > 0) {
                let corp = pricing.deductibles["corp-scheme"].find(x => x.name === "Corporate Discount") || pricing
                    .deductibles["corp-scheme"][0];
                if (corp && corp.name === "Corporate Discount") {
                    $('#group_b_type').val('Inv Disc.');
                    $('#corporate_discount_type').val(corp.type || 'INV');
                    $('#group_b_amount').val(corp.amount).trigger('keyup');
                }

                let loyalty = pricing.deductibles["corp-scheme"].find(x => x.name === "Loyalty Bonus");
                if (loyalty) {
                    $('#group_c_select').val('loyalty_bonus').trigger('change');
                    $('#group_c_type').val(loyalty.type || 'CN1');
                    $('#group_c_amount').val(loyalty.amount).trigger('keyup');
                }
            }
            if (pricing.deductibles["exchange-scheme"] && pricing.deductibles["exchange-scheme"].length > 0) {
                let exch = pricing.deductibles["exchange-scheme"][0];
                $('#group_c_select').val(exch.name ? exch.name.toLowerCase().replace(' ', '_') : 'exchange_bonus')
                    .trigger('change');
                $('#group_c_type').val(exch.type || 'CN1');
                $('#group_c_amount').val(exch.amount).trigger('keyup');
            }
            if (pricing.deductibles["accessories-spl-discount"]) {
                let val = pricing.deductibles["accessories-spl-discount"].amount;
                $('#accessories_spl_disc').val(val > 0 ? val : 'N/A');
            }
            if (pricing.deductibles["coating-spl-discount"]) {
                let val = pricing.deductibles["coating-spl-discount"].amount;
                $('#ceramic_discount').val(val > 0 ? val : 'N/A');
            }
            if (pricing.deductibles["ppf-spl-discount"]) {
                let val = pricing.deductibles["ppf-spl-discount"].amount;
                $('#ppf_discount').val(val > 0 ? val : 'N/A');
            }

            if (pricing.deductibles["charger-swapping-discount"]) {
                let val = pricing.deductibles["charger-swapping-discount"].amount;
                $('#charger_swapping_discount').val(val > 0 ? val : 'N/A');
                $('#charger_swapping_discount_type').val(pricing.deductibles["charger-swapping-discount"].type);

                updateChargerSwappingPrint();
            }
            if (pricing.deductibles["other-cash-discount"]) {
                let val = pricing.deductibles["other-cash-discount"].amount;
                $('#other_cash_discount').val(val > 0 ? val : 'N/A');
            }

            let specialType = pricing.deductibles["accessories-spl-discount"]?.type || 'INV';
            syncSpecialFourDiscountTypes(specialType);

            if (pricing.deductibles["special-cash-discount"]) {
                let val = pricing.deductibles["special-cash-discount"].amount;
                $('#special_cash_discount').val(val > 0 ? val : 'N/A');
                $('#special_cash_discount_type').val('Inv Disc. (D)');
            }

            toggleRowVisibility();

            calculateQuotation();

            checkConditionalFields();

        });

        // ---- Reset Mock Data ----
        $('#btnResetMock').click(function() {
            $('#mock_enquiry_no').val('');
            // $('#customer_name').val('');
            // $('#mobile').val('');
            $('#careof').val('').trigger('change');
            $('#careofname').val('');
            // $('#segment').val('');
            // $('#model').val('');
            // $('#variant').val('');
            // $('#color').val('');
            $('#oem_code').val('');
            $('#oem_code_hidden').val('');
            $('#ex_showroom_price').val('');
            $('#insurance_amount').val('');
            $('#registration_amount').val('');
            $('#accessories_amount').val('0.00');
            $('#maxicare').val('');
            $('#vltd_device').val('');
            $('#coating_price').val('');
            $('#ppf').val('');
            $('#rto_yellow_tape').val('');
            $('#kazam_charging_kit').val('');
            $('#incidental_charges').val('');
            $('#shield_price').val('');
            $('#rsa_amount').val('');
            $('#fastag').val('');
            $('#cod_charges').val('');
            $('#charger_swapping_amount').val('');
            $('#tcs').val('');
            $('#total_receivable').val('');
            $('#total_discount_amount').val('');
            $('#net_receivable_summary').val('');
            $('#fi_total_receivable').val('');
            $('#less_inv_discount').val('');
            $('#finvoice_amount').val('');
            $('#invoiced_discount').val('');
            $('#credit_note_discount').val('');
            $('#total_discount_summary').val('');
            $('#dealer_discount').val('');
            $('#accessories_discount').val('');
            $('#shield_scheme').val('');
            $('#group_b_type').val('Inv Disc.');
            $('#group_b_amount').val('');
            $('#special_cash_discount_type').val('Inv Disc. (D)');
            $('#special_cash_discount').val('');
            $('#accessories_spl_disc').val('');
            $('#ceramic_discount').val('');
            $('#ppf_discount').val('');
            $('#charger_swapping_discount').val('');
            $('#other_cash_discount').val('');
            $('#special_cash_discount').val('');
            syncGroupALinkedTypes();
            $('#insurance_covers').empty();
            $('#insurance_print').text('');
            $('#accessories_print').text('');
            $('#accessories').val([]).trigger('change');
            $('#permit').empty().append('<option value="">Select Permit</option>');
            $('#insurance_company').empty().append('<option value="">Select Company</option>');
            $('#group_a_dynamic_container').empty();

            // Show all rows again
            $('.price-grid tbody tr, .discount-grid tbody tr').show();

            calculateQuotation();
            checkConditionalFields();
        });

        // ============================================================
        // 7. EVENT HANDLERS
        // ============================================================

        // ---- Permit change ----
        $("#permit").on("change", function() {
            loadInsuranceByPermit();
            updateRegistrationAmount();
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Registration Type change ----
        $('#registration_no_type, #registration_category').on('change', function() {
            let hasValue = $(this).val() !== '';
            $('#registration_amount')
                .prop('disabled', !hasValue)
                .val(hasValue ? $('#registration_amount').val() : '');
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Insurance Company change ----
        $("#insurance_company").on("change", function() {
            let companyName = $(this).val();
            if (!companyName || !currentInsurance) return;
            let company = currentInsurance.companies.find(x => x.insCo === companyName);
            if (company) {
                loadInsurance(company);
            }
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Insurance Covers change ----
        $("#insurance_covers").on("change", function() {
            let total = 0;
            $('#insurance_covers option:selected').each(function() {
                total += Number($(this).data('price') || 0);
            });
            $('#insurance_amount').val(total);
            updateInsurancePrintText();
            calculateQuotation();
        });

        // ---- Accessories change ----
        $('#accessories').on('change', function() {
            // ✅ FIX: Skip recalculation during the initial restore in edit mode
            if (accessoriesRestoreInProgress) {
                updateAccessoriesPrintText();
                toggleRowVisibility();
                return;
            }

            updateAccessoriesAmount();
            updateAccessoriesPrintText();
            toggleRowVisibility();
        });

        // ---- Numeric-only filter ----
        $(document).on('input', '.numeric-only', function() {
            let value = $(this).val();
            value = value.replace(/[^\d.]/g, '');
            value = value.replace(/(\..*)\./g, '$1');
            $(this).val(value);
        });

        // ---- Coating change ----
        $('#coating').on('change', function() {
            let value = $(this).val();
            if (value === '' || value === 'No Coating') {
                $('#coating_price').val('N/A').prop('disabled', true);
                $('#ceramic_discount').val('N/A').prop('disabled', true);
                $('#ceramic_discount_type').val('').prop('disabled', true);
            } else {
                $('#coating_price').val('').prop('disabled', false);
                $('#ceramic_discount').val('').prop('disabled', false);
                $('#ceramic_discount_type').prop('disabled', false);
            }
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Shield change ----
        $('#shield').on('change', function() {
            let value = $(this).val();
            if (value === '' || value === 'No Shield') {
                $('#shield_price').val('N/A').prop('disabled', true);
            } else {
                $('#shield_price').val('').prop('disabled', false);
            }
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- RSA change ----
        $('#rsa').on('change', function() {
            let value = $(this).val();
            if (value === '' || value === 'No RSA') {
                $('#rsa_amount').val('N/A').prop('disabled', true);
            } else {
                $('#rsa_amount').val('').prop('disabled', false);
            }
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Charger Swapping change ----
        $('#charger_swapping').on('change', function() {
            let selectedOption = $(this).find('option:selected');
            let amount = selectedOption.data('amount');
            if (amount !== undefined && amount > 0) {
                $('#charger_swapping_amount').val(amount).prop('disabled', false);
                $('#charger_swapping_discount').prop('disabled', false);
                $('#charger_swapping_discount_type').prop('disabled', false);
                $('#charger_swapping_option').prop('disabled', false);
            } else {
                $('#charger_swapping_amount').val('N/A').prop('disabled', true);
                $('#charger_swapping_discount').val('N/A').prop('disabled', true);
                $('#charger_swapping_discount_type').val('').prop('disabled', true);
            }
            calculateQuotation();
            toggleRowVisibility();
        });

        // ---- Update Coating Discount Label ----
        function updateCoatingDiscountLabel() {
            let coating = $('#coating').val();
            let label = 'Coating Special Discount';
            if (coating === 'Ceramic') {
                label = 'Ceramic Coating Special Discount';
            } else if (coating === 'Graphene') {
                label = 'Graphene Coating Special Discount';
            }
            $('#coating_discount_label').text(label);
        }
        $(document).on('change', '#coating', updateCoatingDiscountLabel);

        // ============================================================
        // 8. GROUP DISCOUNT SETUP
        // ============================================================

        // ============================================================
        // 8. GROUP DISCOUNT SETUP - FIXED
        // ============================================================

        function setupGroupDiscount(groupPrefix, fieldNames) {
            function sync() {
                let selected = $('#' + groupPrefix + '_select').val();
                let type = $('#' + groupPrefix + '_type').val();
                let amount = $('#' + groupPrefix + '_amount').val();

                fieldNames.forEach(function(name) {
                    if (name === selected) {
                        $('#' + name).val(amount);
                        $('#' + name + '_type').val(type);
                    } else {
                        $('#' + name).val('');
                        $('#' + name + '_type').val('');
                    }
                });
                calculateQuotation();
                toggleRowVisibility();
            }

            $(document).on('change', '#' + groupPrefix + '_select', sync);
            $(document).on('change', '#' + groupPrefix + '_type', sync);
            $(document).on('keyup change', '#' + groupPrefix + '_amount', sync);
        }

        // Setup Group A - Cash Scheme OEM, CSD Discount, Fame Subsidy
        setupGroupDiscount('group_a', ['cash_scheme_oem', 'csd_discount', 'fame_subsidy']);

        // Group B - Corporate Discount ONLY (static, no dropdown)
        $(document).on('keyup change', '#group_b_amount', function() {
            let amount = $(this).val();
            $('#corporate_discount').val(amount);
            $('#corporate_discount_type').val($('#group_b_type').val());
            calculateQuotation();
            toggleRowVisibility();
        });

        // Setup Group C - Exchange Bonus, Green Bonus, Welcome Bonus, Loyalty Bonus
        setupGroupDiscount('group_c', ['exchange_bonus', 'green_bonus', 'welcome_bonus', 'loyalty_bonus']);

        // Group A Select change - rebuild type field
        // ============================================================
        // GROUP A SELECT CHANGE
        // ============================================================

        // ============================================================
        // GROUP A SELECT CHANGE
        // ============================================================

        $('#group_a_select').on('change', function() {
            const value = $(this).val();
            let currentType = 'INV';

            if (value === 'cash_scheme_oem') {
                currentType = $('#cash_scheme_oem_type').val() || 'INV';
            } else if (value === 'csd_discount') {
                currentType = $('#csd_discount_type').val() || 'INV';
            } else if (value === 'fame_subsidy') {
                currentType = $('#fame_subsidy_type').val() || 'INV';
            }

            renderGroupAType(value, currentType);
            syncGroupALinkedTypes();

            $('#group_a_amount').trigger('change');
        });
        // ============================================================
        // ============================================================
        // 9. CALCULATION FUNCTIONS - MATCHING EXCEL EXACTLY
        // ============================================================

        function num(id) {
            let value = $('#' + id).val();
            if (value === 'N/A' || value === '' || value == null) {
                return 0;
            }
            // Check if the field is frozen (disabled with class 'frozen-field')
            let $field = $('#' + id);
            if ($field.prop('disabled') && $field.hasClass('frozen-field')) {
                return 0; // Frozen fields do NOT participate in calculations
            }
            // Check if the field is disabled for other reasons
            if ($field.prop('disabled')) {
                return 0;
            }
            return parseFloat(value) || 0;
        }

        // All discount fields with their type fields - matches Excel D25:D41
        const DISCOUNT_TYPE_PAIRS = [
            ['cash_scheme_oem', 'cash_scheme_oem_type'],
            ['csd_discount', 'csd_discount_type'],
            ['fame_subsidy', 'fame_subsidy_type'],
            ['dealer_discount', 'dealer_discount_type'],
            ['accessories_discount', 'accessories_discount_type'],
            ['shield_scheme', 'shield_scheme_type'],
            ['corporate_discount', 'corporate_discount_type'],
            ['loyalty_bonus', 'loyalty_bonus_type'],
            ['exchange_bonus', 'exchange_bonus_type'],
            ['green_bonus', 'green_bonus_type'],
            ['welcome_bonus', 'welcome_bonus_type'],
            ['accessories_spl_disc', 'accessories_spl_disc_type'],
            ['ceramic_discount', 'ceramic_discount_type'],
            ['ppf_discount', 'ppf_discount_type'],
            ['charger_swapping_discount', 'charger_swapping_discount_type'],
            ['other_cash_discount', 'other_cash_discount_type'],
            ['special_cash_discount', 'special_cash_discount_type']
        ];

        function calculateDiscountBifurcation() {
            let bif = calculateDiscountBifurcationByType();
            return {
                invoicedDiscount: bif.totalInvSide,
                creditNoteDiscount: bif.totalCNSide
            };
        }

        function calculateQuotation() {
            let subtotal =
                num('ex_showroom_price') +
                num('insurance_amount') +
                num('registration_amount') +
                num('accessories_amount') +
                num('maxicare') +
                num('vltd_device') +
                num('coating_price') +
                num('ppf') +
                num('rto_yellow_tape') +
                num('kazam_charging_kit') +
                num('incidental_charges') +
                num('shield_price') +
                num('rsa_amount') +
                num('fastag') +
                num('cod_charges') +
                num('charger_swapping_amount');

            $('#subtotal_value').val(subtotal.toFixed(2));

            let bif = calculateDiscountBifurcationByType();
            let exShowroom = num('ex_showroom_price');
            let invoiceAmount = exShowroom - bif.totalInvSide;

            $('#invoice_amount_display').val(invoiceAmount.toFixed(2));
            $('#invoice_amount').val(invoiceAmount.toFixed(2));

            let tcs = 0;
            if (invoiceAmount >= 1000000) {
                tcs = invoiceAmount * 0.01;
                $('#tcs').val(tcs.toFixed(2)).prop('readonly', true).prop('disabled', false);
            } else {
                $('#tcs').val('N/A').prop('readonly', true).prop('disabled', true);
            }

            let totalReceivable = subtotal + tcs;
            $('#total_receivable').val(totalReceivable.toFixed(2));

            let totalDiscount = bif.totalInvSide + bif.totalCNSide;
            $('#total_discount_amount').val(totalDiscount.toFixed(2));
            $('#total_discount').val(totalDiscount.toFixed(2));

            let netReceivable = totalReceivable - totalDiscount;
            $('#net_receivable_summary').val(netReceivable.toFixed(2));
            $('#net_receivable_words').text(numberToIndianWords(netReceivable));

            // Display in 6-column bifurcation box
            $('#inv_discount_display').val(bif.inv.toFixed(2));
            $('#inv_oe_discount_display').val(bif.inv_oe.toFixed(2));
            $('#inv_d_discount_display').val(bif.inv_d.toFixed(2));
            $('#cn1_discount_display').val(bif.cn1.toFixed(2));
            $('#cn2_discount_display').val(bif.cn2.toFixed(2));
            $('#cn3_discount_display').val(bif.cn3.toFixed(2));
            $('#total_discount_bifurcation_display').val(totalDiscount.toFixed(2));

            // Set Hidden Summaries
            $('#invoiced_discount_summary').val(bif.totalInvSide.toFixed(2));
            $('#inv_oe_discount_summary').val(bif.inv_oe.toFixed(2));
            $('#inv_d_discount_summary').val(bif.inv_d.toFixed(2));
            $('#credit_note_discount_summary').val(bif.totalCNSide.toFixed(2));
            $('#cn1_discount_summary').val(bif.cn1.toFixed(2));
            $('#cn2_discount_summary').val(bif.cn2.toFixed(2));
            $('#cn3_discount_summary').val(bif.cn3.toFixed(2));

            toggleRowVisibility();
        }

        function calculateBankQuotation() {

            // ============================================
            // BANK QUOTATION
            // ONLY INV DISCOUNTS ARE CONSIDERED
            // ============================================

            function bankNum(id) {
                const value = $('#' + id).val();

                if (
                    value === undefined ||
                    value === null ||
                    value === '' ||
                    value === 'N/A'
                ) {
                    return 0;
                }

                const number = parseFloat(
                    value.toString().replace(/,/g, '')
                );

                return isNaN(number) ? 0 : number;
            }

            // --------------------------------------------
            // 1. Subtotal
            // Same additions as normal quotation
            // --------------------------------------------

            let subtotal =
                bankNum('ex_showroom_price') +
                bankNum('insurance_amount') +
                bankNum('registration_amount') +
                bankNum('accessories_amount') +
                bankNum('maxicare') +
                bankNum('vltd_device') +
                bankNum('coating_price') +
                bankNum('ppf') +
                bankNum('rto_yellow_tape') +
                bankNum('kazam_charging_kit') +
                bankNum('incidental_charges') +
                bankNum('shield_price') +
                bankNum('rsa_amount') +
                bankNum('fastag') +
                bankNum('cod_charges') +
                bankNum('charger_swapping_amount');

            // --------------------------------------------
            // 2. ONLY INV DISCOUNTS
            // --------------------------------------------

            const bankDiscountPairs = [
                ['cash_scheme_oem', 'cash_scheme_oem_type'],
                ['csd_discount', 'csd_discount_type'],
                ['fame_subsidy', 'fame_subsidy_type'],
                ['dealer_discount', 'dealer_discount_type'],
                ['accessories_discount', 'accessories_discount_type'],
                ['shield_scheme', 'shield_scheme_type'],
                ['corporate_discount', 'corporate_discount_type'],
                ['loyalty_bonus', 'loyalty_bonus_type'],
                ['exchange_bonus', 'exchange_bonus_type'],
                ['green_bonus', 'green_bonus_type'],
                ['welcome_bonus', 'welcome_bonus_type'],
                ['accessories_spl_disc', 'accessories_spl_disc_type'],
                ['ceramic_discount', 'ceramic_discount_type'],
                ['ppf_discount', 'ppf_discount_type'],
                ['charger_swapping_discount', 'charger_swapping_discount_type'],
                ['other_cash_discount', 'other_cash_discount_type'],
                ['special_cash_discount', 'special_cash_discount_type']
            ];

            let totalBankInvDiscount = 0;

            bankDiscountPairs.forEach(function(pair) {
                const amount = bankNum(pair[0]);
                let type = $('#' + pair[1]).val() || '';

                if (type === 'Inv Disc.' || type === 'Inv. Disc.' || type === 'INV') {
                    type = 'INV';
                }

                if (type === 'Inv Disc. (OE)' || type === 'INV_OE') {
                    type = 'INV_OE';
                }

                if (type === 'Inv Disc. (D)' || type === 'INV_D') {
                    type = 'INV_D';
                }

                if (type === 'CN1' || type === 'CN2' || type === 'CN3') {
                    // Credit Note — bank calculation mein include nahi hoga
                }

                if (type === 'INV' || type === 'INV_OE' || type === 'INV_D') {
                    totalBankInvDiscount += amount;
                }
            });

            // --------------------------------------------
            // 3. Invoice Amount
            // EX-SHOWROOM - ONLY INV DISCOUNT
            // --------------------------------------------

            const exShowroom = bankNum('ex_showroom_price');

            const bankInvoiceAmount =
                exShowroom - totalBankInvDiscount;

            // --------------------------------------------
            // 4. TCS
            // Same existing rule
            // --------------------------------------------

            const tcsBaseAmount = bankInvoiceAmount;

            let bankTcs = 0;

            if (tcsBaseAmount >= 1000000) {
                bankTcs = tcsBaseAmount * 0.01;
            }

            // --------------------------------------------
            // 5. Total Receivable
            // --------------------------------------------

            const bankTotalReceivable =
                subtotal + bankTcs;

            // --------------------------------------------
            // 6. Bank Net Receivable
            //
            // IMPORTANT:
            // Bank quotation uses ONLY INV discount
            // --------------------------------------------

            const bankNetReceivable =
                bankTotalReceivable - totalBankInvDiscount;

            // --------------------------------------------
            // 7. Update existing display fields
            // --------------------------------------------

            $('#subtotal_value').val(
                subtotal.toFixed(2)
            );

            $('#invoice_amount_display').val(
                bankInvoiceAmount.toFixed(2)
            );

            $('#invoice_amount').val(
                bankInvoiceAmount.toFixed(2)
            );

            if (bankTcs > 0) {
                $('#tcs')
                    .val(bankTcs.toFixed(2))
                    .prop('readonly', true)
                    .prop('disabled', false);
            } else {
                $('#tcs')
                    .val('N/A')
                    .prop('readonly', true)
                    .prop('disabled', true);
            }

            $('#total_receivable').val(
                bankTotalReceivable.toFixed(2)
            );

            $('#total_discount_amount').val(
                totalBankInvDiscount.toFixed(2)
            );

            $('#total_discount').val(
                totalBankInvDiscount.toFixed(2)
            );

            $('#net_receivable_summary').val(
                bankNetReceivable.toFixed(2)
            );

            $('#net_receivable_words').text(
                numberToIndianWords(bankNetReceivable)
            );

            // --------------------------------------------
            // Debug
            // --------------------------------------------

            console.log('=== BANK QUOTATION ===');
            console.log('Subtotal:', subtotal);
            console.log('INV Discount:', totalBankInvDiscount);
            console.log('Invoice Amount:', bankInvoiceAmount);
            console.log('TCS:', bankTcs);
            console.log('Total Receivable:', bankTotalReceivable);
            console.log('Bank Net Receivable:', bankNetReceivable);
        }

        // ---- Event Listeners for recalculation ----
        $(document).on('keyup change',
            '#ex_showroom_price, #insurance_amount, #registration_amount, #accessories_amount, ' +
            '#maxicare, #vltd_device, #coating_price, #ppf, #rto_yellow_tape, #kazam_charging_kit, ' +
            '#incidental_charges, #shield_price, #rsa_amount, #fastag, #cod_charges, #charger_swapping_amount, ' +
            '#dealer_discount, #accessories_discount, #ceramic_discount, #ppf_discount, ' +
            '#charger_swapping_discount, #shield_scheme, #accessories_spl_disc, #other_cash_discount, ' +
            '#special_cash_discount',
            function() {
                calculateQuotation();
            }
        );

        // ---- Also trigger on TYPE dropdown changes ----
        $(document).on('change',
            '#dealer_discount_type, #accessories_discount_type, #shield_scheme_type, ' +
            '#accessories_spl_disc_type, #ceramic_discount_type, #ppf_discount_type, ' +
            '#charger_swapping_discount_type, #other_cash_discount_type, #special_cash_discount_type',
            function() {
                calculateQuotation();
            }
        );


        const STATIC_DISCOUNT_FIELDS = [{
                amount: 'dealer_discount',
                type: 'dealer_discount_type'
            },
            {
                amount: 'accessories_discount',
                type: 'accessories_discount_type'
            },
            {
                amount: 'shield_scheme',
                type: 'shield_scheme_type'
            },
            {
                amount: 'accessories_spl_disc',
                type: 'accessories_spl_disc_type'
            },
            {
                amount: 'ceramic_discount',
                type: 'ceramic_discount_type'
            },
            {
                amount: 'ppf_discount',
                type: 'ppf_discount_type'
            },
            {
                amount: 'charger_swapping_discount',
                type: 'charger_swapping_discount_type'
            },
            {
                amount: 'other_cash_discount',
                type: 'other_cash_discount_type'
            },
            {
                amount: 'special_cash_discount',
                type: 'special_cash_discount_type'
            }
        ];

        // Add event listeners for static discount fields
        STATIC_DISCOUNT_FIELDS.forEach(function(field) {
            $(document).on('keyup change', '#' + field.amount, function() {
                // Check if field is frozen
                if ($(this).hasClass('frozen-field') && $(this).prop('disabled')) {
                    return; // Don't calculate if frozen
                }
                calculateQuotation();
                toggleRowVisibility();
            });
            $(document).on('change', '#' + field.type, function() {
                calculateQuotation();
                toggleRowVisibility();
            });
        });

        // ============================================================
        // 10. PRINT FUNCTIONS
        // ============================================================

        let printLabelRestoreList = [];
        let bankQuotationRestoreData = null;

        function saveQuotationValuesBeforeBankPrint() {

            bankQuotationRestoreData = {
                subtotal_value: $('#subtotal_value').val(),
                invoice_amount_display: $('#invoice_amount_display').val(),
                invoice_amount: $('#invoice_amount').val(),
                tcs: $('#tcs').val(),
                total_receivable: $('#total_receivable').val(),
                total_discount_amount: $('#total_discount_amount').val(),
                total_discount: $('#total_discount').val(),
                net_receivable_summary: $('#net_receivable_summary').val(),
                net_receivable_words: $('#net_receivable_words').text(),

                tcs_disabled: $('#tcs').prop('disabled'),
                tcs_readonly: $('#tcs').prop('readonly')
            };
        }


        function restoreQuotationValuesAfterBankPrint() {

            if (!bankQuotationRestoreData) {
                return;
            }

            $('#subtotal_value').val(
                bankQuotationRestoreData.subtotal_value
            );

            $('#invoice_amount_display').val(
                bankQuotationRestoreData.invoice_amount_display
            );

            $('#invoice_amount').val(
                bankQuotationRestoreData.invoice_amount
            );

            $('#tcs')
                .val(bankQuotationRestoreData.tcs)
                .prop('disabled', bankQuotationRestoreData.tcs_disabled)
                .prop('readonly', bankQuotationRestoreData.tcs_readonly);

            $('#total_receivable').val(
                bankQuotationRestoreData.total_receivable
            );

            $('#total_discount_amount').val(
                bankQuotationRestoreData.total_discount_amount
            );

            $('#total_discount').val(
                bankQuotationRestoreData.total_discount
            );

            $('#net_receivable_summary').val(
                bankQuotationRestoreData.net_receivable_summary
            );

            $('#net_receivable_words').text(
                bankQuotationRestoreData.net_receivable_words
            );

            bankQuotationRestoreData = null;
        }

        function prepareOptionLabelsForPrint() {
            printLabelRestoreList = [];

            $('.quotation-grid td.cell-option select').not('#accessories').each(function() {
                let $select = $(this);
                let selectId = $select.attr('id');

                // Registration Dropdowns aur Insurance Covers ko generic loop se exclude karein
                if (
                    selectId === 'insurance_covers' ||
                    selectId === 'registration_no_type' ||
                    selectId === 'registration_category'
                ) {
                    return;
                }

                let selectedText = $select.find('option:selected').first().text().trim();

                if (!selectedText || selectedText.toLowerCase() === 'select') {
                    return;
                }

                let $label = $select.closest('tr').find('td.cell-label').first();

                printLabelRestoreList.push({
                    el: $label,
                    html: $label.html()
                });

                if (selectId === 'insurance_company') {
                    $label.html('Insurance');
                    $label.append(' (' + $select.val() + ')');
                    return;
                }

                $label.append(' (' + selectedText + ')');
            });
        }

        function restoreOptionLabelsAfterPrint() {
            printLabelRestoreList.forEach(function(item) {
                item.el.html(item.html);
            });
            printLabelRestoreList = [];
        }

        function isEmptyGridValue(value) {
            value = (value || '').toString().trim();
            return (value === '' || value === '0' || value === '0.00' || value === 'N/A');
        }

        function prepareItemVisibilityForPrint() {
            $('.quotation-grid tbody tr').each(function() {
                let $row = $(this);
                let amountCells = $row.find('td.cell-amount');
                let priceValue = amountCells.eq(0).find('input').val();
                let discountValue = amountCells.length > 1 ? amountCells.eq(1).find('input').first().val() : '';

                // Hide empty / 0 / N/A rows
                if (isEmptyGridValue(priceValue) && isEmptyGridValue(discountValue)) {
                    $row.addClass('print-hide');
                } else {
                    $row.removeClass('print-hide');
                }

                // Hide conditional discounts when they are frozen/not eligible
                if (
                    $row.find('#accessories_discount').hasClass('frozen-field') ||
                    $row.find('#shield_scheme').hasClass('frozen-field')
                ) {
                    $row.addClass('print-hide');
                }
            });
        }

        function prepareBankItemVisibilityForPrint() {

            // 1. Saari rows se pehle print-hide hatao
            $('.quotation-grid tbody tr').removeClass('print-hide');

            // 2. Price Table Rows: Empty/0/N/A ko hide karo
            $('.price-grid tbody tr:not(.total-row)').each(function() {
                const $row = $(this);
                const amount = $row.find('td.cell-amount input').first().val();
                if (isEmptyGridValue(amount)) {
                    $row.addClass('print-hide');
                }
            });

            // 3. Discount Table Rows: 
            // - CN1, CN2, CN3 -> HIDE
            // - INV, INV_OE, INV_D -> SHOW (agar amount > 0 ho)
            $('.discount-grid tbody tr:not(.total-row)').each(function() {
                const $row = $(this);
                const amount = $row.find('td.cell-amount input').first().val();
                let rawType = $row.find('td.cell-type select, td.cell-type input').first().val() || '';

                // Type string normalize karein
                let normalizedType = rawType.toString().trim();
                if (normalizedType === 'Inv Disc.' || normalizedType === 'Inv. Disc.' || normalizedType === 'INV') {
                    normalizedType = 'INV';
                } else if (normalizedType === 'Inv Disc. (OE)' || normalizedType === 'INV_OE') {
                    normalizedType = 'INV_OE';
                } else if (normalizedType === 'Inv Disc. (D)' || normalizedType === 'INV_D') {
                    normalizedType = 'INV_D';
                } else if (normalizedType === 'CN' || normalizedType === 'CN1') {
                    normalizedType = 'CN1';
                }

                // CN Discount Types (CN1, CN2, CN3) ya Empty Amount -> HIDE
                const isCreditNote = (normalizedType === 'CN1' || normalizedType === 'CN2' || normalizedType ===
                    'CN3');

                if (isCreditNote || isEmptyGridValue(amount)) {
                    $row.addClass('print-hide');
                } else {
                    $row.removeClass('print-hide');
                }
            });
            $('#accessories_discount, #shield_scheme').each(function() {
                if ($(this).hasClass('frozen-field')) {
                    $(this).closest('tr').addClass('print-hide');
                }
            });
        }

        function restoreBankItemVisibilityAfterPrint() {
            $('.quotation-grid tbody tr').removeClass('print-hide');
        }

        function restoreItemVisibilityAfterPrint() {
            $('.quotation-grid tbody tr').removeClass('print-hide');
        }

        function updateCareOfPrint() {
            const relationText = $('#careof option:selected').text().trim();
            const name = $('#careofname').val().trim();

            if (relationText && relationText !== 'Please Select...') {
                $('#careof_relation_print').text(relationText);
            } else {
                $('#careof_relation_print').text('Care Of');
            }

            $('#careof_name_print').text(name);
        }

        function updateChargerSwappingPrint() {
            const selectedText = $('#charger_swapping_option option:selected').text().trim();

            $('#charger_swapping_option_print').text(selectedText);
        }

        $(document).on('change', '#charger_swapping_option', function() {
            updateChargerSwappingPrint();
        });

        $(document).on('change keyup', '#careof, #careofname', function() {
            updateCareOfPrint();
        });

        function printQuotation() {
            prepareOptionLabelsForPrint();
            prepareItemVisibilityForPrint();
            updateCareOfPrint();
            updateChargerSwappingPrint();
            const modelName = ($('#model').val() || 'Quotation')
                .toString()
                .trim();

            const safeModelName = modelName
                .replace(/[\\/:*?"<>|]/g, '')
                .replace(/\s+/g, ' ')
                .trim();

            const originalTitle = document.title;

            document.title = `Vehicle Quotation - ${safeModelName || 'Quotation'}`;

            window.print();

            setTimeout(function() {
                document.title = originalTitle;

                restoreOptionLabelsAfterPrint();
                restoreItemVisibilityAfterPrint();
            }, 500);
        }

        function printBankQuotation() {

            saveQuotationValuesBeforeBankPrint();

            // Bank print mode
            document.body.classList.add('bank-print');

            // Calculate Bank quotation using ONLY INV discounts
            calculateBankQuotation();

            // Prepare the same existing print layout
            prepareOptionLabelsForPrint();
            prepareBankItemVisibilityForPrint();
            updateCareOfPrint();

            const modelName = ($('#model').val() || 'Quotation')
                .toString()
                .trim();

            const safeModelName = modelName
                .replace(/[\\/:*?"<>|]/g, '')
                .replace(/\s+/g, ' ')
                .trim();

            const originalTitle = document.title;

            document.title =
                `Vehicle Quotation - ${safeModelName || 'Quotation'} (For Bank)`;

            window.print();

            setTimeout(function() {

                document.title = originalTitle;

                restoreOptionLabelsAfterPrint();
                restoreBankItemVisibilityAfterPrint();

                // Restore normal customer quotation values
                restoreQuotationValuesAfterBankPrint();

                // Return to normal customer quotation
                document.body.classList.remove('bank-print');

            }, 500);
        }

        $(document).ready(function() {
            $('form').on('submit', function(e) {

                var covers = [];

                $('#insurance_covers option:selected').each(function() {

                    // IMPORTANT:
                    // Use attr() because data-price is stored using attr()
                    var price = Number($(this).attr('data-price') || 0);

                    var name = $(this).val();

                    covers.push({
                        name: name,
                        price: price
                    });
                });

                $('#insurance_covers_data').val(JSON.stringify(covers));
            });
        });

        // ============================================================
        // 11. DOCUMENT READY
        // ============================================================

        // ============================================================
        // 11. DOCUMENT READY
        // ============================================================

        $(document).ready(function() {

            syncGroupALinkedTypes();

            // Initialize Select2 for Accessories
            $('#accessories').select2({
                placeholder: 'Search & select accessories...',
                width: '100%',
                closeOnSelect: false,
                allowClear: false,
                sorter: function(data) {
                    var selected = [];
                    var notSelected = [];

                    data.forEach(function(item) {
                        if (item.selected) {
                            selected.push(item);
                        } else {
                            notSelected.push(item);
                        }
                    });

                    return selected.concat(notSelected);
                },
                templateResult: function(data) {
                    if (data.loading) return data.text;

                    var $container = $('<span class="select2-checkbox-label"></span>');
                    var $checkbox = $('<input type="checkbox" class="select2-checkbox">');

                    if (data.element && data.element.selected) {
                        $checkbox.prop('checked', true);
                    }

                    $container.append($checkbox);
                    $container.append($(document.createTextNode(' ' + data.text)));

                    return $container;
                },
                templateSelection: function(data) {
                    return data.text || 'Search & select accessories...';
                }
            }).on('select2:select select2:unselect', function(e) {
                setTimeout(function() {
                    $('#accessories').trigger('change');
                    updateAccessoriesAmount();
                    updateAccessoriesPrintText();
                    toggleRowVisibility();
                }, 50);
            });

            // Initialize Select2 for Insurance Covers
            $('#insurance_covers').select2({
                placeholder: 'Search insurance covers...',
                width: '100%',
                closeOnSelect: false,
                allowClear: false,
                sorter: function(data) {
                    var selected = [];
                    var notSelected = [];

                    data.forEach(function(item) {
                        if (item.selected) {
                            selected.push(item);
                        } else {
                            notSelected.push(item);
                        }
                    });

                    return selected.concat(notSelected);
                },
                templateResult: function(data) {
                    if (data.loading) return data.text;

                    var $container = $('<span class="select2-checkbox-label"></span>');
                    var $checkbox = $('<input type="checkbox" class="select2-checkbox">');

                    if (data.element && data.element.selected) {
                        $checkbox.prop('checked', true);
                    }

                    if (data.element && data.element.disabled) {
                        $checkbox.prop('disabled', true);
                        $container.css('opacity', '0.7');
                    }

                    $container.append($checkbox);
                    $container.append($(document.createTextNode(' ' + data.text)));

                    return $container;
                },
                templateSelection: function(data) {
                    return data.text || 'Search insurance covers...';
                }
            }).on('select2:select select2:unselect', function(e) {
                if (e.params && e.params.data && e.params.data.disabled) {
                    var val = $('#insurance_covers').val() || [];
                    if (!val.includes(e.params.data.id)) {
                        val.push(e.params.data.id);
                        $('#insurance_covers').val(val).trigger('change');
                    }
                    return;
                }
                setTimeout(function() {
                    $('#insurance_covers').trigger('change');
                    calculateQuotation();
                    toggleRowVisibility();
                }, 50);
            });

            $('#insurance_covers').on('select2:open', function() {
                setTimeout(function() {
                    const searchInput = document.querySelector(
                        '#insurance_covers + .select2-container .select2-search__field'
                    );

                    if (searchInput) {
                        searchInput.style.color = '#212529';
                        searchInput.style.webkitTextFillColor = '#212529';
                        searchInput.style.opacity = '1';
                        searchInput.style.visibility = 'visible';

                        searchInput.focus();
                    }
                }, 50);
            });

            var groupASelected = '{{ $groupASelected }}';
            var groupCSelected = '{{ $groupCSelected }}';

            $('#group_a_select').val(groupASelected).trigger('change');
            $('#group_c_select').val(groupCSelected).trigger('change');

            const IS_VIEW_MODE = @json($viewMode);

            @if (isset($quotation))

                let enquiryNo = @json($quotationData['enquiry_no'] ?? ($quotation->enquiry_no ?? ''));

                $('#mock_enquiry_no').val(enquiryNo);

                let savedCompany = @json($quotationData['insurance_company'] ?? '');
                let savedPermit = @json($quotationData['permit'] ?? '');
                let savedExShowroom = Number(@json($quotationData['ex_showroom_price'] ?? 0));


                // ========================================================
                // EDIT MODE - LOAD PRICING FOR REAL ENQUIRY
                // ========================================================

                let enquiry = null;

                if (typeof ENQUIRIES !== 'undefined' && ENQUIRIES[enquiryNo]) {
                    enquiry = ENQUIRIES[enquiryNo];
                }

                if (!enquiry) {

                    let pricingKey = Object.keys(PRICING).find(function(key) {

                        let pricing = PRICING[key];

                        return Number(
                            pricing?.receivables?.exShowroom || 0
                        ) === savedExShowroom;

                    });

                    if (pricingKey) {
                        currentPricing = PRICING[pricingKey];
                    }

                } else {

                    currentPricing = PRICING[enquiry.pricingKey];

                }


                // ========================================================
                // LOAD INSURANCE FROM CURRENT PRICING
                // ========================================================

                if (currentPricing && currentPricing.receivables) {
                let permitPricingType = @json($permit_pricing_map ?? []);

                let savedPermitId = String(savedPermit || '');

                // Resolve insurance: exact permit id first, then category, then first entry
                currentInsurance =
                    currentPricing.receivables.insurance.find(function(item) {
                        return String(item.permit) === savedPermitId;
                    }) ||
                    currentPricing.receivables.insurance.find(function(item) {
                        return String(item.permit) === String(permitPricingType[savedPermitId] || '');
                    }) ||
                    currentPricing.receivables.insurance[0];

                if (currentInsurance && Array.isArray(currentInsurance.companies)) {

                    $("#insurance_company").empty();

                    let activeCompany =
                        currentInsurance.companies.find(function(company) {
                            return String(company.insCo).toLowerCase() ===
                                String(savedCompany).toLowerCase();
                        }) ||
                        currentInsurance.companies.find(function(company) {
                            return company.default;
                        }) ||
                        currentInsurance.companies[0];

                    currentInsurance.companies.forEach(function(company) {

                        let isSelected =
                            activeCompany &&
                            String(company.insCo).toLowerCase() ===
                            String(activeCompany.insCo).toLowerCase();

                        $("#insurance_company").append(
                            `<option value="${company.insCo}" ${isSelected ? 'selected' : ''}>
                                ${company.insCo}
                            </option>`
                        );
                    });

                    if (activeCompany) {
                        $("#insurance_company").val(activeCompany.insCo);
                        loadInsurance(activeCompany);
                    }
                }
            }


                // ========================================================
                // RESTORE SAVED INSURANCE AMOUNT
                // ========================================================

                if (SAVED_INSURANCE_AMOUNT !== '') {

                    $("#insurance_amount").val(
                        Number(SAVED_INSURANCE_AMOUNT).toFixed(2)
                    );

                }


                setTimeout(function() {

                    $('#insurance_covers').trigger('change.select2');

                    if (SAVED_INSURANCE_AMOUNT !== '') {

                        $('#insurance_amount').val(
                            Number(SAVED_INSURANCE_AMOUNT).toFixed(2)
                        );

                    }

                    updateInsurancePrintText();

                }, 100);

                // ========================================================
                // RESTORE CHARGER SWAPPING OPTIONS IN EDIT MODE
                // ========================================================

                if (
                    currentPricing &&
                    currentPricing.receivables &&
                    currentPricing.receivables["charger-swapping"] &&
                    currentPricing.receivables["charger-swapping"].length > 0
                ) {
                    const chargerOptions = currentPricing.receivables["charger-swapping"];

                    $('#charger_swapping').empty();

                    chargerOptions.forEach(function(item) {

                        $('#charger_swapping').append(
                            $('<option>', {
                                value: item.title,
                                text: item.title,
                                'data-amount': item.amount
                            })
                        );

                    });

                    // Restore saved quotation selection
                    if (SAVED_CHARGER_SWAPPING) {
                        $('#charger_swapping')
                            .val(SAVED_CHARGER_SWAPPING)
                            .prop('disabled', false);
                    }

                    if (SAVED_CHARGER_SWAPPING_AMOUNT !== '') {
                        $('#charger_swapping_amount')
                            .val(SAVED_CHARGER_SWAPPING_AMOUNT)
                            .prop('disabled', false);
                    }

                    $('#charger_swapping_discount').prop('disabled', false);
                    $('#charger_swapping_discount_type').prop('disabled', false);
                    $('#charger_swapping_option').prop('disabled', false);

                } else {
                    $('#charger_swapping')
                        .val('N/A')
                        .prop('disabled', true);

                    $('#charger_swapping_amount')
                        .val('N/A')
                        .prop('disabled', true);
                }

                
                restoreSavedQuotationValues();
                checkConditionalFields();
            @endif

            // Initial calculations
            calculateQuotation();
            updateCoatingDiscountLabel();
            updateAccessoriesPrintText();
            updateInsurancePrintText();
            updateRegistrationPrintText();

            @if (!isset($quotation))
                if (!IS_VIEW_MODE) {
                    $('#mock_enquiry_no').val('019');
                    $('#btnFetchMock').click();
                }
            @endif

            checkConditionalFields();

            setTimeout(function() {
                checkConditionalFields();
            }, 300);
        });

        function calculateDiscountBifurcationByType() {
            let inv = 0,
                inv_oe = 0,
                inv_d = 0;
            let cn1 = 0,
                cn2 = 0,
                cn3 = 0;

            DISCOUNT_TYPE_PAIRS.forEach(function(pair) {
                let amount = num(pair[0]);

                if (
                    (pair[0] === 'accessories_discount' || pair[0] === 'shield_scheme') &&
                    $('#' + pair[0]).prop('disabled')
                ) {
                    amount = 0;
                }
                let type = $('#' + pair[1]).val() || '';

                // Normalize legacy & display keys
                if (type === 'Inv Disc.' || type === 'Inv. Disc.' || type === 'INV') type = 'INV';
                if (type === 'Inv Disc. (OE)' || type === 'INV_OE') type = 'INV_OE';
                if (type === 'Inv Disc. (D)' || type === 'INV_D') type = 'INV_D';
                if (type === 'CN' || type === 'CN1') type = 'CN1';

                if (type === 'INV') inv += amount;
                else if (type === 'INV_OE') inv_oe += amount;
                else if (type === 'INV_D') inv_d += amount;
                else if (type === 'CN1') cn1 += amount;
                else if (type === 'CN2') cn2 += amount;
                else if (type === 'CN3') cn3 += amount;
            });

            let totalInvSide = inv + inv_oe + inv_d;
            let totalCNSide = cn1 + cn2 + cn3;

            return {
                inv,
                inv_oe,
                inv_d,
                cn1,
                cn2,
                cn3,
                totalInvSide,
                totalCNSide
            };
        }

        // ======================================
        // Validation before saving quotation
        // Rule:
        // Total CN Discount >= Cash OEM Scheme
        // when Cash OEM Scheme Type = INV
        // ======================================

        // ======================================
        // Validation before saving quotation
        // Rule:
        // Total CN Discount >= Group A Scheme Amount
        // when Group A Scheme Type = INV
        // ======================================

        $('form').on('submit', function(e) {
            const selectedKey = $('#group_a_select').val();
            const groupAAmount = parseFloat($('#group_a_amount').val()) || 0;
            const groupAType = $('#group_a_type').val();
            const selectedLabel = $('#group_a_select option:selected').text().trim();

            let bifurcation = calculateDiscountBifurcation();
            let totalCNDiscount = bifurcation.creditNoteDiscount;

            if ((groupAType === 'INV' || groupAType === 'INV_OE') && totalCNDiscount < groupAAmount) {
                e.preventDefault();

                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Save Quotation',
                    html: `
                Total <b>CN Discount</b> should be
                <b>equal to or greater than</b>
                <b>${selectedLabel}</b> when
                <b>${selectedLabel} Type</b> is <b>INV</b>.

                <br><br>

                <b>${selectedLabel} :</b>
                ₹${groupAAmount.toFixed(2)}

                <br>

                <b>Total CN Discount :</b>
                ₹${totalCNDiscount.toFixed(2)}
            `,
                    confirmButtonText: 'Understood'
                });

                return false;
            }
        });

        function toggleRtoChargesNote() {
            let inHouseValue = $('input[name="in_house_rto"]:checked').val();
            let $rtoNoteItem = $('#rto_charges_note_item');

            if (inHouseValue === "1") {
                $rtoNoteItem.show();
            } else {
                $rtoNoteItem.hide();
            }
        }

        // Event listener for In-House RTO Radio Buttons
        $(document).on('change', 'input[name="in_house_rto"]', function() {
            toggleRtoChargesNote();
        });

        // Document Ready par Initial State Check Karne Ke Liye Call Karein
        $(document).ready(function() {
            toggleRtoChargesNote();
            updateCareOfPrint();
        });

        function updateRegistrationPrintText() {
            let typeText = $('#registration_no_type option:selected').text().trim();
            let categoryText = $('#registration_category option:selected').text().trim();
            let inHouseVal = $('input[name="in_house_rto"]:checked').val();
            let inHouseText = (inHouseVal === "1") ? "Yes" : "No";

            let parts = [];

            if (typeText && typeText.toLowerCase() !== 'select type') {
                parts.push('(' + typeText + ')');
            }
            if (categoryText && categoryText.toLowerCase() !== 'select category') {
                parts.push('(' + categoryText + ')');
            }
            parts.push('(In-House: ' + inHouseText + ')');

            $('#registration_details_print').text(parts.join(' '));
        }

        // Event Listeners
        $(document).on('change', '#registration_no_type, #registration_category, input[name="in_house_rto"]', function() {
            updateRegistrationPrintText();
        });

        $(document).ready(function() {
            updateRegistrationPrintText();
        });

        // ============================================================
        // CONDITIONAL FIELD FREEZING LOGIC
        // ============================================================

        let conditionalRules = null;

        function loadConditionalRules() {

            let enquiryNo = $('#mock_enquiry_no').val();

            // 1. Try the mock key from #mock_enquiry_no (create mode / after btnFetchMock)
            let pricing = null;

            if (enquiryNo && ENQUIRIES[enquiryNo]) {
                pricing = PRICING[ENQUIRIES[enquiryNo].pricingKey];
            }

            // 2. Edit-mode fallback: match by savedExShowroom against PRICING
            //    (works even when the real enquiry id is NOT a mock key)
            if (!pricing && IS_EDIT_MODE) {

                let savedExShowroom = Number(@json($quotationData['ex_showroom_price'] ?? 0));

                let pricingKey = Object.keys(PRICING).find(function (key) {
                    return Number(PRICING[key]?.receivables?.exShowroom || 0) === savedExShowroom;
                        });

                        if (pricingKey) {
                            pricing = PRICING[pricingKey];
                        }
                    }

            // 3. Last-ditch fallback: if only one pricing exists, use it
            if (!pricing && Object.keys(PRICING).length === 1) {
                pricing = PRICING[Object.keys(PRICING)[0]];
            }

            conditionalRules = pricing?.conditional_rules || null;
        }

        function checkConditionalFields() {
            // Load rules first
            loadConditionalRules();

            if (!conditionalRules) {
                // No rules - enable both fields
                $('#accessories_discount').prop('disabled', false).removeClass('frozen-field');
                $('#shield_scheme').prop('disabled', false).removeClass('frozen-field');
                $('.freeze-message').remove();
                return;
            }

            // Check Accessories Scheme condition
            checkAccessoriesSchemeCondition();

            // Check Shield Scheme condition
            checkShieldSchemeCondition();

            // Recalculate after state changes
            calculateQuotation();
        }

        function checkAccessoriesSchemeCondition() {
            let rule = conditionalRules.accessories_scheme;
            let accessoriesDiscountInput = $('#accessories_discount');
            let labelCell = accessoriesDiscountInput.closest('tr').find('td.cell-label');
            let row = accessoriesDiscountInput.closest('tr');

            // Remove existing freeze message
            labelCell.find('.freeze-message').remove();

            // ALWAYS show the row in Edit Mode
            if (IS_EDIT_MODE) {
                row.show();
            }

            if (!rule) {
                // No rule - enable field
                accessoriesDiscountInput.prop('disabled', false).removeClass('frozen-field');
                accessoriesDiscountInput.attr('placeholder', '0.00');
                accessoriesDiscountInput.removeClass('frozen-field');
                return;
            }

            // Get accessories amount
            let accessoriesAmount = parseFloat($('#accessories_amount').val()) || 0;

            // Check if criteria is met (if min_amount is 0, always eligible)
            let minAmount = rule.min_amount || 0;
            let isEligible = accessoriesAmount >= minAmount;

            // Calculate the scheme amount based on rule
            let schemeAmount = 0;
            if (rule.discount_amount !== undefined && rule.discount_amount !== null) {
                // Use fixed discount amount
                schemeAmount = parseFloat(rule.discount_amount) || 0;
            } else if (rule.discount_percentage) {
                // Use percentage
                schemeAmount = accessoriesAmount * (parseFloat(rule.discount_percentage) / 100);
                schemeAmount = Math.round(schemeAmount * 100) / 100;
            }

            // Store the scheme amount in a data attribute for reference
            accessoriesDiscountInput.data('scheme-amount', schemeAmount);

            if (isEligible) {
                // Enable field - set the scheme amount and make it active
                accessoriesDiscountInput
                    .prop('disabled', false)
                    .removeClass('frozen-field')
                    .val(schemeAmount.toFixed(2));
                accessoriesDiscountInput.attr('placeholder', '0.00');

                // Remove any freeze indicator
                labelCell.css('color', '');
            } else {
                // Disable/freeze field - scheme amount displayed but NOT participating in calculations
                accessoriesDiscountInput
                    .prop('disabled', true)
                    .addClass('frozen-field')
                    .val(schemeAmount.toFixed(2)); // Show the amount but frozen
                accessoriesDiscountInput.attr('placeholder', 'Currently not applicable');

                // Add freeze message to label
                let message = '';
                if (IS_EDIT_MODE) {
                    message = `(₹${minAmount.toFixed(0)}+ required - Edit accessories to avail)`;
                } else {
                    message = rule.freeze_message || `(Requires accessories worth ₹${minAmount.toFixed(0)}+)`;
                }
                labelCell.append(
                    ` <span class="freeze-message" style="color: #dc3545; font-weight: normal; font-size: 9px;">${message}</span>`
                );
            }
        }

        function checkShieldSchemeCondition() {
            let rule = conditionalRules.shield_scheme;
            let shieldSchemeInput = $('#shield_scheme');
            let labelCell = shieldSchemeInput.closest('tr').find('td.cell-label');
            let row = shieldSchemeInput.closest('tr');

            // Remove existing freeze message
            labelCell.find('.freeze-message').remove();

            // ALWAYS show the row in Edit Mode
            if (IS_EDIT_MODE) {
                row.show();
            }

            if (!rule) {
                // No rule - enable field
                shieldSchemeInput.prop('disabled', false).removeClass('frozen-field');
                shieldSchemeInput.attr('placeholder', '0.00');
                shieldSchemeInput.removeClass('frozen-field');
                return;
            }

            // Get shield status and price
            let shieldValue = $('#shield').val();
            let shieldPrice = parseFloat($('#shield_price').val()) || 0;

            // Check if shield is selected and price meets minimum
            let minAmount = rule.min_amount || 0;
            let isEligible = shieldValue &&
                shieldValue !== '' &&
                shieldValue !== 'No Shield' &&
                shieldPrice >= minAmount;

            // Calculate the scheme amount based on rule
            let schemeAmount = 0;
            if (rule.discount_amount !== undefined && rule.discount_amount !== null) {
                // Use fixed discount amount
                schemeAmount = parseFloat(rule.discount_amount) || 0;
            } else if (rule.discount_percentage) {
                // Use percentage
                schemeAmount = shieldPrice * (parseFloat(rule.discount_percentage) / 100);
                schemeAmount = Math.round(schemeAmount * 100) / 100;
            }

            // Store the scheme amount in a data attribute for reference
            shieldSchemeInput.data('scheme-amount', schemeAmount);

            if (isEligible) {
                // Enable field - set the scheme amount and make it active
                shieldSchemeInput
                    .prop('disabled', false)
                    .removeClass('frozen-field')
                    .val(schemeAmount.toFixed(2));
                shieldSchemeInput.attr('placeholder', '0.00');

                // Remove any freeze indicator
                labelCell.css('color', '');
            } else {
                // Disable/freeze field - scheme amount displayed but NOT participating in calculations
                shieldSchemeInput
                    .prop('disabled', true)
                    .addClass('frozen-field')
                    .val(schemeAmount.toFixed(2)); // Show the amount but frozen
                shieldSchemeInput.attr('placeholder', 'Currently not applicable');

                // Add freeze message to label
                let message = '';
                if (IS_EDIT_MODE) {
                    message = `(₹${minAmount.toFixed(0)}+ required - Edit shield to avail)`;
                } else {
                    message = rule.freeze_message || `(Requires Shield worth ₹${minAmount.toFixed(0)}+)`;
                }
                labelCell.append(
                    ` <span class="freeze-message" style="color: #dc3545; font-weight: normal; font-size: 9px;">${message}</span>`
                );
            }
        }
        // ============================================================
        // CONDITIONAL FIELD EVENT HANDLERS
        // ============================================================

        $(document).on('change', '#accessories', function() {
            // Small delay to ensure accessories_amount is updated first
            setTimeout(function() {
                checkConditionalFields();
            }, 100);
        });

        $(document).on('change', '#shield', function() {
            setTimeout(function() {
                checkConditionalFields();
            }, 100);
        });

        $(document).on('keyup change', '#shield_price', function() {

            checkConditionalFields();

            calculateQuotation();

            toggleRowVisibility();
        });

        $(document).on('keyup change', '#accessories_amount', function() {
            checkConditionalFields();
        });

        $(document).on('click', '#btnFetchMock', function() {
            // Wait for all data to populate then check conditions
            setTimeout(function() {
                checkConditionalFields();
            }, 300);
        });
        @if (request()->query('saved') == '1')

            function showPrintOptions() {

                Swal.fire({
                    icon: 'success',
                    title: 'Quotation Saved Successfully',
                    text: 'What would you like to do?',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Print Quotation',
                    denyButtonText: 'Print Quotation without Credit Note',
                    cancelButtonText: 'Close'
                }).then((result) => {

                    if (result.isConfirmed) {

                        Swal.close();

                        setTimeout(function() {

                            printQuotation();

                            // After print dialog is closed,
                            // show print options again
                            setTimeout(function() {
                                showPrintOptions();
                            }, 700);

                        }, 300);
                    }

                    if (result.isDenied) {

                        Swal.close();

                        setTimeout(function() {

                            printBankQuotation();

                            // After print dialog is closed,
                            // show print options again
                            setTimeout(function() {
                                showPrintOptions();
                            }, 700);

                        }, 300);
                    }
                    if (result.isDismissed) {
                        window.location.href = "{{ backpack_url('sales/quotation') }}";
                    }

                });
            }

            showPrintOptions();
        @endif

        // ============================================================
        // FINANCIER FIELD VALIDATION
        // ============================================================

        // Function to check if financier is selected
        function validateFinancierField() {
            let financierVal = $('#financier').val();

            // Check if financier is empty or has placeholder value
            if (!financierVal || financierVal === '' || financierVal === '0' || financierVal === 'Select Financier') {
                return false;
            }
            return true;
        }

        // Add validation to form submit
        $('form').on('submit', function(e) {
            // Check financier field
            if (!validateFinancierField()) {
                e.preventDefault();

                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Save Quotation',
                    html: `
                <div style="text-align: left;">
                    <p style="font-weight: bold; margin-bottom: 10px;">Please select a Financier before saving.</p>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        ⚠️ <b>Financier</b> field is mandatory.
                    </div>
                    <p style="color: #6c757d; font-size: 12px; margin-top: 10px;">
                        Please select a financier from the dropdown.
                    </p>
                </div>
            `,
                    confirmButtonText: 'OK, Select Financier',
                    confirmButtonColor: '#3085d6'
                });

                // Highlight the financier field
                $('#financier').css('border', '2px solid #dc3545');

                return false;
            }

            // Clear highlight if valid
            $('#financier').css('border', '');

            return true;
        });

        // Clear error border when user selects a value
        $(document).on('change', '#financier', function() {
            let val = $(this).val();
            if (val && val !== '' && val !== '0' && val !== 'Select Financier') {
                $(this).css('border', '');
            }
        });
    </script>
@endpush
