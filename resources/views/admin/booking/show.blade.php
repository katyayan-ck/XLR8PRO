{{-- resources/views/admin/booking/show.blade.php --}}
@extends(backpack_view('blank'))

@section('header')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">


<style>
    .chip-container .badge {
        transition: all 0.2s;
    }

    .chip-container .badge:hover {
        background-color: #e9ecef !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }

    .text-truncate {
        overflow: hidden;
        white-space: nowrap;
    }

    .proof-chip {
        display: inline-flex;
        align-items: center;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 50px;
        padding: 6px 16px;
        font-size: 0.95rem;
        max-width: 100%;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        cursor: pointer;
    }

    .proof-chip:hover {
        background-color: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .proof-chip .file-name {
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0 8px;
        color: #212529;
    }

    .proof-chip .btn-action {
        background: none;
        border: none;
        font-size: 1.1rem;
        padding: 0 4px;
        cursor: pointer;
        color: #6c757d;
    }

    .proof-chip .btn-download:hover {
        color: #0d6efd;
    }

    / #proofPreviewModal .modal-content {
        border-radius: 12px !important;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3) !important;
        border: none !important;
        overflow: hidden;
    }

    #proofPreviewModal .modal-header {
        background: #0d6efd !important;
        color: white !important;
        border-bottom: none !important;
    }

    #proofPreviewModal .modal-body {
        background: white !important;
        padding: 0 !important;
        min-height: 65vh;
        max-height: 85vh;
        overflow: hidden;
    }

    #modalProofImage,
    #modalProofPdf {
        max-height: 85vh !important;
        width: 100% !important;
        object-fit: contain !important;
        border: none !important;
    }

    /* Footer buttons - clean & clickable */
    #proofPreviewModal .modal-footer {
        background: #f8f9fa !important;
        border-top: none !important;
        padding: 1rem 1.5rem !important;
    }

    #proofPreviewModal .modal-footer .btn {
        min-width: 130px;
        padding: 10px 20px;
        font-weight: 500;
        border-radius: 6px;
    }

    #proofPreviewModal {
        z-index: 1070 !important;
    }

    #proofPreviewModal .modal-dialog {
        z-index: 1071 !important;
    }

    .modal-backdrop {
        display: none !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    @include(backpack_view('inc.alerts'))
    <div class="row">
        @if ($booking->status == 1 || $booking->status == 6 || $booking->status == 8)
        <div class="card mt-3 shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex justify-content-between align-items-center">

                <h2 class="mb-0 fw-bold">Actions</h2>

                <a href="{{ backpack_url('booking/otf-form/' . $booking->id) }}" class="btn btn-success">
                    <i class="la la-file-text"></i>
                    Booking Process
                </a>
            </div>
            <div class="card-body">

                <form class="forms-sample" method="POST" action="{{ route('booking.followup.store', $booking->id) }}"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">

                        @if($booking->status != 6)
                        <div class="col-md-3">
                            <label for="status" class="form-label fw-bold">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="status" class="form-control form-select"
                                onchange="handleStatusChange(this.value, {{ $booking->pending ?? 0 }})">
                                <option value="0" selected>No Change</option>
                                <option value="2">Invoiced</option>
                                <option value="3">Cancelled</option>
                                <option value="6">On-Hold</option>
                            </select>
                        </div>


                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                Upload Image or PDF
                            </label>

                            <input class="form-control" type="file" id="newProofFile" name="new_proof_file"
                                accept=".jpg,.jpeg,.png,.pdf">

                            <small class="form-text text-muted">
                                Only jpg, png, pdf allowed (max 2MB)
                            </small>

                            <div id="newProofChipContainer" class="mt-2"></div>
                        </div>


                        <div class="col-md-3" id="invoiceNumberField" style="display: none;">
                            <label for="invoice_number" class="form-label fw-bold">Invoice Number</label>
                            <input type="text" name="invoice_number" id="invoice_number" class="form-control">
                        </div>

                        <div class="col-md-3" id="invoiceDateField" style="display: none;">
                            <label for="invoice_date" class="form-label fw-bold">Invoice Date</label>
                            <input type="text" name="invoice_date_display" id="invoice_date"
                                class="form-control flatpickr" placeholder="Select date">
                            <input type="hidden" name="invoice_date" id="hidden_invoice_date">
                        </div>

                        <div class="col-md-3" id="dealerInvoiceNumberField" style="display: none;">
                            <label for="dealer_invoice_number" class="form-label fw-bold">Dealer Invoice
                                Number</label>
                            <input type="text" name="dealer_invoice_number" id="dealer_invoice_number"
                                class="form-control">
                        </div>

                        <div class="col-md-3" id="dealerInvoiceDateField" style="display: none;">
                            <label for="dealer_invoice_date" class="form-label fw-bold">Dealer Invoice
                                Date</label>
                            <input type="text" name="dealer_invoice_date_display" id="dealer_invoice_date"
                                class="form-control flatpickr" placeholder="Select date">
                            <input type="hidden" name="dealer_invoice_date" id="hidden_dealer_invoice_date">
                        </div>
                        @endif

                        <div class="col-md-12">
                            <label for="remark" class="form-label fw-bold">
                                Remarks <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" name="remark" id="remark" rows="4" required></textarea>
                            <input type="hidden" name="id" value="{{ $booking->id }}">

                            @if($booking->status == 6)
                            @if($booking->pending != 0)
                            <input type="hidden" name="status" value="8">
                            @else
                            <input type="hidden" name="status" value="1">
                            @endif
                            @endif
                        </div>

                        <div class="col-md-3 mt-4">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ $booking->status == 6 ? 'Restore Booking' : 'Save & Submit' }}
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
        @endif


        @if ($booking->status == 3)
        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header text-black">
                <h2 class="mb-0 fw-bold">
                    Actions for Cancelled Booking
                </h2>
            </div>
            <div class="card-body">

                <form method="POST" action="{{ route('request-refund', $booking->id) }}" enctype="multipart/form-data"
                    id="refundRequestForm">
                    @csrf

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Booking Amount</label>
                            <input type="text" class="form-control" value="{{ $booking->booking_amount ?? 'N/A' }}"
                                readonly>
                            <input type="hidden" name="booking_amount" value="{{ $booking->booking_amount ?? 0 }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Deduction <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="deduction"
                                id="deduction" value="0" required oninput="calculateRemaining()">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Remaining Amount</label>
                            <input type="text" class="form-control" id="remaining_amount" name="remaining_amount"
                                value="{{ $booking->booking_amount ?? 0 }}" readonly>
                        </div>

                        <div class="col-md-4">
                            <label for="bank_name" class="form-label fw-bold">
                                Bank Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="bank_name" id="bank_name" required>
                        </div>

                        <div class="col-md-4">
                            <label for="branch_name" class="form-label fw-bold">
                                Branch Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="branch_name" required
                                pattern="[A-Za-z\s]{3,100}">
                        </div>

                        <div class="col-md-4">
                            <label for="account_type" class="form-label fw-bold">
                                Account Type <span class="text-danger">*</span>
                            </label>
                            <select name="account_type" id="account_type" class="form-control form-select" required>
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="account_number" class="form-label fw-bold">
                                Account Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="account_number" required
                                pattern="[0-9]{9,18}">
                        </div>

                        <div class="col-md-3">
                            <label for="holder_name" class="form-label fw-bold">
                                Account Holder Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="holder_name" required
                                pattern="[A-Za-z\s]{3,100}">
                        </div>

                        <div class="col-md-3">
                            <label for="ifsc_code" class="form-label fw-bold">
                                IFSC Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="ifsc_code" required maxlength="11"
                                pattern="^[A-Z]{4}0[A-Z0-9]{6}$">
                        </div>

                        <div class="col-md-3">
                            <label for="ac_proof" class="form-label fw-bold">Upload Account Image <span
                                    class="text-danger">*</span></label>
                            <input class="form-control" type="file" name="acc_proof" id="ac_proof"
                                accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="form-text text-muted">Max 2MB • jpg, png, pdf</small>

                            <div id="ac_proof_chip" class="mt-2"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="deduction_reason" class="form-label fw-bold">
                                Reason for Deduction <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" name="deduction_reason" id="deduction_reason"
                                required></textarea>
                        </div>

                        <div class="col-md-3">
                            <label for="aadhar_proof" class="form-label fw-bold">Upload Aadhar Image <span
                                    class="text-danger">*</span></label>
                            <input class="form-control" type="file" name="aadhar" id="aadhar_proof"
                                accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="form-text text-muted">Max 2MB • jpg, png, pdf</small>

                            <div id="aadhar_proof_chip" class="mt-2"></div>
                        </div>

                        <div class="col-md-3">
                            <label for="pan_proof" class="form-label fw-bold">Upload PAN Image <span
                                    class="text-danger">*</span></label>
                            <input class="form-control" type="file" name="pan" id="pan_proof"
                                accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="form-text text-muted">Max 2MB • jpg, png, pdf</small>

                            <div id="pan_proof_chip" class="mt-2"></div>
                        </div>



                        <div class="col-12 mt-4 text-center">
                            <button type="button" class="btn btn-success btn-lg px-5 me-4" id="restoreCancelledBtn">
                                <i class="la la-undo"></i> Restore Booking
                            </button>

                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="la la-arrow-right"></i> Process Refund Request
                            </button>
                        </div>
                    </div>
                </form>

                <form id="activateForm" method="POST" action="{{ route('statusave', $booking->id) }}"
                    style="display:none;">
                    @csrf
                    <input type="hidden" name="status" value="{{ $booking->pending == 0 ? 1 : 8 }}">
                </form>

            </div>
        </div>

        @endif

        @if($booking->status == 4)

        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header text-dark">
                <h2 class="mb-0">{{ __('Refund Queue') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Booking Amount') }}</label>
                        <input type="text" class="form-control" name="booking_amount"
                            value="{{ $data['amount'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Deduction') }}</label>
                        <input type="text" class="form-control" name="deduction"
                            value="{{ $data['deduction'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Remaining Amount') }}</label>
                        <input type="text" class="form-control" name="remaining_amount"
                            value="{{ $data['refund']['remaining_amount'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Details') }}</label>
                        <input type="text" class="form-control" name="details"
                            value="{{ $data['refund']['details'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Bank Name') }}</label>
                        <input type="text" class="form-control" name="bank_name"
                            value="{{ $data['refund']['bank_name'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Branch Name') }}</label>
                        <input type="text" class="form-control" name="branch_name"
                            value="{{ $data['refund']['branch_name'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Account Type') }}</label>
                        <input type="text" class="form-control" name="account_type"
                            value="{{ $data['refund']['account_type'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="small fw-bold">{{ __('Account Number') }}</label>
                        <input type="text" class="form-control" name="account_number"
                            value="{{ $data['refund']['account_number'] ?? '' }}" placeholder="e.g. 123456789012"
                            readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="small fw-bold">{{ __('Account Holder') }}</label>
                        <input type="text" class="form-control" name="holder_name"
                            value="{{ $data['refund']['holder_name'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="small fw-bold">{{ __('IFSC Code') }}</label>
                        <input type="text" class="form-control" name="ifsc_code"
                            value="{{ $data['refund']['ifsc_code'] ?? '' }}" placeholder="e.g. SBIN0001234" readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="small fw-bold">{{ __('Request Date') }}</label>
                        <input type="text" class="form-control" name="req_date"
                            value="{{ $data['refund']['req_date'] ?? 'N/A' }}" readonly>
                    </div>



                    @foreach([
                    ['label' => 'Account Proof', 'key' => 'acc_proof'],
                    ['label' => 'Aadhaar Image', 'key' => 'aadhar'],
                    ['label' => 'PAN Image', 'key' => 'pan'],
                    ] as $proof)
                    <div class="col-sm-4">
                        <label class="small fw-bold">{{ $proof['label'] }}</label>
                        @php
                        $url = $data[$proof['key']] ?? '';
                        $fileName = $url ? basename($url) : '';
                        @endphp
                        @if($url)
                        <span
                            class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 mt-2"
                            style="cursor:pointer;"
                            onclick="openPaymentProof('{{ $url }}', '{{ addslashes($fileName) }}')">
                            <i class="la la-paperclip"></i>
                            <span class="fw-medium small text-truncate" style="max-width:160px;">
                                {{ Str::limit($fileName, 22) }}
                            </span>
                        </span>
                        @else
                        <span class="badge bg-secondary-subtle text-secondary mt-2">Not uploaded</span>
                        @endif
                    </div>
                    @endforeach
                    <div class="col-sm-12 text-center mt-4">
                        <form id="restoreForm" method="POST" action="{{ route('statusave', $booking->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="3">
                            <button type="button" class="btn btn-success btn-lg px-5" id="restoreBookingButton">
                                {{ __('Restore Booking (Cancel Refund)') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header text-black">
                <h2 class="mb-0">{{ __('Refund Details') }}</h2>
            </div>
            <div class="card-body">
                <form class="forms-sample" method="POST" action="{{ route('update-refund', $booking->id) }}"
                    enctype="multipart/form-data">
                    @method('PUT')
                    @csrf
                    <div class="row g-3">
                        <!-- Refund Date -->
                        <div class="col-sm-4">
                            <label for="ref_date">{{ __('Refund Date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="ref_date" id="ref_date" class="form-control flatpickr"
                                placeholder="dd-MMM-yyyy" required>
                            <input type="hidden" name="hidden_ref" id="hidden_ref">
                            <input type="hidden" name="ref_by" value="{{ backpack_user()->id }}">
                            <input type="hidden" name="entity_type" value="booking">
                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                        </div>

                        <!-- Mode of Payment -->
                        <div class="col-sm-4">
                            <label for="mode">{{ __('Mode of Payment') }} <span class="text-danger">*</span></label>
                            <select class="form-control form-select" name="mode" required>
                                <option value="">{{ __('Select Mode') }}</option>
                                <option value="Cash">{{ __('Cash') }}</option>
                                <option value="Cheque">{{ __('Cheque') }}</option>
                                <option value="Bank Transfer">{{ __('Bank Transfer') }}</option>
                                <option value="UPI">{{ __('UPI') }}</option>
                            </select>
                        </div>

                        <!-- Transaction Ref -->
                        <div class="col-sm-4">
                            <label for="transaction_details">{{ __('Transaction Reference Number') }} <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="transaction_details"
                                placeholder="Enter transaction details" required>
                        </div>

                        <div class="col-sm-4">
                            <label class="form-label fw-bold">
                                Upload Refund Proof <span class="text-danger">*</span>
                            </label>

                            <input class="form-control" type="file" name="pay_proof" id="pay_proof"
                                accept=".jpg,.jpeg,.png,.pdf" required onchange="handleRefundProof(this)">

                            <small class="text-muted">JPG, PNG, PDF (Max 2MB)</small>

                            <div id="pay_proof_chip" class="mt-2"></div>
                        </div>

                        <!-- Remarks -->
                        <div class="col-sm-8">
                            <label for="remark">{{ __('Remarks') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="remark" rows="3" placeholder="Enter any remarks"
                                required></textarea>
                        </div>

                        <div class="col-sm-3 mt-4">
                            <button type="button" class="btn btn-danger btn-block w-100" id="rejectRefundBtn">
                                {{ __('Reject Refund Request') }}
                            </button>
                        </div>

                        <div class="col-sm-3 offset-sm-6 mt-4">
                            <button type="submit" class="btn btn-primary btn-block w-100">
                                {{ __('Submit Refund Details') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4" id="rejectionCard" style="border-radius: 12px; display: none;">
            <div class="card-header  text-black">
                <h2 class="mb-0">{{ __('Reject Refund Request') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('statusave', $booking->id) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="status" value="7">

                    <div class="row g-3">
                        <div class="col-sm-12">
                            <label for="remark"><strong>{{ __('Reason for Rejection') }} <span
                                        class="text-danger">*</span></strong></label>
                            <textarea name="remark" id="remark" class="form-control" rows="5"
                                placeholder="Enter reason why refund is being rejected..." required></textarea>
                        </div>

                        <div class="col-sm-4">
                            <label class="form-label fw-bold">
                                {{ __('Upload Supporting Document (Optional)') }}
                            </label>

                            <input type="file" name="fdoc" id="fdoc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">

                            <small class="text-muted">JPG, PNG, PDF (Max 2MB)</small>

                            <div id="fdoc_chip" class="mt-2"></div>
                        </div>

                        <div class="col-sm-12 text-center mt-4">
                            <button type="submit" class="btn btn-danger btn-lg px-5">
                                {{ __('Reject Refund & Save Remarks') }}
                            </button>
                            <button type="button" class="btn btn-secondary ml-3"
                                onclick="document.getElementById('rejectionCard').style.display='none';">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @endif

        @if ($booking->status == 7)

        <div class="card mt-4" id="receipt-log-card" style="border-radius: 12px">
            <div class="card-header position-relative">
                <h2 class="mb-0">{{ __('Receipt Log') }}</h2>
                <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2"
                    id="toggle-receipt-log">
                    <i class="ik ik-minus" id="receipt-icon"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 table-responsive">
                        <table id="receipt_log" class="table table-striped table-bordered table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th width="20%">{{ __('Date') }}</th>
                                    <th width="30%">{{ __('Receipt No.') }}</th>
                                    <th width="20%">{{ __('Amount') }}</th>
                                    <th width="20%">{{ __('Image') }}</th>
                                    <th width="10%">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($receiptLogs->isNotEmpty())
                                @foreach ($receiptLogs as $log)
                                @php $iurl = $log->getFirstMediaUrl('amount-proof') @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($log->date)->format('d-M-Y') }}</td>
                                    <td>{{ $log->reciept ?? 'N/A' }}</td>
                                    <td>{{ number_format($log->amount, 2) }}</td>
                                    <td>
                                        @if($iurl)
                                        <a href="{{ $iurl }}" target="_blank">
                                            <img src="{{ $iurl }}" class="img-fluid" width="100" alt="Receipt Image">
                                        </a>
                                        @else
                                        N/A
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <a href="{{ route('receipt.edit', ['id' => $booking->id, 'receipt_id' => $log->id]) }}"
                                            class="btn btn-sm btn-outline-success">
                                            <i class="la la-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                @endforeach

                                @else
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        {{ __('No receipts found.') }}
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('editRefund', $booking->id) }}" method="POST" id="refundForm"
            enctype="multipart/form-data">
            @csrf
            <div class="card mt-4" id="refund-details-card" style="border-radius: 12px">
                <div class="card-header position-relative">
                    <h2 class="mb-0">{{ __('Edit Refund Details') }}</h2>
                    <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2"
                        id="toggle-refund-details">
                        <i class="ik ik-minus" id="refund-icon"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <label>{{ __('Booking Amount') }}</label>
                            <input type="text" class="form-control" name="booking_amount"
                                value="{{ $booking->booking_amount }}" disabled>
                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                        </div>

                        <div class="col-sm-4">
                            <label>{{ __('Deduction') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="deduction" id="deduction"
                                value="{{ $data['deduction'] ?? 0 }}" required step="0.01"
                                oninput="calculateRemaining()">
                        </div>

                        <!-- Remaining Amount -->
                        <div class="col-sm-4">
                            <label>{{ __('Remaining Amount') }}</label>
                            <input type="text" class="form-control" name="remaining_amount" id="remaining_amount"
                                value="{{ $data['refund']['remaining_amount'] ?? 0 }}" readonly>
                        </div>

                        <!-- Bank Name -->
                        <div class="col-sm-4">
                            <label>{{ __('Bank Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="bank_name"
                                value="{{ $data['refund']['bank_name'] ?? '' }}" required>
                        </div>

                        <!-- Branch Name -->
                        <div class="col-sm-4">
                            <label>{{ __('Branch Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="branch_name"
                                value="{{ $data['refund']['branch_name'] ?? '' }}" required>
                        </div>

                        <!-- Account Type -->
                        <div class="col-sm-4">
                            <label>{{ __('Account Type') }} <span class="text-danger">*</span></label>
                            <select class="form-control form-select" name="account_type" required>
                                <option value="Savings" {{ ($data['refund']['account_type'] ?? '' )=='Savings'
                                    ? 'selected' : '' }}>Savings</option>
                                <option value="Current" {{ ($data['refund']['account_type'] ?? '' )=='Current'
                                    ? 'selected' : '' }}>Current</option>
                            </select>
                        </div>

                        <!-- Account Number -->
                        <div class="col-sm-4">
                            <label>{{ __('Account Number') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="account_number"
                                value="{{ $data['refund']['account_number'] ?? '' }}" required>
                        </div>

                        <!-- Account Holder -->
                        <div class="col-sm-4">
                            <label>{{ __('Account Holder') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="holder_name"
                                value="{{ $data['refund']['holder_name'] ?? '' }}" required>
                        </div>

                        <!-- IFSC Code -->
                        <div class="col-sm-4">
                            <label>{{ __('IFSC Code') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ifsc_code"
                                value="{{ $data['refund']['ifsc_code'] ?? '' }}" required>
                        </div>

                        <!-- Account Proof - FIXED -->
                        <div class="col-sm-4">
                            <label class="small fw-bold">Account Proof</label>

                            @php
                            $url = $data['acc_proof'] ?? '';
                            $fileName = $url ? basename($url) : '';
                            @endphp

                            @if($url)
                            <span
                                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 mt-2"
                                style="cursor:pointer;"
                                onclick="openPaymentProof('{{ $url }}', '{{ addslashes($fileName) }}')">
                                <i class="la la-paperclip"></i>
                                <span class="fw-medium small text-truncate" style="max-width:160px;">
                                    {{ Str::limit($fileName, 22) }}
                                </span>
                            </span>
                            @else
                            <span class="badge bg-secondary-subtle text-secondary mt-2">Not uploaded</span>
                            @endif
                        </div>

                        <!-- Details -->
                        <div class="col-sm-4">
                            <label>{{ __('Details') }}</label>
                            <input type="text" class="form-control" name="details"
                                value="{{ $data['refund']['details'] ?? '' }}">
                        </div>

                        <!-- Submit -->
                        <div class="col-sm-12 mt-4">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ __('Update Refund Details') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Add Remarks Card -->
        <div class="card mt-4" style="border-radius: 12px" id="remark-card">
            <div class="card-header position-relative">
                <h2 class="mb-0">{{ __('Add Remarks') }}</h2>
                <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" id="toggle-remark">
                    <i class="ik ik-minus" id="remark-icon"></i>
                </button>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('booking.followup.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-9">
                            <label>{{ __('Remarks') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="remark" id="remark" required>
                            <input type="hidden" name="id" value="{{ $booking->id }}">
                        </div>

                        <div class="col-sm-3">
                            <label class="small fw-bold">Upload Image/PDF</label>

                            <input class="form-control" type="file" name="fdoc" id="fdoc" accept=".jpg,.jpeg,.png,.pdf">

                            <small class="text-muted">JPG, PNG, PDF (max 2MB)</small>

                            <!-- Chip container -->
                            <div id="fdoc_chip" class="mt-2"></div>
                        </div>

                        <div class="col-sm-12 mt-3">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ __('Add Remarks') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Request Refund Again -->

        <div class="row mt-5">
            <div class="col-12">
                <div class="d-flex justify-content-center gap-4 flex-wrap">
                    <!-- Request Refund Again -->
                    <form id="activateForm" method="POST" action="{{ route('statusave', $booking->id) }}"
                        class="d-inline-block">
                        @csrf
                        <input type="hidden" name="status" value="4">
                        <button type="submit" class="btn btn-warning btn-lg px-5 shadow" id="refundButton">
                            <i class="la la-redo me-2"></i> {{ __('Request For Refund Again') }}
                        </button>
                    </form>

                    <!-- Back to List -->
                    <a href="{{ backpack_url('booking') }}" class="btn btn-secondary btn-lg px-5 shadow">
                        <i class="la la-arrow-left me-2"></i> {{ __('Back to List') }}
                    </a>
                </div>
            </div>
        </div>



        @endif
        @if ($booking->status == 5)
        <!-- Refund Details Card -->
        <div class="card mt-4" style="border-radius: 12px" id="refund-details-card">
            <div class="card-header position-relative">
                <h2 class="mb-0">{{ __('Refund Details') }}</h2>
                <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2"
                    id="toggle-edit-refund">
                    <i class="ik ik-edit" id="edit-icon"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="refund-details-form" method="POST" action="{{ route('update-refunded', $booking->id) }}"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <!-- Mode of Payment -->
                        <div class="col-sm-3">
                            <label>{{ __('Mode of Payment') }}</label>
                            <select class="form-control" name="mode" id="mode" disabled>
                                <option value="Cash" {{ ($data['refund']['mode'] ?? '' )=='Cash' ? 'selected' : '' }}>
                                    Cash</option>
                                <option value="Online" {{ ($data['refund']['mode'] ?? '' )=='Online' ? 'selected' : ''
                                    }}>Online</option>
                                <option value="Cheque" {{ ($data['refund']['mode'] ?? '' )=='Cheque' ? 'selected' : ''
                                    }}>Cheque</option>
                            </select>
                        </div>

                        <!-- Transaction Details -->
                        <div class="col-sm-3">
                            <label>{{ __('Transaction Details') }}</label>
                            <input type="text" class="form-control" name="transaction_details"
                                value="{{ $data['refund']['transaction_details'] ?? 'N/A' }}" readonly>
                        </div>

                        <!-- Remarks -->
                        <div class="col-sm-3">
                            <label>{{ __('Remarks') }}</label>
                            <input type="text" class="form-control" name="remark"
                                value="{{ $data['refund']['remark'] ?? 'N/A' }}" readonly>
                        </div>

                        <!-- Payment Proof -->
                        <div class="col-sm-3">
                            <label class="small fw-bold">Payment Proof (Refund Proof)</label>

                            @php
                            $payUrl = $data['pay_proof'] ?? '';
                            $payFileName = $payUrl ? basename($payUrl) : '';
                            @endphp

                            @if($payUrl)
                            <span
                                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 mt-2"
                                style="cursor:pointer;"
                                onclick="openPaymentProof('{{ $payUrl }}', '{{ addslashes($payFileName) }}')">
                                <i class="la la-paperclip"></i>
                                <span class="fw-medium small text-truncate" style="max-width:160px;">
                                    {{ Str::limit($payFileName, 22) }}
                                </span>
                            </span>
                            @else
                            <span class="badge bg-secondary-subtle text-secondary mt-2">Not uploaded</span>
                            @endif
                        </div>

                        <!-- Edit / Update Buttons -->
                        {{-- <div class="col-sm-12 mt-3 text-end">
                            <button type="button" class="btn btn-primary" id="editRefundButton">
                                {{ __('Edit') }}
                            </button>
                            <button type="submit" class="btn btn-success" id="updateRefundButton" style="display:none;">
                                {{ __('Update') }}
                            </button>
                        </div> --}}
                    </div>
                </form>
            </div>
        </div>
        @endif


        <div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
            <!-- Payment Details -->
            <h2 class="mb-3  ">Payment Details</h2>
            <div class="row g-3">
                <div class="col-sm-3">
                    <label class="small fw-bold">Customer Type</label>
                    <input type="text" class="form-control" value="{{ ucfirst($booking->b_type ?? 'N/A') }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Customer Category</label>
                    <input type="text" class="form-control" value="{{ ucfirst($booking->b_cat ?? 'N/A') }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Booking Date</label>
                    <input type="text" class="form-control"
                        value="{{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d-M-Y') : 'N/A' }}"
                        readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Collection Type</label>
                    <input type="text" class="form-control" id="col_type"
                        value="{{ $booking->col_type == 1 ? 'Receipt' : ($booking->col_type == 2 ? 'Field Collection (By Sales Team)' : ($booking->col_type == 3 ? 'Field Collection (By DSA)' : 'Used Car Purchased')) }}"
                        readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Collected By</label>
                    <input type="text" class="form-control"
                        value="{{ \App\Services\OrgService::getUserNameByCode($booking->col_by, $booking->col_type) }}"
                        readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Booking Amount</label>
                    <input type="text" class="form-control" value="₹ {{ number_format($booking->booking_amount ?? 0) }}"
                        readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">
                        {{ in_array($booking->col_type, [1,4])
                        ? ($booking->col_type == 1 ? 'Receipt No.' : 'Voucher No.')
                        : 'Receipt/Voucher No.' }}
                    </label>
                    <input type="text" class="form-control" value="{{ $booking->receipt_no ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="small fw-bold">Receipt/Voucher Date</label>
                    <input type="text" class="form-control"
                        value="{{ $booking->receipt_date ? \Carbon\Carbon::parse($booking->receipt_date)->format('d-M-Y') : 'N/A' }}"
                        readonly>
                </div>



                <!-- Payment Proof - 100% add.blade.php jaisa -->
                <div class="col-sm-3">
                    <label class="small fw-bold">Payment Proof</label>

                    @php
                    $payment = $booking->bookingAmounts()->latest()->first();
                    $hasProof = $payment && $payment->hasMedia('amount-proof');
                    $media = $hasProof ? $payment->getFirstMedia('amount-proof') : null;
                    $fileUrl = $hasProof ? $media->getUrl() : null;
                    $fileName = $hasProof ? $media->file_name : null;
                    $isPdf = $hasProof && str_contains($media?->mime_type ?? '', 'pdf');
                    @endphp

                    @if($hasProof)
                    <div class="mt-2" id="paymentProofPreview">

                        <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                            style="cursor:pointer;"
                            onclick="openPaymentProofModal('{{ $fileUrl }}','{{ addslashes($fileName) }}')">

                            <i class="la la-paperclip"></i>

                            <span class="fw-medium small">
                                {{ Str::limit($fileName, 28) }}
                            </span>

                        </span>

                    </div>
                    <i
                        class="{{ $isPdf ? 'fas fa-file-pdf text-danger' : 'fas fa-file-image text-primary' }} fs-4 me-2"></i>
                    {{-- <span class="file-name text-truncate" title="{{ $fileName }}">
                        {{ Str::limit($fileName, 24) }}
                    </span> --}}
                    {{-- <button type="button" class="btn-action btn-download ms-2" title="Download"
                        onclick="event.stopPropagation(); downloadFile('{{ $fileUrl }}', '{{ addslashes($fileName) }}')">
                        <i class="fas fa-download"></i>
                    </button> --}}
                </div>
            </div>
            @else
            <div class="badge bg-secondary-subtle text-secondary px-3 py-2 mt-2">
                No proof uploaded
            </div>
            @endif
        </div>
    </div>
</div>


<div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
    <h2 class="mb-3  ">Customer Details</h2>
    <div class="row g-3">
        <div class="col-sm-3">
            <label class="small fw-bold">Customer Name</label>
            <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Care Of</label>
            <input type="text" class="form-control" id="title" name="title" value="{{ $booking->care_of_type == 5
                                                ? 'Owned By'
                                                : ($booking->care_of_type == 1
                                                    ? 'Son of'
                                                    : ($booking->care_of_type == 2
                                                        ? 'Daughter of'
                                                        : ($booking->care_of_type == 3
                                                            ? 'Married'
                                                            : ($booking->care_of_type == 4
                                                                ? 'Guardian Name'
                                                                : 'N/A')))) }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Care Of Name</label>
            <input type="text" class="form-control" value="{{ $booking->care_of ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Contact No.</label>
            <input type="text" class="form-control" value="{{ $booking->mobile ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Alternate Contact No.</label>
            <input type="text" class="form-control" value="{{ $booking->alt_mobile ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Gender</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->gender ?? 'N/A') }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Occupation</label>
            <input type="text" class="form-control" value="{{ match($booking->occ ?? '') {
                                    'Agriculture'                  => 'Agriculture',
                                    'Business'                     => 'Business',
                                    'Salaried (Govt.)'             => 'Salaried (Govt.)',
                                    'Salaried (Pvt.)'              => 'Salaried (Pvt.)',
                                    'Self Employed (Professional)' => 'Self Employed (Professional)',
                                    'Pensioner'                    => 'Pensioner',
                                    'Other'                        => 'Other',
                                    default                        => 'N/A'
                                } }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">PAN Card No.</label>
            <input type="text" class="form-control" value="{{ $booking->pan_no ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-4">
            <label class="small fw-bold">Aadhar No.</label>
            <input type="text" class="form-control" value="{{ $booking->adhar_no ?: 'N/A' }}" readonly>
        </div>
        <div class="col-sm-4">
            <label class="small fw-bold">GSTN</label>
            <input type="text" class="form-control"
                value="{{ $booking->gstn && trim($booking->gstn) !== '0' ? trim($booking->gstn) : 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Customer D.O.B.</label>

            <input type="text" class="form-control"
                value="{{ $booking->receipt_date ? \Carbon\Carbon::parse($booking->c_dob)->format('d-M-Y') : 'N/A' }}"
                readonly>
        </div>

        <div class="col-sm-4">
            <label class="small fw-bold">Branch</label>
            <input type="text" class="form-control" value="{{ $booking->branch?->name ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-4">
            <label class="small fw-bold">Location</label>
            <input type="text" class="form-control"
                value="{{ $booking->location ? ($booking->location->name  ?? 'N/A') : 'Other' }}" readonly>
        </div>
        <div class="col-sm-4">
            <label class="small fw-bold">Other Location</label>
            <input type="text" class="form-control" value="{{ $booking->location_other ?? 'N/A' }}" readonly>
        </div>
    </div>
</div>

<div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
    <!-- Referred By Details -->
    <h2 class="mb-3  ">Referred By Details</h2>
    <div class="row g-3">

        <div class="col-sm-3">
            <label class="small fw-bold">Referred Customer Name</label>
            <input type="text" class="form-control" value="{{ $booking->r_name ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Referred Mobile No.</label>
            <input type="text" class="form-control" value="{{ $booking->r_mobile ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-2">
            <label class="small fw-bold">Existing Model</label>
            <input type="text" class="form-control" value="{{ $booking->r_model ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-2">
            <label class="small fw-bold">Variant</label>
            <input type="text" class="form-control" value="{{ $booking->r_variant ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-2">
            <label class="small fw-bold">Chassis/Regn. No.</label>
            <input type="text" class="form-control" value="{{ $booking->r_chassis ?? 'N/A' }}" readonly>
        </div>
    </div>
</div>

<div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
    <!-- Purchase Type Details -->
    <h2 class="mb-3">Purchase Type Details</h2>
    <div class="row g-3">
        <div class="col-sm-3">
            <label class="small fw-bold">Purchase Type</label>
            <input type="text" class="form-control" id="buyer_type" name="buyer_type"
                value="{{ $booking->buyer_type ?? 'N/A' }}" readonly>
        </div>
        {{-- <div class="col-sm-3">
            <label class="small fw-bold">Brand Make 1</label>
            <input type="text" class="form-control" id="enum_master1" name="enum_master1" value="{{ $make1 }}" readonly>
            {{-- Fix: $data['make1'] -> $make1
        </div> --}}
        <div class="col-sm-3">
            <label class="small fw-bold">Model Variant 1</label>
            <input type="text" class="form-control" value="{{ $booking->vh1_detail ?? 'N/A' }}" readonly>
        </div>

        <div class="col-sm-3">
            <label class="small fw-bold">Model Variant 2</label>
            <input type="text" class="form-control" value="{{ $booking->vh2_detail ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Vehicle Registration No.</label>
            <input type="text" class="form-control" value="{{ $booking->registration_no ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Manufacturing Year</label>
            <input type="text" class="form-control" value="{{ $booking->make_year ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Odometer Reading</label>
            <input type="text" class="form-control" value="{{ $booking->odo_reading ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Expected Price</label>
            <input type="text" class="form-control" value="₹ {{ number_format($booking->expected_price ?? 0) }}"
                readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Offered Price</label>
            <input type="text" class="form-control" value="₹ {{ number_format($booking->offered_price ?? 0) }}"
                readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Exchange Bonus</label>
            <input type="text" class="form-control" value="₹ {{ number_format($booking->exchange_bonus ?? 0) }}"
                readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Price Gap</label>
            <input type="text" class="form-control" id="difference" name="difference"
                value="{{ $booking->expected_price - ($booking->offered_price + $booking->exchange_bonus) ?? 'N/A' }}"
                readonly>
        </div>
    </div>
</div>


<div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
    <!-- Vehicle Details -->
    <h2 class="mb-3  ">Vehicle Details</h2>
    <div class="row g-3">

        <div class="col-sm-3">
            <label class="small fw-bold">Segment</label>
            <input type="text" class="form-control" value="{{ $booking->segment_name ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Model</label>
            <input type="text" class="form-control" value="{{ $booking->model_code ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Variant</label>
            <input type="text" class="form-control" value="{{ $booking->variant_code ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Color</label>
            <input type="text" class="form-control" value="{{ $booking->color_code ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Seating</label>
            <input type="text" class="form-control" value="{{ $booking->seating ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-5">
            <label class="small fw-bold">Accessories</label>
            <input type="text" class="form-control"
                value="{{ $booking->accessories ? str_replace(',', ', ', $booking->accessories) : 'N/A' }}" readonly>
        </div>
        <div class="col-sm-2">
            <label class="small fw-bold">Accessories Amount</label>
            <input type="text" class="form-control" value="₹ {{ number_format($booking->apack_amount ?? 0) }}" readonly>
        </div>
        <div class="col-sm-2">
            <label class="small fw-bold">Allotted Chassis No.</label>
            <input type="text" class="form-control" value="{{ $booking->chassis_no ?? 'N/A' }}" readonly>
        </div>
    </div>
</div>


<div class="card card-body mt-3 shadow-sm" style="border-radius: 12px">
    <!-- Booking Type & Source -->
    <h2 class="mb-3  ">Booking Type & Source</h2>
    <div class="row g-3">
        <div class="col-sm-3">
            <label class="small fw-bold">Booking Mode</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->b_mode ?? 'N/A') }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Online Booking Ref No.</label>
            <input type="text" class="form-control" value="{{ $booking->online_bk_ref_no ?? 'N/A' }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Booking Source</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->b_source ?? 'N/A') }}" readonly>
        </div>

        <div class="col-sm-3">
            <label class="small fw-bold">Delivery Date Type</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->del_type ?? 'N/A') }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Expected Delivery Date</label>
            <input type="text" class="form-control"
                value="{{ $booking->receipt_date ? \Carbon\Carbon::parse($booking->del_date)->format('d-M-Y') : 'N/A' }}"
                readonly>

        </div>

        <div class="col-sm-3">
            <label class="small fw-bold">Finance Mode</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->fin_mode ?? 'N/A') }}" readonly>
        </div>
        <div class="col-sm-3">
            <label class="small fw-bold">Financier</label>
            <input type="text" class="form-control"
                value="{{ \App\Models\Module\Booking\XlFinancier::find($booking->financier)?->name ?? 'N/A' }}"
                readonly>
        </div>

        <div class="col-sm-3">
            <label class="small fw-bold">Loan File Status</label>
            <input type="text" class="form-control" value="{{ ucfirst($booking->loan_status ?? 'N/A') }}" readonly>
        </div>

    </div>
</div>

<div class="card card-body mt-3 shadow-sm mb-2" style="border-radius: 12px">
    <h2 class="mb-3  ">DMS Booking Details</h2>
    <div class="row g-3">
        <div class="col-md-3 form-group">
            <label>
                DMS Booking No.

            </label>
            <input type="text" class="form-control" value="{{ $booking->dms_no ?? 'N/A' }}" readonly>
        </div>

        <div class="col-md-3 form-group">
            <label>
                DMS OTF No.

            </label>
            <input type="text" class="form-control" value="{{ $booking->dms_otf ?? 'N/A' }}" readonly>
        </div>

        <div class="col-md-3 form-group">
            <label>
                DMS OTF Date

            </label>

            <input type="text" class="form-control"
                value="{{ $booking->otf_date ? \Carbon\Carbon::parse($booking->otf_date)->format('d-M-Y') : 'N/A' }}"
                readonly>

        </div>


        <div class="col-md-3 form-group">
            <label>
                DMS SO No.

            </label>
            <input type="text" class="form-control" value="{{ $booking->dms_so ?? 'N/A' }}" readonly>
        </div>

    </div>
</div>
<div class="card mt-3 shadow-sm mb-2" style="border-radius: 12px">

    {{-- <h2 class="mb-3">Booking History</h2> --}}

    <div class="row">
        <div class="col-12">

            @include('partials.entity-history', [
            'threads' => $bookingHistory ?? collect()
            ])

        </div>
    </div>

</div>



<!-- Proof Preview Modal -->
<div class="modal" id="proofPreviewModal" tabindex="-1" aria-labelledby="proofPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-3 shadow-lg border-0 overflow-hidden">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="proofPreviewModalLabel">Payment Proof Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="min-height:65vh; height:85vh;">
                <div class="position-relative w-100 h-100 d-flex align-items-center justify-content-center">
                    <img id="modalProofImg" class="img-fluid"
                        style="display:none; max-height:100%; max-width:100%; object-fit:contain;" alt="Proof Image">
                    <iframe id="modalProofPdf" class="w-100 h-100 border-0"
                        style="display:none; max-height:100%; max-width:100%; object-fit:contain;"
                        sandbox="allow-scripts allow-same-origin"></iframe>
                    <div id="modalNoPreview" class="text-center text-muted d-none">
                        <i class="fas fa-file fa-3x mb-3"></i>
                        <p>Preview not available</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
                <a id="modalDownloadLink" href="#" class="btn btn-primary px-4" download>
                    {{-- <i class="fas fa-download me-1"></i> --}}
                    Download
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Back Button -->
{{-- <div class="row mt-5">
    <div class="col-12 text-center">
        <a href="{{ backpack_url('booking') }}" class="btn btn-secondary btn-lg px-5 shadow">
            <i class="la la-arrow-left me-2"></i> Back to List
        </a>
    </div>
</div> --}}


</div>

<div class="modal fade" id="attachmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalFileName"></h5>
                {{-- <button type="button" class="close" data-bs-dismiss="modal">&times;</button> --}}
            </div>

            <div class="modal-body text-center">
                <iframe id="modalFilePreview" style="width:100%; height:500px;" frameborder="0"></iframe>
            </div>

            <div class="modal-footer">
                <a id="modalDownload" class="btn btn-success" download>
                    Download
                </a>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
<!-- Fdoc Proof Modal -->
<div class="modal fade" id="fdocProofModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-black">
                <h5 class="modal-title" id="fdocProofModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="fdocProofImg" src="" alt="Preview" style="max-width:100%; max-height:70vh; display:none;">
                <iframe id="fdocProofIframe" style="width:100%; height:500px; display:none;" frameborder="0"></iframe>
            </div>
            <div class="modal-footer">
                <a id="fdocProofDownload" class="btn btn-success" download>Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="paymentProofModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="paymentProofFileName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">
                <iframe id="paymentProofPreviewFrame" style="width:100%; height:500px;" frameborder="0"></iframe>
            </div>

            <div class="modal-footer">
                <a id="paymentProofDownload" class="btn btn-success" download>
                    Download
                </a>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
{{-- <div class="row mt-4">
    <div class="col-12">

        @include('partials.entity-history', [
        'threads' => $bookingHistory ?? collect()
        ])

    </div>
</div> --}}


@endsection
@section('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    function openPaymentProof(url, fileName = 'Proof File') {
    document.getElementById('paymentProofFileName').textContent = fileName;
    const downloadBtn = document.getElementById('paymentProofDownload');
    downloadBtn.href = url;
    downloadBtn.download = fileName;
    const frame = document.getElementById('paymentProofPreviewFrame');
    frame.src = url;
    frame.style.display = 'block';
    const modal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
    modal.show();
}
    function openPaymentProofModal(url, fileName)
{
    document.getElementById('paymentProofFileName').innerText = fileName;

    document.getElementById('paymentProofPreviewFrame').src = url;

    document.getElementById('paymentProofDownload').href = url;

    const modal = new bootstrap.Modal(
        document.getElementById('paymentProofModal')
    );

    modal.show();
}
        function createProofChip(containerId, fileName, fileUrl, isPdf = false) {

    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = "";

    const chip = document.createElement('span');

    chip.className = "btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2";

    chip.style.cursor = "pointer";

    chip.innerHTML = `
    <i class="la la-paperclip"></i>
    <span class="fw-medium small">
        ${fileName.length > 28 ? fileName.substring(0,25) + '...' : fileName}
    </span>
`;

    chip.onclick = function () {
        openPaymentProofModal(fileUrl, fileName);
    };

    container.appendChild(chip);
}

        function removeProofChip(containerId) {
            const container = document.getElementById(containerId);
            if (container) container.innerHTML = '';

            let inputId;
            if (containerId === 'ac_proof_chip')     inputId = 'ac_proof';
            else if (containerId === 'aadhar_proof_chip') inputId = 'aadhar_proof';
            else if (containerId === 'pan_proof_chip')    inputId = 'pan_proof';
            else if (containerId === 'pay_proof_chip') inputId = 'pay_proof';
            else if (containerId === 'fdoc_chip') inputId = 'fdoc';
            const input = document.getElementById(inputId);
            if (input) input.value = '';
        }

        function handleFileUpload(input) {
            if (!input.files?.[0]) return;

            const file = input.files[0];

            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
    icon: 'error',
    title: 'Invalid File',
    text: 'File must be less than 2MB!',
    confirmButtonColor: '#dc3545'
});
                input.value = '';
                return;
            }

            const isPdf = file.type === 'application/pdf';
            const url = URL.createObjectURL(file);

            let containerId;
            if (input.id === 'ac_proof')         containerId = 'ac_proof_chip';
            else if (input.id === 'aadhar_proof') containerId = 'aadhar_proof_chip';
            else if (input.id === 'pan_proof')    containerId = 'pan_proof_chip';
            else if (input.id === 'pay_proof') containerId = 'pay_proof_chip';
            else if (input.id === 'fdoc') containerId = 'fdoc_chip';
            else return;

            createProofChip(containerId, file.name, url, isPdf);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = ['ac_proof', 'aadhar_proof', 'pan_proof', 'pay_proof', 'fdoc'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', () => handleFileUpload(el));
                }
            });
        });

            
        const activateBtn = document.getElementById('activateButton');
        if (activateBtn) {
            activateBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to restore this booking?')) {
                    document.getElementById('activateForm').submit();
                }
            });
        }
        function handleRefundProof(input)
{
    if (!input.files?.[0]) return;

    const file = input.files[0];

    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File',
            text: 'File must be less than 2MB!',
            confirmButtonColor: '#dc3545'
        });
        input.value = '';
        return;
    }

    const fileURL = URL.createObjectURL(file);

    const container = document.getElementById('pay_proof_chip');
    container.innerHTML = '';

    const chip = document.createElement('span');

    chip.className = "btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2";
    chip.style.cursor = "pointer";

    chip.innerHTML = `
        <i class="la la-paperclip"></i>
        <span class="fw-medium small">
            ${file.name.length > 28 ? file.name.substring(0,25) + '...' : file.name}
        </span>
    `;

    chip.onclick = function() {

        const type = file.type === 'application/pdf' ? 'pdf' : 'image';

        openProofPreview(fileURL, type, file.name);
    };

    container.appendChild(chip);
}
        function openProofPreview(url, type) {
            const modalEl = document.getElementById('proofModal');
            const modal = new bootstrap.Modal(modalEl);

            const imgElement  = document.getElementById('modalImage');
            const pdfElement  = document.getElementById('modalPdf');

            // Reset both
            imgElement.style.display = 'none';
            pdfElement.style.display = 'none';
            imgElement.src = '';
            pdfElement.src = '';

            if (type === 'image') {
                imgElement.src = url;
                imgElement.style.display = 'block';
            } else if (type === 'pdf') {
                pdfElement.src = url;
                pdfElement.style.display = 'block';
            }

            modal.show();
        }
function openProofPreview(url, type, fileName = 'Payment Proof') {
    if (!url) {
        Swal.fire({ title: 'Error', text: 'No file available', icon: 'error' });
        return;
    }

    const modalEl = document.getElementById('proofPreviewModal');
    const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });

    const imgEl   = document.getElementById('modalProofImage');
    const pdfEl   = document.getElementById('modalProofPdf');
    const noPrev  = document.getElementById('modalNoPreview');
    const dlLink  = document.getElementById('modalDownloadLink');
    const titleEl = document.getElementById('proofPreviewModalLabel');

    imgEl.style.display = 'none';
    pdfEl.style.display = 'none';
    noPrev.classList.add('d-none');
    imgEl.src = '';
    pdfEl.src = '';

    titleEl.textContent = fileName;

    if (type === 'image') {
        imgEl.src = url;
        imgEl.style.display = 'block';
    } else if (type === 'pdf') {
        // Add toolbar + fit width + scroll
        pdfEl.src = url + '#toolbar=1&navpanes=1&scrollbar=1&zoom=100&view=FitH';
        pdfEl.style.display = 'block';
    } else {
        noPrev.classList.remove('d-none');
    }

    dlLink.href = url;
    dlLink.download = fileName;

    modal.show();

    setTimeout(() => {
        document.querySelectorAll('.modal').forEach(el => {
            el.style.opacity = '1';
            el.style.backgroundColor = '#000';
        });
        if (type === 'pdf') pdfEl.contentWindow?.focus();
    }, 150);
}

document.getElementById('proofPreviewModal')?.addEventListener('hidden.bs.modal', () => {
    document.getElementById('modalProofImage').src = '';
    document.getElementById('modalProofPdf').src = '';
});

        function openProofPreview(url, type, fileName) {
        if (!url) {
            alert('No file available!');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('proofPreviewModal'));
        const imgEl   = document.getElementById('modalProofImg');
        const pdfEl   = document.getElementById('modalProofPdf');
        const noPrev  = document.getElementById('modalNoPreview');
        const dlLink  = document.getElementById('modalDownloadLink');
        const titleEl = document.getElementById('proofPreviewModalLabel');

        imgEl.style.display = 'none';
        pdfEl.style.display = 'none';
        noPrev.classList.add('d-none');

        titleEl.textContent = fileName;
        dlLink.href = url;
        dlLink.download = fileName;

        if (type === 'image') {
            imgEl.src = url;
            imgEl.style.display = 'block';
        } else if (type === 'pdf') {
            pdfEl.src = url + '#toolbar=1&navpanes=1&scrollbar=1&zoom=100';
            pdfEl.style.display = 'block';
        } else {
            noPrev.classList.remove('d-none');
        }

        modal.show();
    }

    function downloadFile(url, fileName) {
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    $('#proofPreviewModal').on('hidden.bs.modal', function () {
        $('#modalProofImg').attr('src', '');
        $('#modalProofPdf').attr('src', '');
    });

        let uploadedFile = null;

        function handleProofClick(url, type, fileName) {
            if (!url) {
                Swal.fire({ title: 'Error', text: 'No file available', icon: 'error' });
                return;
            }

            const modalEl = document.getElementById('proofPreviewModal');
            const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });

            const imgEl = document.getElementById('modalProofImg');
            const pdfEl = document.getElementById('modalProofPdf');
            const noPrev  = document.getElementById('modalNoPreview');
            const dlLink  = document.getElementById('modalDownloadLink');
            const titleEl = document.getElementById('proofPreviewModalLabel');

            imgEl.style.display = 'none';
            pdfEl.style.display = 'none';
            noPrev.classList.add('d-none');
            imgEl.src = '';
            pdfEl.src = '';

            titleEl.textContent = fileName;
            dlLink.href = url;
            dlLink.download = fileName;

            if (type === 'image') {
                imgEl.src = url;
                imgEl.style.display = 'block';
            } else if (type === 'pdf') {
                pdfEl.src = url + '#toolbar=1&navpanes=1&scrollbar=1&zoom=100&view=FitH';
                pdfEl.style.display = 'block';
            } else {
                noPrev.classList.remove('d-none');
            }

            modal.show();

            setTimeout(() => {
                document.querySelectorAll('.modal').forEach(el => {
                    el.style.opacity = '0.45';
                    el.style.backgroundColor = '#000';
                });
                if (type === 'pdf') pdfEl.contentWindow?.focus();
            }, 150);
        }

        function downloadFile(url, fileName) {
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        document.getElementById('proofPreviewModal')?.addEventListener('hidden.bs.modal', () => {
            document.getElementById('modalProofImage').src = '';
            document.getElementById('modalProofPdf').src = '';
        });
        
        function calculateRemaining() {
            const bookingInput = document.querySelector('input[name="booking_amount"]');
            const deductionInput = document.getElementById('deduction');
            const remainingInput = document.getElementById('remaining_amount');

            if (!bookingInput || !deductionInput || !remainingInput) {
                return;
            }

            let bookingAmount = parseFloat(bookingInput.value.replace(/[^0-9.-]+/g,"")) || 0;
            let deduction = parseFloat(deductionInput.value) || 0;
            let remaining = Math.max(0, bookingAmount - deduction);
            remainingInput.value = remaining.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            calculateRemaining();
        });
        function previewACP() {
            const fileInput = document.getElementById('ac_proof');
            const preview = document.getElementById('acp_preview');
            const pdfIcon = document.getElementById('pdf_icon');
            const discardBtn = document.getElementById('discardACP');
            const file = fileInput?.files[0];

            if (!file) return;

            if (file.type.startsWith('image/')) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
                pdfIcon.style.display = 'none';
            } else if (file.type === 'application/pdf') {
                preview.style.display = 'none';
                pdfIcon.style.display = 'block';
            }

            discardBtn.style.display = 'block';
        }

        function discardImageACP() {
            document.getElementById('ac_proof').value = '';
            document.getElementById('acp_preview').src = '';
            document.getElementById('acp_preview').style.display = 'none';
            document.getElementById('pdf_icon').style.display = 'none';
            document.getElementById('discardACP').style.display = 'none';
        }

        function previewAadhar() {
        const input = document.getElementById('aadhar_proof');
        const preview = document.getElementById('aadhar_preview');
        const pdf = document.getElementById('aadhar_pdf_icon');
        const discard = document.getElementById('discardAadhar');
        const file = input?.files[0];

        if (!file) return;

        if (file.type.startsWith('image/')) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            pdf.style.display = 'none';
        } else if (file.type === 'application/pdf') {
            preview.style.display = 'none';
            pdf.style.display = 'block';
        }
        discard.style.display = 'block';
    }

    function discardImageAadhar() {
        document.getElementById('aadhar_proof').value = '';
        document.getElementById('aadhar_preview').src = '';
        document.getElementById('aadhar_preview').style.display = 'none';
        document.getElementById('aadhar_pdf_icon').style.display = 'none';
        document.getElementById('discardAadhar').style.display = 'none';
    }

    function previewPan() {
        const input = document.getElementById('pan_proof');
        const preview = document.getElementById('pan_preview');
        const pdf = document.getElementById('pan_pdf_icon');
        const discard = document.getElementById('discardPan');
        const file = input?.files[0];

        if (!file) return;

        if (file.type.startsWith('image/')) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            pdf.style.display = 'none';
        } else if (file.type === 'application/pdf') {
            preview.style.display = 'none';
            pdf.style.display = 'block';
        }
        discard.style.display = 'block';
    }

    function discardImagePan() {
        document.getElementById('pan_proof').value = '';
        document.getElementById('pan_preview').src = '';
        document.getElementById('pan_preview').style.display = 'none';
        document.getElementById('pan_pdf_icon').style.display = 'none';
        document.getElementById('discardPan').style.display = 'none';
    }
     
        const deductionField = document.getElementById('deduction');
        if (deductionField) {
            deductionField.addEventListener('input', function() {
                calculateRemaining();
                const val = parseFloat(this.value) || 0;
                const booking = parseFloat(
                    document.querySelector('input[name="booking_amount"]')?.value.replace(/[^0-9.-]+/g,"") || 0
                );
                if (val > booking) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            flatpickr(".flatpickr", {
                dateFormat: "d-M-Y",
                maxDate: "today",
                allowInput: true,
                onChange: function(selectedDates, dateStr, instance) {
                    const hidden = instance.element.nextElementSibling;
                    if (selectedDates[0]) {
                        hidden.value = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                    } else {
                        hidden.value = "";
                    }
                }
            });
        });





        function toggleInvoiceFields() {
            const status = document.getElementById('status').value;
            const show = (status === "2");

            const fields = [
                'invoiceNumberField',
                'invoiceDateField',
                'dealerInvoiceNumberField',
                'dealerInvoiceDateField'
            ];

            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.classList.toggle('d-none', !show);
                }
            });

            if (!show) {
                ['invoice_number', 'dealer_invoice_number'].forEach(id => {
                    const input = document.getElementById(id);
                    if (input) input.value = '';
                });
                ['hidden_invoice_date', 'hidden_dealer_invoice_date'].forEach(id => {
                    const hidden = document.getElementById(id);
                    if (hidden) hidden.value = '';
                });
            }
        }

        function handleStatusChange() {
            const statusSelect = document.getElementById('status');
            const selectedStatus = statusSelect.value;

            const colType        = "{{ $booking->col_type ?? 0 }}";
            const bookingAmount  = parseFloat("{{ $booking->booking_amount ?? 0 }}");
            const pending        = {{ $booking->pending ?? 0 }};
            const bookingId      = {{ $booking->id }};

            
            if (["3", "4", "6"].includes(selectedStatus)) {
                if ([2, 3].includes(parseInt(colType))) {
                    statusSelect.disabled = true;
                
                    fetch("{{ route('booking.check-field-payment', $booking->id) }}", {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        statusSelect.disabled = false;
                    
                        if (data.success === false) {
                            let titleText = '';
                            if (selectedStatus === "3") titleText = 'Cannot Cancel Booking';
                            else if (selectedStatus === "4") titleText = 'Cannot Proceed with Delivery';
                            else if (selectedStatus === "6") titleText = 'Cannot Put Booking On Hold';
                        
                            Swal.fire({
                                icon: 'warning',
                                title: titleText,
                                html: `
                                    This is a <strong>Field Collection</strong> booking.<br>
                                    Total received: ₹ ${data.total_paid.toLocaleString('en-IN')}<br>
                                    Booking amount: ₹ ${bookingAmount.toLocaleString('en-IN')}<br><br>
                                    <strong>Amount is not fully satisfied.</strong><br>
                                    Please update/add receipts first.
                                `,
                                confirmButtonText: 'Go to Pending / Receipts',
                                showCancelButton: true,
                                cancelButtonText: 'Cancel Action',
                                allowOutsideClick: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "{{ route('booking.pending-edit', $booking->id) }}" + "#pending";
                                } else {
                                    statusSelect.value = "0";
                                }
                            });
                        }
                        else {
                            proceedWithNormalFlow(selectedStatus);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        statusSelect.disabled = false;
                        Swal.fire('Error', 'Could not verify payment status. Please try again.', 'error');
                        statusSelect.value = "0";
                    });
                
                    return;
                }
            }
        
            
            proceedWithNormalFlow(selectedStatus);
        }

        function proceedWithNormalFlow(selectedStatus) {
            if (selectedStatus === "2") { // Invoiced
                if ("{{ strtolower($booking->b_type ?? '') }}" === 'dummy') {
                    Swal.fire({
                        title: "Dummy Booking Detected!",
                        text: "Please activate it by adding a receipt...",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Add Receipt"
                    }).then(result => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ backpack_url('booking/:id/add-amount') }}".replace(':id', {{ $booking->id }});
                        } else {
                            document.getElementById('status').value = "0";
                        }
                    });
                    return;
                }
            
                if ({{ $booking->pending ?? 0 }} > 0) {
                    Swal.fire({
                        title: "Pending Items Exist!",
                        text: "Clear them before invoicing.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Go to Pending"
                    }).then(result => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ backpack_url('booking/:id/pending-edit') }}".replace(':id', {{ $booking->id }}) + "?pending_flag=1";
                        } else {
                            document.getElementById('status').value = "0";
                        }
                    });
                    return;
                }
            }
        
            
            toggleInvoiceFields();
        }

        
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr(".flatpickr", {
                dateFormat: "d-M-Y",
                maxDate: "today",
                allowInput: true,
                onChange: function(selectedDates, dateStr, instance) {
                    const hidden = instance.element.nextElementSibling;
                    if (selectedDates.length > 0) {
                        hidden.value = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                    } else {
                        hidden.value = "";
                    }
                }
            });
        });



        function previewPAY() {
            const input = document.getElementById('pay_proof');
            const preview = document.getElementById('payproof');
            const pdf = document.getElementById('pdf_icon');
            const discard = document.getElementById('discard-image');
            const file = input?.files[0];

            if (!file) return;

            if (file.type.startsWith('image/')) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
                pdf.style.display = 'none';
            } else if (file.type === 'application/pdf') {
                preview.style.display = 'none';
                pdf.style.display = 'block';
            }
            discard.style.display = 'block';
        }

        function discardImage() {
            document.getElementById('pay_proof').value = '';
            document.getElementById('payproof').src = '';
            document.getElementById('payproof').style.display = 'none';
            document.getElementById('pdf_icon').style.display = 'none';
            document.getElementById('discard-image').style.display = 'none';
        }


        // Reject Refund Button Toggle
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('rejectRefundBtn');
            const card = document.getElementById('rejectionCard');

            if (btn && card) {
                btn.addEventListener('click', function () {
                    card.style.display = 'block';
                });
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            const newProofInput = document.getElementById('newProofFile');
            if (newProofInput) {
                newProofInput.addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) return;
                
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Too Large',
                            text: 'File must be less than 2MB!',
                            confirmButtonColor: '#dc3545'
                        });
                        this.value = '';
                        return;
                    }
                
                    const fileURL = URL.createObjectURL(file);
                    const container = document.getElementById('newProofChipContainer');
                    container.innerHTML = "";
                
                    createFileChip(file.name, fileURL);   // aapka purana function
                    this.value = "";
                });
            }
        });

        document.getElementById('restoreBookingButton')?.addEventListener('click', function() {
            if (confirm('{{ __("Are you sure you want to restore this booking and cancel the refund request?") }}')) {
                document.getElementById('restoreForm').submit();
            }
        });

        flatpickr("#ref_date", {
            dateFormat: "d-M-Y",
            maxDate: "today",
            allowInput: true,
            onChange: function(selectedDates, dateStr, instance) {
                document.getElementById('hidden_ref').value = selectedDates.length > 0
                    ? flatpickr.formatDate(selectedDates[0], "Y-m-d")
                    : '';
            }
        });



    document.addEventListener('DOMContentLoaded', function () {

    const input = document.getElementById('newProofFile');
    if (!input) return;

    input.addEventListener('change', function () {

        const file = this.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File',
                text: 'File must be less than 2MB!',
                confirmButtonColor: '#dc3545'
            });
            this.value = "";
            return;
        }

        const fileURL = URL.createObjectURL(file);

        const container = document.getElementById('newProofChipContainer');
        container.innerHTML = "";

        createFileChip(file.name, fileURL);

        this.value = "";
    });

});


function createFileChip(fileName, fileURL)
{
    const container = document.getElementById('newProofChipContainer');

    const chip = document.createElement('span');

    chip.className = "btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2";

    chip.style.cursor = "pointer";

    chip.innerHTML = `
        <i class="la la-paperclip"></i>
        <span class="fw-medium small">
            ${fileName.length > 28 ? fileName.substring(0,25) + '...' : fileName}
        </span>
    `;

    chip.onclick = function () {
        openPaymentProofModal(fileURL, fileName);
    };

    container.appendChild(chip);
}


function openAttachmentModal(fileName, fileURL) {

    document.getElementById('modalFileName').innerText = fileName;
    document.getElementById('modalFilePreview').src = fileURL;
    document.getElementById('modalDownload').href = fileURL;

    var modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
modal.show();

}
        function toggleCard(cardId, iconId) {
            const body = document.querySelector(`#${cardId} .card-body`);
            const icon = document.getElementById(iconId);
            if (!body || !icon) return;

            if (body.style.display === 'none' || !body.style.display) {
                body.style.display = 'block';
                icon.classList.remove('ik-plus');
                icon.classList.add('ik-minus');
            } else {
                body.style.display = 'none';
                icon.classList.remove('ik-minus');
                icon.classList.add('ik-plus');
            }
        }

        document.getElementById('toggle-receipt-log')?.addEventListener('click', () => toggleCard('receipt-log-card', 'receipt-icon'));
        document.getElementById('toggle-refund-details')?.addEventListener('click', () => toggleCard('refund-details-card', 'refund-icon'));
        document.getElementById('toggle-remark')?.addEventListener('click', () => toggleCard('remark-card', 'remark-icon'));

        function removeAccImage() {

    Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, remove it!'
}).then((result) => {
    if (result.isConfirmed) {
        document.getElementById('remove-acc-input').value = "1";
        const chip = document.querySelector('#ac_proof_chip');
        if (chip) chip.innerHTML = '';
    }
});

    document.getElementById('remove-acc-input').value = "1";

    const chip = document.querySelector('#ac_proof_chip');
    if(chip) chip.innerHTML = '';

}

        function previewAccProof(event) {
            const file = event.target.files[0];
            if (!file) return;

            const container = document.getElementById('acc-preview-container');
            const img = document.getElementById('acc-preview-img');

            if (file.type.startsWith('image/')) {
                img.src = URL.createObjectURL(file);
                container.style.display = 'block';
            } else {
                alert('Please select an image file.');
                event.target.value = '';
            }
        }


        function calculateRemaining() {
        const bookingInput = document.querySelector('input[name="booking_amount"]');
        const deductionInput = document.getElementById('deduction');
        const remainingInput = document.getElementById('remaining_amount');

        if (!bookingInput || !deductionInput || !remainingInput) {
            return;
        }

        let bookingAmount = parseFloat(bookingInput.value.replace(/[^0-9.-]+/g, "")) || 0;
        let deduction    = parseFloat(deductionInput.value) || 0;
        let remaining    = Math.max(0, bookingAmount - deduction); // negative न हो

        remainingInput.value = remaining.toFixed(2);
    }

       document.addEventListener('DOMContentLoaded', function () {
        const deductionField = document.getElementById('deduction');
        if (deductionField) {
            deductionField.addEventListener('input', function () {
                calculateRemaining();

                const val = parseFloat(this.value) || 0;
                const bookingInput = document.querySelector('input[name="booking_amount"]');
                if (bookingInput) {
                    const booking = parseFloat(bookingInput.value.replace(/[^0-9.-]+/g, "")) || 0;
                    if (val > booking) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
            });

            calculateRemaining();
        }
    });



        function toggleCard(cardId, iconId) {
            const body = document.querySelector(`#${cardId} .card-body`);
            const icon = document.getElementById(iconId);
            if (!body || !icon) return;

            if (body.style.display === 'none' || !body.style.display) {
                body.style.display = 'block';
                icon.classList.remove('ik-plus');
                icon.classList.add('ik-minus');
            } else {
                body.style.display = 'none';
                icon.classList.remove('ik-minus');
                icon.classList.add('ik-plus');
            }
        }

        document.getElementById('toggle-receipt-log')?.addEventListener('click', () => toggleCard('receipt-log-card', 'receipt-icon'));
        document.getElementById('toggle-refund-details')?.addEventListener('click', () => toggleCard('refund-details-card', 'refund-icon'));
        document.getElementById('toggle-remark')?.addEventListener('click', () => toggleCard('remark-card', 'remark-icon'));

        const editBtn = document.getElementById('editRefundButton');
        if (editBtn) {
            editBtn.addEventListener('click', function() {
                document.querySelectorAll('#refund-details-form input:not([type="hidden"]), #refund-details-form select').forEach(el => {
                    if (!el.hasAttribute('readonly') && el.id !== 'ref_date') {
                        el.removeAttribute('disabled');
                        el.removeAttribute('readonly');
                    }
                });

                document.getElementById('pay_proof').style.display = 'block';

                this.style.display = 'none';
                document.getElementById('updateRefundButton').style.display = 'block';
            });
        }

        function previewPAY() {
            const input = document.getElementById('pay_proof');
            const preview = document.getElementById('payproof');
            const file = input?.files[0];

            if (!file) return;

            if (file.type.startsWith('image/')) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            } else if (file.type === 'application/pdf') {
                preview.src = "{{ asset('images/pdf-icon.png') }}"; // PDF placeholder
                preview.style.display = 'block';
            }
        }


        document.addEventListener('DOMContentLoaded', function() {
        });
let existingFdocUrl = "{{ $booking->fdoc ? asset($booking->fdoc) : '' }}";  // adjust path if needed
let existingFdocName = "{{ $booking->fdoc ? basename($booking->fdoc) : '' }}";

function handleFdocAttachment(input) {
    const previewDiv = document.getElementById('fdocProofPreview');
    previewDiv.innerHTML = '';

    if (input.files && input.files[0]) {
        const file = input.files[0];

        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
    icon: 'error',
    title: 'Invalid File',
    text: 'File must be less than 2MB!',
    confirmButtonColor: '#dc3545'
});
            input.value = '';
            return;
        }
        const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            Swal.fire({
    icon: 'error',
    title: 'Invalid File Type',
    text: 'Only JPG, PNG, PDF allowed!',
    confirmButtonColor: '#dc3545'
});
            input.value = '';
            return;
        }

        const fileURL = URL.createObjectURL(file);

        previewDiv.innerHTML = `
            <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                  style="cursor:pointer;"
                  onclick="openFdocProofModal('${fileURL}', '${file.name.replace(/'/g, "\\'")}')">
                <i class="la la-paperclip"></i>
                <span class="fw-medium small">${file.name}</span>
            </span>

        `;

        document.getElementById('delete_fdoc').value = '1';
    }
}

function openFdocProofModal(url, name) {
    document.getElementById('fdocProofModalLabel').innerText = name;
    document.getElementById('fdocProofDownload').href = url;

    const img = document.getElementById('fdocProofImg');
    const iframe = document.getElementById('fdocProofIframe');

    img.style.display = iframe.style.display = 'none';

    if (name.toLowerCase().endsWith('.pdf')) {
        iframe.src = url;
        iframe.style.display = 'block';
    } else {
        img.src = url;
        img.style.display = 'block';
    }

    $('#fdocProofModal').modal('show');
}



document.addEventListener('DOMContentLoaded', function () {
    if (existingFdocUrl && existingFdocName) {
        const previewDiv = document.getElementById('fdocProofPreview');
        previewDiv.innerHTML = `
            <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                  style="cursor:pointer;"
                  onclick="openFdocProofModal('${existingFdocUrl}', '${existingFdocName.replace(/'/g, "\\'")}')">
                <i class="la la-paperclip"></i>
                <span class="fw-medium small">${existingFdocName}</span>
            </span>

        `;
    }
});
document.addEventListener('DOMContentLoaded', function () {
    
    // Restore Cancelled Booking (Status 3)
    const restoreCancelledBtn = document.getElementById('restoreCancelledBtn');
    if (restoreCancelledBtn) {
        restoreCancelledBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Restore Cancelled Booking?',
                text: "Are you sure you want to restore this booking?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Restore It',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const activateForm = document.getElementById('activateForm');
                    if (activateForm) {
                        activateForm.submit();
                    }
                }
            });
        });
    }

    const restoreBookingButton = document.getElementById('restoreBookingButton');
    if (restoreBookingButton) {
        restoreBookingButton.addEventListener('click', function () {
            Swal.fire({
                title: 'Cancel Refund & Restore Booking?',
                text: "Are you sure you want to restore this booking and cancel the refund request?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Restore',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const restoreForm = document.getElementById('restoreForm');
                    if (restoreForm) restoreForm.submit();
                }
            });
        });
    }
});
// === Hide Empty/N/A Fields - Improved Version ===
// document.addEventListener('DOMContentLoaded', function () {

//     function hideEmptyFields() {
        
//         const selector = '.card-body .row .col-sm-3, ' +
//                         '.card-body .row .col-sm-4, ' +
//                         '.card-body .row .col-sm-2, ' +
//                         '.card-body .row .col-md-3';

//         const fields = document.querySelectorAll(selector);

//         fields.forEach(field => {
//             // Skip if this field is inside History card
//             if (field.closest('#receipt-log-card, #refund-details-card, .entity-history, [id*="history"], [id*="remark"]')) {
//                 return;
//             }

//             const input = field.querySelector('input');
//             if (!input) return;

//             const value = input.value.trim();

//             // === Rules for hiding ===
//             const isN_A = value === 'N/A' || value.toLowerCase() === 'n/a';
//             const isEmpty = value === '' || value === '-';

//             // Do NOT hide monetary fields that have ₹ or numbers (even if 0)
//             const hasRupee = value.includes('₹') || value.includes('0');
//             const isMonetaryField = 
//                 field.textContent.includes('Price') || 
//                 field.textContent.includes('Bonus') || 
//                 field.textContent.includes('Amount') ||
//                 field.textContent.includes('Gap') ||
//                 field.textContent.includes('Odometer');

//             if ((isN_A || isEmpty) && !hasRupee && !isMonetaryField) {
//                 field.style.display = 'none';
//             }
//         });

//         // Optional: Hide entire empty cards (except important ones)
//         const cardsToCheck = document.querySelectorAll('.card.card-body');
//         cardsToCheck.forEach(card => {
//             // Skip these cards completely
//             if (card.querySelector('#receipt-log-card, #refund-details-card, .entity-history, [id*="history"]')) {
//                 return;
//             }

//             const visibleCols = Array.from(card.querySelectorAll('.col-sm-3, .col-sm-4, .col-sm-2, .col-md-3'))
//                                     .filter(col => col.style.display !== 'none');

//             if (visibleCols.length === 0) {
//                 card.style.display = 'none';
//             }
//         });
//     }

//     // Execute
//     hideEmptyFields();

//     // Safety re-run
//     setTimeout(hideEmptyFields, 600);
// });
</script>

@endsection