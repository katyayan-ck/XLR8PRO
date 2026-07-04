@extends(backpack_view('blank'))

@section('title', 'OTF Form')

@push('after_styles')
<style>
    .form-section {
        margin-bottom: 20px;
    }

    .form-section h4 {
        font-weight: 600;
        margin-bottom: 15px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    #otfPreviewPage {
        background: #fff;
        padding: 0;
        margin: 0;
    }

    .print-page {
        width: 210mm;
        margin: 0 auto;
        padding: 0;
        background: #fff;
        overflow: hidden;
    }

    #printScaleWrapper {
        width: 210mm;
        transform-origin: top left;
    }

    .preview-grid {
        column-count: 2;
        column-gap: 6px;
    }

    .preview-card {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 6px 9px 8px;
        margin-bottom: 6px;
        break-inside: avoid;
    }

    .preview-card.wide {
        column-span: none;
    }

    .preview-card h3 {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: #555;
        margin: 0 0 5px;
        padding-bottom: 3px;
        border-bottom: 1px solid #eee;
    }

    .preview-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 1.5px 0;
        font-size: 10.5px;
        line-height: 1.25;
    }

    .preview-label {
        color: #666;
    }

    .preview-value {
        font-weight: 600;
        text-align: right;
        word-break: break-word;
    }

    .preview-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.8px;
    }

    .preview-table th,
    .preview-table td {
        padding: 2px 5px;
        border-bottom: 1px solid #eee;
    }

    .preview-table th {
        text-transform: uppercase;
        font-size: 9px;
        color: #666;
    }

    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body,
        html {
            margin: 0 !important;
            padding: 0 !important;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        .no-print,
        #otfFormSection,
        .page-header,
        .navbar,
        .sidebar {
            display: none !important;
        }

        .print-page {
            width: 210mm !important;
            height: 297mm !important;
            margin: 0 !important;
        }

        #printScaleWrapper {
            font-size: 9.8px !important;
            line-height: 1.18 !important;
        }

        .preview-card {
            padding: 5px 8px !important;
            margin-bottom: 5px !important;
        }

        .preview-card h3 {
            font-size: 9.2px !important;
            margin-bottom: 3px !important;
        }

        .preview-row {
            font-size: 9.8px !important;
            padding: 1px 0 !important;
        }

        .preview-table {
            font-size: 9.5px !important;
        }
    }

    .disclaimer-box {
        border: 1px solid #ccc;
        padding: 6px;
        font-size: 8px;
        line-height: 1.3;
    }

    .disclaimer-box h4 {
        margin: 0 0 4px;
        font-size: 9px;
        font-weight: 700;
    }

    .disclaimer-box ol {
        margin: 0;
        padding-left: 15px;
    }

    .disclaimer-box li {
        margin-bottom: 2px;
    }
</style>
@endpush

@section('content')

<div id="otfFormSection">

    <div class="container-fluid">

        <div class="page-header">

            <div class="row align-items-center">

                <div class="col-md-6">

                    <h1 class="fw-bold mb-0">
                        OTF Form
                    </h1>

                </div>
            </div>

        </div>

        <form method="POST" enctype="multipart/form-data">

            @csrf
            <div class="card">

                <div class="card-body">

                    <div class="form-section">

                        <h4>
                            Customer Information
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Branch</label>
                                <input type="text" class="form-control" value="{{ $booking->branch?->name }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Location</label>
                                <input type="text" class="form-control" value="{{ $booking->location?->name }}"
                                    readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" value="{{ $booking->name }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Tehsil</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Address</label>
                                <textarea rows="3" class="form-control"></textarea>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Customer Category</label>

                                <select class="form-control form-select">

                                    <option>Individual</option>
                                    <option>Corporate</option>
                                    <option>CSD</option>
                                    <option>CPC</option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Retail Category
                                </label>

                                <select class="form-control form-select">

                                    <option>Normal</option>
                                    <option>ZACO</option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    GST Number
                                </label>

                                <input type="text" class="form-control" value="{{ $booking->gstn }}" readonly>

                            </div>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Vehicle Information
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Segment</label>
                                <input type="text" class="form-control" value="{{ $booking->segment_name }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Model</label>
                                <input type="text" class="form-control" value="{{ $booking->model_code }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Variant</label>
                                <input type="text" class="form-control" value="{{ $booking->variant_code }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Color</label>
                                <input type="text" class="form-control" value="{{ $booking->color_code }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>
                                    Chassis Number
                                </label>

                                <input type="text" class="form-control" value="{{ $booking->chasis_no }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Chassis Image
                                </label>

                                <input type="file" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Engine Number
                                </label>

                                <input type="text" readonly class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    OEM Model Code
                                </label>

                                <input type="text" readonly class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    GST Slab
                                </label>

                                <input type="text" readonly class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Body Type
                                </label>

                                <select class="form-control form-select">

                                    <option>Complete</option>
                                    <option>CBC</option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Sale Type
                                </label>

                                <select class="form-control form-select">

                                    <option>Within State</option>
                                    <option>Outside State</option>

                                </select>

                            </div>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Booking Details
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">

                                <label>
                                    Permit
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    RTO Type
                                </label>

                                <select class="form-control form-select">

                                    <option>Regular</option>
                                    <option>BH</option>
                                    <option>Special Number</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    VOTF Number
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    FSC Name
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    FSC Mile ID
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    DMS Enquiry Number
                                </label>

                                <input type="text" class="form-control" value="{{ $booking->dms_no }}" readonly>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    DMS OTF Number
                                </label>

                                <input type="text" class="form-control" value="{{ $booking->dms_otf }}" readonly>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    DSA Retail
                                </label>

                                <select class="form-control form-select">

                                    <option>Yes</option>
                                    <option>No</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    DSA Name
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    DSA Location
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            

                            <div class="col-md-3 mb-3">

                                <label>
                                    Exchange
                                </label>

                                <select class="form-control form-select">

                                    <option>NA</option>
                                    <option>In-House</option>
                                    <option>Third Party</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    In House RTO
                                </label>

                                <select class="form-control form-select">

                                    <option>Yes</option>
                                    <option>No</option>

                                </select>

                            </div>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Pricing & Charges
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">

                                <label>
                                    Ex-Showroom Price
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Insurance
                                </label>

                                <select class="form-control form-select">

                                    <option>Normal</option>
                                    <option>Nil Dep</option>
                                    <option>Nil Dep + Addons</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Registration
                                </label>

                                <select class="form-control form-select">

                                    <option>TRC Only</option>
                                    <option>Tax Only</option>
                                    <option>TRC + Tax</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Accessories
                                </label>

                                <input type="text" class="form-control" value="{{ $booking->accessories }}" readonly>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Maxicare
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    VLTD Device (GPS)
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Coating
                                </label>

                                <select class="form-control form-select">

                                    <option>Ceramic</option>
                                    <option>Graphene</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    PPF
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    RTO Yellow Tape
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Kazam
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Shield
                                </label>

                                <select class="form-control form-select">

                                    <option>4th Year</option>
                                    <option>4th + 5th Year</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    RSA
                                </label>

                                <input type="number" class="form-control">

                            </div>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Additional Charges
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">

                                <label>
                                    Fastag
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    COD Charges
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Charger Swapping
                                </label>

                                <select class="form-control form-select">

                                    <option>
                                        NCH to 7.2 kW
                                    </option>

                                    <option>
                                        NCH to 11.2 kW
                                    </option>

                                    <option>
                                        7.2 kW to 11.2 kW
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    TCS @ 1%
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Receivable
                                </label>

                                <input type="number" readonly class="form-control bg-light">

                            </div>

                        </div>

                    </div>

                    <div class="form-section">

                        <h4>
                            Discounts
                        </h4>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label>
                                    OEM Scheme / CSD Discount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Fame Subsidy
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Exchange Bonus / Green Bonus / Loyalty Bonus
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Corporate Discount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Accessories Discount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Ceramic Discount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    PPF Discount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Other Discount - Dealer
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Charger Swapping Discount
                                </label>

                                <select class="form-control form-select">

                                    <option>
                                        7.2 kW to NCH
                                    </option>

                                    <option>
                                        11.2 kW to NCH
                                    </option>

                                    <option>
                                        11.2 kW to 7.2 kW
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Discount
                                </label>

                                <input type="number" class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Net Receivable
                                </label>

                                <input type="number" id="net_receivable_summary" readonly class="form-control bg-light">

                            </div>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Finance Details
                        </h4>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label>
                                    Financier Name
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Financier Branch
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Loan Amount
                                </label>

                                <input type="number" id="loan_amount" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Deduction
                                </label>

                                <input type="number" id="deduction" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Margin Money Deposited By Financier
                                </label>

                                <input type="number" id="margin_money" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Amount
                                </label>

                                <input type="number" id="do_amount" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Settlement Difference
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Expected Balance
                                </label>

                                <input type="number" id="expected_balance" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Final Balance
                                </label>

                                <input type="number" id="final_balance" readonly class="form-control bg-light">

                            </div>

                        </div>

                    </div>

                    <div class="form-section">

                        <h4>
                            Receipt Details
                        </h4>

                        <div class="mb-3 text-end">

                            <button type="button" id="addReceiptRow" class="btn btn-primary">

                                Add Receipt

                            </button>

                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered">

                                <thead>

                                    <tr>

                                        <th>
                                            Receipt Number
                                        </th>

                                        <th>
                                            Receipt Date
                                        </th>

                                        <th>
                                            Amount
                                        </th>

                                        <th width="120">
                                            Action
                                        </th>

                                    </tr>

                                </thead>

                                <tbody id="receiptTableBody">

                                    <tr>

                                        <td>

                                            <input type="text" name="receipt_number[]" class="form-control">

                                        </td>

                                        <td>

                                            <input type="date" name="receipt_date[]" class="form-control">

                                        </td>

                                        <td>

                                            <input type="number" name="receipt_amount[]"
                                                class="form-control receipt-amount">

                                        </td>

                                        <td>

                                            <button type="button" class="btn btn-danger remove-row">

                                                Remove

                                            </button>

                                        </td>

                                    </tr>

                                </tbody>

                            </table>

                        </div>

                    </div>
                    <div class="form-section">

                        <h4>
                            Delivery & Settlement
                        </h4>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label>
                                    Financier Verified
                                </label>

                                <select class="form-control form-select">

                                    <option>Yes</option>
                                    <option>No</option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Vehicle To Be Delivered On
                                </label>

                                <select class="form-control form-select">

                                    <option>DO</option>
                                    <option>Payment</option>
                                    <option>Mail</option>
                                    <option>Whatsapp</option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Number (Delivery Time)
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Number (TA Statement)
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Amount (TA Statement)
                                </label>

                                <input type="number" id="do_amount_ta" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Financier Subvention Amount
                                </label>

                                <input type="number" id="financier_subvention" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    DO Voucher Date
                                </label>

                                <input type="date" class="form-control">

                            </div>

                        </div>

                    </div>

                    <div class="form-section">

                        <h4>
                            Receivables
                        </h4>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label>
                                    Brokerage Amount
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Other Discount Receivable
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Other Discount Receivable - M&M Support
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Other Discount Receivable - Liquidation Scheme
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Registration Service Charge - Receivable
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Registration Service Charge - Received
                                </label>

                                <input type="number" class="form-control">

                            </div>

                        </div>

                    </div>

                    <div class="form-section">

                        <h4>
                            Final Summary
                        </h4>

                        <div class="row">

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Receivable
                                </label>

                                <input type="number" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Discount
                                </label>

                                <input type="number" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Net Receivable
                                </label>

                                <input type="number" id="net_receivable" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Final Balance
                                </label>

                                <input type="number" id="final_balance_summary" readonly class="form-control bg-light">

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="text-center mt-5 mb-5">

                <button type="button" id="previewBtn" class="btn btn-primary btn-lg px-5 py-2">

                    <i class="la la-eye"></i>
                    Form Preview

                </button>

            </div>

        </form>

    </div>
</div>

<div id="otfPreviewPage" style="display:none;">
    <div class="bg-white p-4">
        <div class="print-page">
            <div id="printScaleWrapper">
                <div class="text-center mb-2">
                    <img src="{{ asset('images/otf-header.png') }}" class="img-fluid" style="width:100%;">
                </div>
                <div id="previewContent" class="preview-grid"></div>
            </div>
        </div>

        <div class="text-center mt-3 no-print">
            <button type="button" id="backToForm" class="btn btn-secondary">Back</button>
            <button type="button" id="printFormBtn" class="btn btn-success">Print / Save as PDF</button>
        </div>
    </div>
</div>



@endsection

@push('after_scripts')

<script>
    function num(id) {
        return parseFloat(
            $('#' + id).val()
        ) || 0;
    }

    function receiptTotal() {
        let total = 0;

        $('.receipt-amount').each(function() {

            total += parseFloat(
                $(this).val()
            ) || 0;

        });

        return total;
    }

    function calculateFinance() {
        const loanAmount =
            num('loan_amount');

        const deduction =
            num('deduction');

        const marginMoney =
            num('margin_money');

        const doAmount =
            loanAmount -
            deduction +
            marginMoney;

        $('#do_amount').val(
            doAmount.toFixed(2)
        );

        const netReceivable =
            num('net_receivable');

        const receiptAmount =
            receiptTotal();

        const expectedBalance =
            netReceivable -
            doAmount -
            receiptAmount;

        $('#expected_balance').val(
            expectedBalance.toFixed(2)
        );

        const doAmountTa =
            num('do_amount_ta');

        const financierSubvention =
            num('financier_subvention');

        const finalBalance =
            expectedBalance +
            doAmount -
            doAmountTa -
            financierSubvention;

        $('#final_balance').val(
            finalBalance.toFixed(2)
        );

        $('#net_receivable_summary').val(
            netReceivable.toFixed(2)
        );

        $('#final_balance_summary').val(
            finalBalance.toFixed(2)
        );
    }

    $('#addReceiptRow').on(
        'click',
        function() {

            $('#receiptTableBody').append(`

                                            <tr>

                                                <td>
                                                    <input
                                                        type="text"
                                                        name="receipt_number[]"
                                                        class="form-control">
                                                </td>

                                                <td>
                                                    <input
                                                        type="date"
                                                        name="receipt_date[]"
                                                        class="form-control">
                                                </td>

                                                <td>
                                                    <input
                                                        type="number"
                                                        name="receipt_amount[]"
                                                        class="form-control receipt-amount">
                                                </td>

                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-danger remove-row">
                                                        Remove
                                                    </button>
                                                </td>

                                            </tr>

                                        `);

            calculateFinance();
        }
    );

    $(document).on(
        'click',
        '.remove-row',
        function() {

            $(this)
                .closest('tr')
                .remove();

            calculateFinance();
        }
    );

    $(document).on(
        'input change',
        '#loan_amount, #deduction, #margin_money, #net_receivable, #do_amount_ta, #financier_subvention, .receipt-amount',
        function() {

            calculateFinance();

        }
    );

    calculateFinance();


    $('#backToForm').on('click', function() {

        $('#otfPreviewPage').hide();

        $('#otfFormSection').show();

        $('.page-header').show();

    });

    /* ---------- Force the preview onto exactly one A4 page ---------- */

    function mmToPx(mm) {
        // create a 1mm-tall probe element and read its rendered pixel height,
        // so the conversion is correct regardless of the browser's zoom/DPI.
        const probe = document.createElement('div');
        probe.style.height = '1mm';
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        document.body.appendChild(probe);
        const pxPerMm = probe.getBoundingClientRect().height;
        document.body.removeChild(probe);
        return mm * pxPerMm;
    }



    $('#previewBtn').on('click', function() {
        let html = '';

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function displayValue(value) {
            if (value == null || value === '') return '-';
            return String(value).trim();
        }

        $('.form-section').each(function() {
            const $section = $(this);
            const title = $section.find('h4').first().text().trim();
            const $table = $section.find('table');

            if ($table.length) {
                // Receipt Table
                let rows = '',
                    total = 0;
                $table.find('tbody tr').each(function() {
                    const num = $(this).find('input[name="receipt_number[]"]').val();
                    const date = $(this).find('input[name="receipt_date[]"]').val();
                    const amt = parseFloat($(this).find('input[name="receipt_amount[]"]').val()) || 0;
                    total += amt;
                    rows += `<tr><td>${escapeHtml(displayValue(num))}</td><td>${escapeHtml(displayValue(date))}</td><td style="text-align:right">${amt.toLocaleString('en-IN')}</td></tr>`;
                });

                html += `<div class="preview-card wide"><h3>${escapeHtml(title)}</h3>
                    <table class="preview-table"><thead><tr><th>Receipt No.</th><th>Date</th><th>Amount</th></tr></thead>
                    <tbody>${rows}<tr class="total"><td colspan="2"><strong>Total Received</strong></td><td style="text-align:right"><strong>${total.toLocaleString('en-IN')}</strong></td></tr></tbody></table></div>`;
                return;
            }

            // Normal fields
            let rowsHtml = '';
            $section.find('.mb-3').each(function() {
                const label = $(this).find('label').first().text().trim();
                const value = $(this).find('input, textarea, select').first().val();
                if (label) {
                    rowsHtml += `<div class="preview-row"><span class="preview-label">${escapeHtml(label)}</span><span class="preview-value">${escapeHtml(displayValue(value))}</span></div>`;
                }
            });

            if (rowsHtml) html += `<div class="preview-card"><h3>${escapeHtml(title)}</h3>${rowsHtml}</div>`;
        });

        html += `
            <div class="preview-card disclaimer-box">

                <h3>NOTE</h3>

                <ol>
                    <li>Vehicle shall be delivered only against payment.</li>
                    <li>Interest shall be charged @ 24% P.A. in case of payments delayed over three days.</li>
                    <li>No interest shall be payable on booking amount.</li>
                    <li>Price & Scheme of the vehicle is applicable as on the date of delivery.</li>
                    <li>Price & Scheme are subject to change without any prior notice.</li>
                    <li>Self attested colour copy of original documents is required for any claim. Claims will be rejected in absence of required documents.</li>
                </ol>

            </div>
            `;

        $('#previewContent').html(html);
        $('#otfFormSection, .page-header').hide();
        $('#otfPreviewPage').show();

        setTimeout(fitPreviewToOnePage, 200);
    });



    function fitPreviewToOnePage() {
        const wrapper = document.getElementById('printScaleWrapper');
        if (!wrapper) return;

        wrapper.style.transform = 'none';
        wrapper.style.width = '210mm';

        const pageHeight = mmToPx(297) - 30;
        const contentHeight = wrapper.scrollHeight;

        let scale = pageHeight / contentHeight;
        if (scale > 1) scale = 1;
        if (scale < 0.62) scale = 0.62; // Minimum readable size

        wrapper.style.transform = `scale(${scale})`;
        wrapper.style.width = (100 / scale) + '%';
    }

    $('#printFormBtn').on('click', function() {
        fitPreviewToOnePage();
        setTimeout(() => window.print(), 150);
    });

    $('#backToForm').on('click', function() {
        $('#otfPreviewPage').hide();
        $('#otfFormSection, .page-header').show();
    });

    window.addEventListener('beforeprint', fitPreviewToOnePage);
</script>
@endpush