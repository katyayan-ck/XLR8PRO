{{--
    =====================================================================
    resources/views/admin/booking/add.blade.php
    ---------------------------------------------------------------------
    Add / Edit Booking form.
    ---------------------------------------------------------------------
    OPTIMIZED VERSION — see changelog.md for the full previous-vs-new diff.

    What changed (details + reasoning in changelog.md):
      1. The six form sections (Payment, Customer, Referred By, Purchase
         Type, Vehicle, Booking Type & Source) are now uniform
         COLLAPSIBLE, DRAG-REORDERABLE cards (SortableJS). A user can
         collapse sections they're not editing ("max shown in minimum
         area") and drag sections into whatever order suits their
         workflow; the chosen order + collapsed state persists per
         browser via localStorage.
      2. Each card shows a live "X/Y filled" progress badge for its own
         required fields, so you can tell which sections still need
         attention without opening them.
      3. Collapsing a card never breaks validation: jQuery Validate still
         runs (fields are only CSS-hidden, not disabled), and submitting
         with an error inside a collapsed card now auto-expands that
         card so the highlighted field is visible (previously the error
         modal listed the message but the field itself could be hidden
         with nothing indicating which card to open).
      4. Every field keeps its exact original `name`/`id`, so
         BookingCrudController::store()/update() need no changes to
         read this form — the two files stay in lock-step by contract,
         not by accident.
      5. Every component below carries a short comment describing what
         it does and why (per the request to document the logic).
      6. No validation rule, AJAX endpoint, prefill value, or dropdown
         source was changed — only the presentation shell around them.
    =====================================================================
--}}
@php
    $entry = $entry ?? null;
    $isEdit = isset($entry);

    $q = $data['quotation']?->standard_data ?? [];
    if (is_string($q)) {
        $q = json_decode($q, true) ?? [];
    }

    $quotation = $data['quotation'] ?? null;
    $enquiry = $quotation?->enquiry ?? ($data['enquiry'] ?? null);

    $dobVal = $entry?->c_dob ?? ($enquiry?->dob ?? ($q['c_dob'] ?? ''));

    // Safely resolve finance mode without throwing undefined array key errors
    $hasQuotationFinancier = is_array($q) && !empty($q['financier']);
    $fmode = old($isEdit ? 'fin_mode' : 'finmode', $entry?->fin_mode ?? ($enquiry?->fin_mode ?? ($hasQuotationFinancier ? 'In-house' : '')));
        $bookingPaymentPrefill = $data['booking_payment_prefill'] ?? [
            'has_previous_payment' => false,
            'collection_type' => '',
            'receipt_no' => '',
            'receipt_date' => '',
            'payment_mode' => '',
            'total_amount' => 0,
        ];

        $hasPreviousPayment = $bookingPaymentPrefill['has_previous_payment'] ?? false;
@endphp
@extends(backpack_view('blank'))

@section('header')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="page-header">
            <div class="row align-items-end">
                <div class="col-lg-6">
                    <div class="page-header-title">
                        <i class="ik ik-car bg-blue"></i>
                        <h1 class="fw-bold mb-0">{{ $isEdit ? 'Edit Booking' : 'Add New Booking' }}</h1>
                    </div>
                </div>
                <div class="col-lg-6 text-end">
                    <nav class="breadcrumb-container" aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-end">
                            <li class="breadcrumb-item">
                                <a href="{{ backpack_url('dashboard') }}">
                                    <i class="ik ik-home"></i> Home
                                </a>
                            </li>
                            <li class="breadcrumb-item active">{{ $isEdit ? 'Edit Booking' : 'Add New Booking' }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="row">
            @include(backpack_view('inc.alerts'))

            <div class="col-md-12">
                <form id="bookingForm" class="forms-sample" method="POST"
                    action="{{ $isEdit ? backpack_url('sales/booking/' . $entry->id) : backpack_url('sales/booking') }}"
                    enctype="multipart/form-data">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    @if ($quotation)
                        {{-- Pass the ID because controller searches using: Quotation::where('id', $request->quotation_no) --}}
                        <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
                    @endif

                    @if ($enquiry)
                        <input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">
                    @endif

                    {{-- =========================================================
                         COLLAPSIBLE / DRAG-REORDERABLE FORM SECTIONS
                         ---------------------------------------------------------
                         Six .booking-card elements, one per logical section.
                         Each shares the same header chrome (drag handle,
                         title, "X/Y filled" progress badge, collapse chevron)
                         wired up once by initCardLayout() in after_scripts —
                         see that function for how drag/collapse/progress and
                         localStorage persistence work.
                         All FIELD MARKUP inside each card body is untouched
                         from the original form: same names, ids, options,
                         disabled/required wiring, and prefill logic, so the
                         controller and the existing JS below need no changes.
                    ========================================================= --}}
                    <div id="bookingCardsContainer" class="booking-cards-container">

                        {{-- ---------------------------------------------------
                             CARD 1 — Payment Details
                             Who's paying / how much / how was it collected.
                             Field-name pairs differ between create vs edit
                             mode ($isEdit ? 'db_column' : 'legacy_input_name')
                             because BookingCrudController::store() and
                             ::update() historically read two different key
                             sets — preserved as-is; unifying that is a
                             controller-level change, not a template one.
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="payment">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Payment Details</h2>

                                @if ($quotation)
                                    <div class="d-flex align-items-center gap-2 ms-2">
                                        <a href="{{ backpack_url('sales/quotation/' . $quotation->id . '/preview') }}"
                                            target="_blank" class="btn btn-info btn-sm">
                                            <i class="ik ik-file-text mr-2"></i> View Quotation PDF
                                        </a>
                                    </div>
                                @endif

                                <span class="booking-card__badge" data-progress-for="payment"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Payment Details">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="customertype">Customer Type <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'customer_type' : 'customertype' }}" id="customertype" class="form-control form-select"
                                                required>
                                                <option value="Actual" {{ old($isEdit ? 'customer_type' : 'customertype', $entry?->b_type ?? 'Actual') == 'Actual' ? 'selected' : '' }}>Actual</option>
                                                <option value="Dummy" {{ old($isEdit ? 'customer_type' : 'customertype', $entry?->b_type ?? '') == 'Dummy' ? 'selected' : '' }}>Dummy</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="customercat">
                                                Customer Category <span class="required-mark">*</span>
                                            </label>

                                            <select name="customercat"
                                                    id="customercat"
                                                    class="form-control form-select"
                                                    required>

                                                <option value="">Select Customer Category</option>

                                                @foreach ($customer_categories ?? [] as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('customercat', $enquiry?->customer_type ?? $entry?->b_cat ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="bookingdate">Booking Date <span class="required-mark">*</span></label>
                                            @php $bkDate = $entry?->booking_date ?? ''; @endphp
                                            <input type="text" name="bookingdate" id="bookingdate"
                                                class="form-control flatpickr" placeholder="dd-mmm-yyyy" required
                                                value="{{ old('bookingdate', site_date($bkDate, '')) }}">
                                            <input type="hidden" name="{{ $isEdit ? 'booking_date_actual' : 'hiddenbookingdate' }}" id="hiddenbookingdate"
                                                value="{{ old($isEdit ? 'booking_date_actual' : 'hiddenbookingdate', $bkDate) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="coltype">Collection Type <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'col_type' : 'coltype' }}" id="coltype" class="form-control form-select" required>
                                                <option value="" disabled selected>-- Select Collection Type --</option>
                                                @php
                                                    $colT = old(
                                                        $isEdit ? 'col_type' : 'coltype',
                                                        $entry?->col_type
                                                            ?? ($bookingPaymentPrefill['collection_type'] ?? '')
                                                    );
                                                @endphp
                                                <option value="1" {{ $colT == '1' ? 'selected' : '' }}>Receipt</option>
                                                <option value="2" {{ $colT == '2' ? 'selected' : '' }}>Field Collection By Sales Team</option>
                                                <option value="3" {{ $colT == '3' ? 'selected' : '' }}>Field Collection By DSA</option>
                                                <option value="4" {{ $colT == '4' ? 'selected' : '' }}>Used Car Purchase</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-lg-3">
                                        <div class="form-group">
                                            <label for="user">
                                                Collected By <span class="required-mark" style="display:none">*</span>
                                            </label>

                                            <select name="user" id="user" class="form-control form-select w-100"
                                                disabled>
                                                <option value="">Please Select...</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="bookingamount">Booking Amount <span
                                                    class="required-mark">*</span></label>
                                            <input type="text"
                                                name="{{ $isEdit ? 'booking_amount' : 'bookingamount' }}"
                                                id="bookingamount"
                                                class="form-control"
                                                required
                                                value="{{ old(
                                                    $isEdit ? 'booking_amount' : 'bookingamount',
                                                    $entry?->booking_amount
                                                        ?? ($bookingPaymentPrefill['total_amount'] ?? '')
                                                ) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group" id="receiptvouchergroup">
                                            <label id="receiptvoucherlabel">Receipt No. <span
                                                    class="required-mark">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'receipt_no' : 'receiptvoucherno' }}" id="receiptvoucherinput"
                                                class="form-control" required placeholder="12345"
                                                value="{{ old(
                                                    $isEdit ? 'receipt_no' : 'receiptvoucherno',
                                                    $entry?->receipt_no
                                                        ?? ($bookingPaymentPrefill['receipt_no'] ?? '')
                                                ) }}">
                                            <div id="receiptvoucherwarning" class="text-danger" style="display: none;">Number
                                                already exists</div>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="receiptdate">Receipt Date <span class="required-mark">*</span></label>
                                            @php
                                                $rcDate = $entry?->receipt_date
                                                    ?? ($bookingPaymentPrefill['receipt_date'] ?? '');
                                            @endphp
                                            <input type="text" name="receiptdate" id="receiptdate"
                                                class="form-control flatpickr" placeholder="dd-mmm-yyyy" required
                                                value="{{ old('receiptdate', site_date($rcDate, '')) }}">
                                            <input type="hidden" name="{{ $isEdit ? 'receipt_date_actual' : 'hiddenreceiptdate' }}" id="hiddenreceiptdate"
                                                value="{{ old($isEdit ? 'receipt_date_actual' : 'hiddenreceiptdate', $rcDate) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="mode">
                                                Mode <span class="required-mark">*</span>
                                            </label>

                                            @php
                                                $paymentMode = old(
                                                    'mode',
                                                    $entry?->payment_mode
                                                        ?? ($bookingPaymentPrefill['payment_mode'] ?? '')
                                                );
                                            @endphp

                                            <select name="mode" id="mode" class="form-control form-select" required>

                                                <option value="" disabled {{ $paymentMode === '' ? 'selected' : '' }}>
                                                    -- Select Mode --
                                                </option>

                                                <option value="Cash" {{ $paymentMode === 'Cash' ? 'selected' : '' }}>
                                                    Cash
                                                </option>

                                                <option value="Cheque" {{ $paymentMode === 'Cheque' ? 'selected' : '' }}>
                                                    Cheque
                                                </option>

                                                <option value="Bank Transfer" {{ $paymentMode === 'Bank Transfer' ? 'selected' : '' }}>
                                                    Bank Transfer
                                                </option>

                                                <option value="UPI" {{ $paymentMode === 'UPI' ? 'selected' : '' }}>
                                                    UPI
                                                </option>

                                            </select>
                                        </div>
                                    </div>

                                    @if (!$isEdit)
                                    <div class="col-sm-3">
                                        <div class="form-group" id="proofUploadGroup">
                                            <label for="fdoc">
                                                Upload Image or PDF
                                                <span class="required-mark">*</span>
                                            </label>

                                            <input type="file"
                                                name="amountproof"
                                                id="proofInput"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                required>

                                            <div id="proofPreview" class="mt-3"></div>
                                        </div>
                                    </div>
                                @endif
                                </div>
                            </div>
                        </div>
                        @if (!$isEdit && !empty($data['booking_payment_logs']) && $data['booking_payment_logs']->isNotEmpty())

                        <div class="col-12 mt-4">
                            <div class="border rounded bg-light">

                                <div class="px-3 py-2 border-bottom">
                                    <h5 class="mb-0 fw-semibold">
                                        Previous Receipts / Vouchers
                                    </h5>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:70px;">S.No</th>
                                                <th>Instrument No.</th>
                                                <th>Instrument Type</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th style="width:100px;">View</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            @foreach ($data['booking_payment_logs'] as $index => $payment)

                                                @php
                                                    $isVoucher = (int) $payment->type === 4;

                                                    $paymentNumber = $payment->type_number
                                                        ?? $payment->reciept
                                                        ?? '—';

                                                    $paymentDate = $payment->date
                                                        ? site_date($payment->date)
                                                        : '—';

                                                    $paymentAmount = (float) ($payment->amount ?? 0);

                                                    $proofUrl = $payment->getFirstMediaUrl('amount-proof');
                                                @endphp

                                                <tr>
                                                    <td>
                                                        {{ $index + 1 }}
                                                    </td>

                                                    <td>
                                                        <span class="fw-semibold">
                                                            {{ $paymentNumber }}
                                                        </span>

                                                        <div class="small text-muted">
                                                            {{ $isVoucher ? 'Voucher' : 'Receipt' }}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        {{ $payment->mode ?? '—' }}
                                                    </td>

                                                    <td>
                                                        {{ $paymentDate }}
                                                    </td>

                                                    <td>
                                                        ₹ {{ number_format($paymentAmount, 2) }}
                                                    </td>

                                                    <td>
                                                        @if ($proofUrl)
                                                            <a href="{{ $proofUrl }}"
                                                            target="_blank"
                                                            class="btn btn-outline-primary btn-sm">
                                                                <i class="la la-eye"></i>
                                                                View
                                                            </a>
                                                        @else
                                                            <span class="text-muted">
                                                                —
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>

                                            @endforeach

                                        </tbody>

                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">
                                                    Total Amount
                                                </th>

                                                <th>
                                                    ₹ {{ number_format($bookingPaymentPrefill['total_amount'] ?? 0, 2) }}
                                                </th>

                                                <th></th>
                                            </tr>
                                        </tfoot>

                                    </table>
                                </div>

                            </div>
                        </div>

                    @endif

                        {{-- ---------------------------------------------------
                             CARD 2 — Customer Details
                             Identity (PAN/Aadhar/GSTN/name), contact info,
                             and the customer's address (pincode-driven
                             VPO/Tehsil/District/State cascade — see the
                             India Post pincode lookup in after_scripts).
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="customer">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Customer Details</h2>
                                <span class="booking-card__badge" data-progress-for="customer"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Customer Details">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">

                                    <!-- PAN No -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="panno">{{ __('booking.fields.pan_number') }}</label>
                                            <input type="text" name="{{ $isEdit ? 'pan_no' : 'panno' }}" id="panno" class="form-control"
                                                value="{{ old($isEdit ? 'pan_no' : 'panno', $entry?->pan_no ?? ($enquiry->pan_no ?? ($q['panno'] ?? ''))) }}">
                                        </div>
                                    </div>

                                    <!-- Aadhar No -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="adharno">{{ __('booking.fields.aadhaar_number') }}</label>
                                            <input type="text" name="{{ $isEdit ? 'adhar_no' : 'adharno' }}" id="adharno" class="form-control"
                                                value="{{ old($isEdit ? 'adhar_no' : 'adharno', $entry?->adhar_no ?? ($enquiry->adhar_no ?? ($q['adharno'] ?? ''))) }}">
                                        </div>
                                    </div>

                                    <!-- GSTN -->
                                    <div class="col-sm-3">
                                        <div class="form-group" id="gstn-group">
                                            <label for="gstn">{{ __('booking.fields.gstin') }} <span class="required-mark"
                                                    style="display: none;">*</span></label>
                                            <input type="text" name="gstn" id="gstn" class="form-control"
                                                placeholder="Enter GSTN No." disabled
                                                value="{{ old('gstn', $entry?->gstn ?? ($enquiry->gstn ?? ($q['gstn'] ?? ''))) }}">
                                            <div class="form-check mt-1">
                                                <input type="checkbox" id="notrequiredgst" name="{{ $isEdit ? 'gst_unregistered' : 'notrequiredgst' }}"
                                                    class="form-check-input" checked>
                                                <label for="notrequiredgst" class="form-check-label">GST Unregistered</label>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Customer Name -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label id="customernamelabel" for="name">Customer Name <span
                                                    class="required-mark">*</span></label>
                                            <input type="text" name="name" id="name" class="form-control"
                                                required value="{{ old('name', $entry?->name ?? ($enquiry->name ?? ($q['name'] ?? ''))) }}">
                                        </div>
                                    </div>

                                    <!-- Care Of -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="careof">Care Of <span class="required-mark">*</span></label>
                                            @php
                                                $careOfType = old(
                                                    $isEdit ? 'care_of' : 'careof',
                                                    $entry?->care_of_type
                                                        ?? $enquiry?->care_of_type
                                                        ?? $q['care_of_type']
                                                        ?? ''
                                                );
                                            @endphp

                                            <select name="{{ $isEdit ? 'care_of' : 'careof' }}"
                                                    id="careof"
                                                    class="form-control form-select"
                                                    required>

                                                <option value="">Please Select...</option>

                                                <option value="1" {{ (string)$careOfType === '1' ? 'selected' : '' }}>
                                                    Son of
                                                </option>

                                                <option value="2" {{ (string)$careOfType === '2' ? 'selected' : '' }}>
                                                    Daughter of
                                                </option>

                                                <option value="3" {{ (string)$careOfType === '3' ? 'selected' : '' }}>
                                                    Married to
                                                </option>

                                                <option value="4" {{ (string)$careOfType === '4' ? 'selected' : '' }}>
                                                    Guardian Name
                                                </option>

                                                <option value="5" id="ownedByOption"
                                                    style="display: none;"
                                                    {{ (string)$careOfType === '5' ? 'selected' : '' }}>
                                                    Owned By
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Care Of Name -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label id="careofnamelabel">Care Of Name <span
                                                    class="required-mark">*</span></label>
                                            @php
                                                $careOfName = old(
                                                    $isEdit ? 'care_of_name' : 'careofname',
                                                    $entry?->care_of
                                                        ?? $enquiry?->care_of
                                                        ?? $data['care_of']
                                                        ?? $q['care_of']
                                                        ?? ''
                                                );
                                            @endphp
                                            <input type="text"
                                                name="{{ $isEdit ? 'care_of_name' : 'careofname' }}"
                                                id="careofname"
                                                class="form-control uppercase"
                                                value="{{ $careOfName }}">
                                        </div>
                                    </div>

                                    <!-- Mobile -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="mobile">{{ __('booking.fields.mobile') }} <span class="required-mark">*</span></label>
                                            <input type="text" name="mobile" id="mobile" class="form-control"
                                                required maxlength="10"
                                                value="{{ old('mobile', $entry?->mobile ?? ($enquiry->mobile ?? ($q['mobile'] ?? ''))) }}">
                                        </div>
                                    </div>

                                    <!-- Alternate Mobile -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="altmobile">{{ __('booking.fields.alt_mobile') }}</label>
                                            <input type="text" name="{{ $isEdit ? 'alt_mobile' : 'altmobile' }}" id="altmobile" class="form-control"
                                                value="{{ old($isEdit ? 'alt_mobile' : 'altmobile', $entry?->alt_mobile ?? ($enquiry->alternate_mobile ?? ($q['alt_mobile'] ?? ''))) }}">
                                        </div>
                                    </div>

                                    <!-- Gender -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="gender">{{ __('booking.fields.gender') }} <span class="required-mark">*</span></label>
                                            <select name="gender" id="gender" class="form-control form-select" required>
                                                @php $gndr = old('gender', $entry?->gender ?? ($enquiry->gender ?? ($q['gender'] ?? ''))); @endphp
                                                <option value="Male" {{ $gndr == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ $gndr == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Transgender" {{ $gndr == 'Transgender' ? 'selected' : '' }}>Transgender</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="occupation">
                                                {{ __('booking.fields.occupation') }}
                                                <span class="required-mark">*</span>
                                            </label>

                                            @php
                                                $occ = old(
                                                    'occupation',
                                                    $entry?->occ
                                                        ?? $enquiry?->occupation_type
                                                        ?? ($q['occ'] ?? '')
                                                );
                                            @endphp

                                            <select name="occupation"
                                                    id="occupation"
                                                    class="form-control form-select"
                                                    required>

                                                <option value="">-- Select Occupation --</option>

                                                @foreach ($occupation_types ?? [] as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ (string) $occ === (string) $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>

                                    <!-- Customer D.O.B. -->
                                    <div class="col-sm-3">
                                        <div class="form-group" id="dob-group">
                                            <label for="customerdob">{{ __('booking.fields.customer_dob') }} <span class="required-mark">*</span></label>
                                            @php $dobVal = $entry?->c_dob ?? ($enquiry->dob ?? ($q['c_dob'] ?? '')); @endphp
                                            <input type="text" name="customerdob" id="customerdob" class="form-control"
                                                placeholder="dd-mmm-yyyy" required
                                                value="{{ old('customerdob', site_date($dobVal, '')) }}">
                                            <input type="hidden" name="{{ $isEdit ? 'hidden_customer_dob' : 'hiddencustomerdob' }}" id="hiddencustomerdob"
                                                value="{{ old($isEdit ? 'hidden_customer_dob' : 'hiddencustomerdob', $dobVal) }}">
                                        </div>
                                    </div>

                                    <!-- Customer Age -->
                                    <div class="col-sm-3">
                                        <div class="form-group" id="age-group">
                                            <label for="customerage">Customer Age</label>
                                            <input type="text" name="customerage" id="customerage" class="form-control" readonly>
                                        </div>
                                    </div>

                                    {{-- ================= CUSTOMER ADDRESS DETAILS ================= --}}
                                    <div class="col-sm-2 mb-2">
                                        <label>Pin Code <span class="text-danger">*</span></label>
                                        <input type="text" id="zipcode" name="pincode" maxlength="6"
                                            class="form-control" value="{{ old('pincode', $entry?->pincode ?? ($enquiry->zipcode ?? '')) }}" required>
                                    </div>

                                    <div class="col-sm-2">
                                        <label>VPO <span class="text-danger">*</span></label>
                                        <select id="vpo_select" class="form-control form-select" required>
                                            <option value="">Select VPO</option>
                                        </select>
                                        <input type="text" id="vpo_input" class="form-control mt-2 d-none"
                                            placeholder="Enter VPO Manually" value="{{ old('vpo', $entry?->vpo ?? ($enquiry->vpo ?? '')) }}">
                                    </div>

                                    <div class="col-sm-2">
                                        <label>Tehsil <span class="text-danger">*</span></label>
                                        <select id="tehsil_select" class="form-control form-select" required>
                                            <option value="">Select Tehsil</option>
                                        </select>
                                        <input type="text" id="tehsil_input" class="form-control mt-2 d-none"
                                            placeholder="Enter Tehsil Manually"
                                            value="{{ old('customer_tehsil', $entry?->tehsil ?? ($enquiry->tehsil ?? '')) }}">
                                    </div>

                                    <div class="col-sm-2">
                                        <label>District <span class="text-danger">*</span></label>
                                        <select id="district_select" class="form-control form-select" required>
                                            <option value="">Select District</option>
                                        </select>
                                        <input type="text" id="district_input" class="form-control mt-2 d-none"
                                            placeholder="Enter District Manually"
                                            value="{{ old('customer_district', $entry?->district ?? ($enquiry->district ?? '')) }}">
                                    </div>

                                    <div class="col-sm-2">
                                        <label>State <span class="text-danger">*</span></label>
                                        <select id="state_select" class="form-control form-select" required>
                                            <option value="">Select State</option>
                                        </select>
                                        <input type="text" id="state_input" class="form-control mt-2 d-none"
                                            placeholder="Enter State Manually"
                                            value="{{ old('city', $entry?->city ?? ($enquiry->city ?? '')) }}">
                                    </div>

                                    <div class="col-sm-2">
                                        <label>Territory <span class="text-danger">*</span></label>
                                        <select id="territory" name="territory" class="form-control form-select" required>
                                            <option value="">Select Territory</option>
                                            @php $terr = old('territory', $entry?->territory ?? ($enquiry->territory ?? '')); @endphp
                                            <option value="OWN TERRITORY" {{ $terr == 'OWN TERRITORY' ? 'selected' : '' }}>OWN TERRITORY</option>
                                            <option value="OTHER TERRITORY" {{ $terr == 'OTHER TERRITORY' ? 'selected' : '' }}>OTHER TERRITORY</option>
                                        </select>
                                    </div>

                                    <!-- Sale Type -->
                                    <div class="col-sm-3">
                                        <label>Sale Type <span class="text-danger">*</span></label>
                                        @php
                                            $saleType = old('sale_type', $entry?->sale_type ?? '');
                                        @endphp

                                        <select id="sale_type"
                                                name="sale_type"
                                                class="form-control form-select"
                                                required>

                                            <option value="">Please Select...</option>

                                            <option value="1" {{ (string)$saleType === '1' ? 'selected' : '' }}>
                                                Within State
                                            </option>

                                            <option value="2" {{ (string)$saleType === '2' ? 'selected' : '' }}>
                                                Outside State
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="branch">{{ __('booking.fields.branch') }} <span class="required-mark">*</span></label>
                                            <select name="branch" id="branch" class="form-control form-select" required>
                                                <option value="" disabled {{ empty($entry?->branch_code) && empty($enquiry->dealer_branch) ? 'selected' : '' }}>-- Select Branch --</option>
                                                @foreach ($data['branches'] ?? [] as $branch)
                                                    <option value="{{ $branch->code ?? $branch->branch_code ?? '' }}"
                                                        {{ old('branch', $entry?->branch_code ?? ($enquiry->dealer_branch ?? '')) == ($branch->code ?? $branch->branch_code ?? '') ? 'selected' : '' }}>
                                                        {{ $branch->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="location">{{ __('booking.fields.location') }} <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'location_id' : 'location' }}" id="location" class="form-control form-select" required disabled>
                                                <option value="" disabled selected>-- Select Location --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group" id="othloc">
                                            <label for="locationother">{{ __('booking.fields.location_other') }} <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'location_other' : 'locationother' }}" id="locationother"
                                                class="form-control" disabled value="{{ old($isEdit ? 'location_other' : 'locationother', $entry?->location_other ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ---------------------------------------------------
                             CARD 3 — Referred By Details
                             Disabled/optional block: enabling the
                             "Referred By" checkbox turns on and requires
                             the five fields below it (see bindEventListeners()
                             in after_scripts).
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="referred">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Referred By Details</h2>
                                <span class="booking-card__badge" data-progress-for="referred"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Referred By Details">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">
                                    <div class="col-sm-1">
                                        <div class="form-group">
                                            <label><input type="checkbox" id="referredby" name="referredby" {{ old('referredby', $entry?->r_name ? 'on' : '') ? 'checked' : '' }}> Referred By</label>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="refcustomername">Customer Name <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'ref_customer_name' : 'refcustomername' }}" id="refcustomername"
                                                class="form-control" disabled value="{{ old($isEdit ? 'ref_customer_name' : 'refcustomername', $entry?->r_name ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="refmobileno">Mobile No. <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'ref_mobile_no' : 'refmobileno' }}" id="refmobileno" class="form-control"
                                                disabled value="{{ old($isEdit ? 'ref_mobile_no' : 'refmobileno', $entry?->r_mobile ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="refexistingmodel">Existing Model <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'ref_existing_model' : 'refexistingmodel' }}" id="refexistingmodel"
                                                class="form-control" disabled value="{{ old($isEdit ? 'ref_existing_model' : 'refexistingmodel', $entry?->r_model ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="refvariant">Variant <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'ref_variant' : 'refvariant' }}" id="refvariant" class="form-control"
                                                disabled value="{{ old($isEdit ? 'ref_variant' : 'refvariant', $entry?->r_variant ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="refchassisregno">Chassis No. / Regn. No. <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'ref_chassis_reg_no' : 'refchassisregno' }}" id="refchassisregno"
                                                class="form-control" disabled value="{{ old($isEdit ? 'ref_chassis_reg_no' : 'refchassisregno', $entry?->r_chassis ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ---------------------------------------------------
                             CARD 4 — Purchase Type Details
                             Existing-vehicle trade-in / exchange / scrappage
                             details. Which fields are required depends on
                             Purchase Type (see togglePurchaseFields() in
                             after_scripts); "Price Gap" is a read-only,
                             client-computed value (expected - offered - bonus).
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="purchase">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Purchase Type Details</h2>
                                <span class="booking-card__badge" data-progress-for="purchase"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Purchase Type Details">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="buyertype">Purchase Type <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'buyer_type' : 'buyertype' }}" id="buyertype" class="form-control form-select" required>
                                                <option value="" disabled {{ empty($entry?->buyer_type) && empty($enquiry->purchase_type) ? 'selected' : '' }}>-- Select Purchase Type --</option>
                                                @php
                                                    $ptype = old(
                                                        $isEdit ? 'buyer_type' : 'buyertype',
                                                        $entry?->buyer_type ?? ($enquiry->purchase_type ?? '')
                                                    );

                                                    // Normalize Purchase Type so DB/key-value capitalization
                                                    // does not affect dropdown selection.
                                                    $ptypeNormalized = strtolower(trim((string) $ptype));
                                                @endphp

                                                <option value="First Time Buy"
                                                    {{ $ptypeNormalized === 'first time buy' ? 'selected' : '' }}>
                                                    First Time Buyer
                                                </option>

                                                <option value="Additional Buy"
                                                    {{ $ptypeNormalized === 'additional buy' ? 'selected' : '' }}>
                                                    Additional Buy
                                                </option>

                                                <option value="Exchange Buy"
                                                    {{ $ptypeNormalized === 'exchange buy' ? 'selected' : '' }}>
                                                    Exchange Buy
                                                </option>

                                                <option value="Scrappage"
                                                    {{ $ptypeNormalized === 'scrappage' ? 'selected' : '' }}>
                                                    Scrappage
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="enummaster1">Brand Make 1 <span class="required-mark" style="display: none;">*</span></label>
                                            <select name="enummaster1" id="enummaster1" class="form-control form-select" disabled>
                                                <option value="" disabled selected>-- Select Brand Make 1 --</option>
                                                @foreach ($data['enum_master'] ?? [] as $enum)
                                                    <option value="{{ $enum->code ?? ($enum['code'] ?? '') }}"
                                                        {{ old('enummaster1', $entry?->exist_oem1 ?? ($enquiry->brand_make ?? '')) == ($enum->code ?? ($enum['code'] ?? '')) ? 'selected' : '' }}>
                                                        {{ $enum->value ?? ($enum['value'] ?? '') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="vehicledetails">Model Variant 1 <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'vehicle_details' : 'vehicledetails' }}" id="vehicledetails"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'vehicle_details' : 'vehicledetails', $entry?->vh1_detail ?? ($enquiry->brand_model ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="enummaster2">Brand Make 2</label>
                                            <select name="enummaster2" id="enummaster2" class="form-control form-select" disabled>
                                                <option value="" disabled selected>-- Select Brand Make 2 --</option>
                                                @foreach ($data['enum_master'] ?? [] as $enum)
                                                    <option value="{{ $enum->code ?? ($enum['code'] ?? '') }}"
                                                        {{ old('enummaster2', $entry?->exist_oem2 ?? ($enquiry->consid_brand2 ?? '')) == ($enum->code ?? ($enum['code'] ?? '')) ? 'selected' : '' }}>
                                                        {{ $enum->value ?? ($enum['value'] ?? '') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="vehicledetails2">Model Variant 2</label>
                                            <input type="text" name="{{ $isEdit ? 'vehicle_details2' : 'vehicledetails2' }}" id="vehicledetails2"
                                                class="form-control" disabled value="{{ old($isEdit ? 'vehicle_details2' : 'vehicledetails2', $entry?->vh2_detail ?? ($enquiry->consid_model2 ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="registrationno">Vehicle Registration No. <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'registration_no' : 'registrationno' }}" id="registrationno"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'registration_no' : 'registrationno', $entry?->registration_no ?? ($enquiry->vehicle_no ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="manufacturingyear">Vehicle Manufacturing Year <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="number" name="{{ $isEdit ? 'manufacturing_year' : 'manufacturingyear' }}" id="manufacturingyear"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'manufacturing_year' : 'manufacturingyear', $entry?->make_year ?? ($enquiry->make_year ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="odometerreading">Vehicle Odometer Reading <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'odometer_reading' : 'odometerreading' }}" id="odometerreading"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'odometer_reading' : 'odometerreading', $entry?->odo_reading ?? ($enquiry->odo_reading ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="expectedprice">Used Vehicle Expected Price <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="number" name="{{ $isEdit ? 'expected_price' : 'expectedprice' }}" id="expectedprice"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'expected_price' : 'expectedprice', $entry?->expected_price ?? ($enquiry->expected_price ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="offeredprice">Used Vehicle Offered Price <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="number" name="{{ $isEdit ? 'offered_price' : 'offeredprice' }}" id="offeredprice" class="form-control"
                                                disabled value="{{ old($isEdit ? 'offered_price' : 'offeredprice', $entry?->offered_price ?? ($enquiry->offered_price ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="exchangebonus">New Vehicle Exchange Bonus <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="number" name="{{ $isEdit ? 'exchange_bonus' : 'exchangebonus' }}" id="exchangebonus"
                                                class="form-control" disabled
                                                value="{{ old($isEdit ? 'exchange_bonus' : 'exchangebonus', $entry?->exchange_bonus ?? ($enquiry->exchange_bonus ?? '')) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="difference">Price Gap</label>
                                            @php
                                                $diffVal = ($entry?->expected_price ?? $enquiry->expected_price ?? 0) - ($entry?->offered_price ?? $enquiry->offered_price ?? 0) - ($entry?->exchange_bonus ?? $enquiry->exchange_bonus ?? 0);
                                            @endphp
                                            <input type="text" id="difference" class="form-control" disabled value="{{ $diffVal }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ---------------------------------------------------
                             CARD 5 — Vehicle Details
                             Segment → Model → Variant → Color cascade (each
                             AJAX-populated from the previous selection —
                             see the #segment/#model/#variant/#color change
                             handlers in after_scripts) plus Accessories
                             (multi-select, price-summed live into
                             "Accessories Amount").
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="vehicle">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Vehicle Details</h2>
                                <span class="booking-card__badge" data-progress-for="vehicle"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Vehicle Details">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">
                                    <!-- Segment -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="segment">Segment <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'segment_id' : 'segment' }}" id="segment" class="form-control form-select" required>
                                                <option value="">Please Select Segment...</option>
                                                @foreach ($data['segments'] ?? [] as $segment)
                                                    <option value="{{ $segment->code ?? $segment->id ?? '' }}"
                                                        {{ old($isEdit ? 'segment_id' : 'segment', $entry?->segment_code ?? ($q['segment_code'] ?? ($enquiry->segment_code ?? ''))) == ($segment->code ?? $segment->id ?? '') ? 'selected' : '' }}>
                                                        {{ $segment->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Model -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="model">Model <span class="required-mark">*</span></label>
                                            <select name="model" id="model" class="form-control form-select" required>
                                                <option value="">Please Select Model...</option>
                                                @foreach ($data['models'] ?? [] as $model)
                                                    <option value="{{ $model->code ?? $model->id ?? '' }}"
                                                        {{ old('model', $entry?->model_code ?? ($q['model_code'] ?? ($enquiry->model_code ?? ''))) == ($model->code ?? $model->id ?? '') ? 'selected' : '' }}>
                                                        {{ $model->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Variant -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="variant">Variant <span class="required-mark">*</span></label>
                                            <select name="variant" id="variant" class="form-control form-select" required>
                                                <option value="">Please Select Variant...</option>
                                                @foreach ($data['variants'] ?? [] as $variant)
                                                    <option value="{{ $variant->code ?? $variant->id ?? '' }}"
                                                        {{ old('variant', $entry?->variant_code ?? ($q['variant_code'] ?? ($enquiry->variant_code ?? ''))) == ($variant->code ?? $variant->id ?? '') ? 'selected' : '' }}
                                                        data-seating="{{ $variant->seating_capacity ?? 0 }}">
                                                        {{ $variant->display_name ?? ($variant->name ?? ($variant->code ?? '')) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Color -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="color">Color <span class="required-mark">*</span></label>
                                            <select name="color" id="color" class="form-control form-select" required>
                                                <option value="">Please Select Color...</option>
                                                @foreach ($data['colors'] ?? [] as $color)
                                                    <option value="{{ $color->code ?? $color->id ?? '' }}"
                                                        {{ old('color', $entry?->color_code ?? ($q['color_code'] ?? ($enquiry->color_code ?? ''))) == ($color->code ?? $color->id ?? '') ? 'selected' : '' }}>
                                                        {{ $color->name ?? ($color->code ?? '') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" id="vhid" name="vhid">
                                        </div>
                                    </div>

                                    <!-- Seating -->
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="seating">Seating</label>
                                            <input type="text" name="seating" id="seating" class="form-control"
                                                value="{{ old('seating', $entry?->seating ?? ($q['seating'] ?? ($quotation?->variant?->seating_capacity ?? ($enquiry->seating ?? 0)))) }}">
                                        </div>
                                    </div>

                                    <!-- Accessories -->
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label>Select Accessories</label>
                                            <select name="accessories[]" id="accessories" class="form-select" multiple>
                                                @php
                                                    $rawAcc = $entry?->accessories ?? ($q['accessories'] ?? []);
                                                    $selectedAccessories = is_array($rawAcc) ? $rawAcc : explode(',', $rawAcc);
                                                @endphp
                                                @foreach ($data['accessories_dropdown'] ?? [] as $accessory)
                                                    <option value="{{ $accessory['part_no'] ?? ($accessory->part_no ?? '') }}"
                                                        data-price="{{ $accessory['ndp'] ?? ($accessory->ndp ?? 0) }}"
                                                        {{ in_array($accessory['part_no'] ?? ($accessory->part_no ?? ''), $selectedAccessories) ? 'selected' : '' }}>
                                                        {{ $accessory['item'] ?? ($accessory->item ?? '') }}
                                                        (₹{{ number_format($accessory['ndp'] ?? ($accessory->ndp ?? 0), 2) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Accessories Amount -->
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label>Accessories Amount</label>
                                            <input type="text" name="{{ $isEdit ? 'apack_amount' : 'apackamount' }}" id="apackamount" class="form-control"
                                                value="{{ old($isEdit ? 'apack_amount' : 'apackamount', $entry?->apack_amount ?? ($q['accessories_amount'] ?? 0)) }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="chassis">Allotted Chassis No.</label>
                                            <select name="chassis" id="chassis" class="form-control select2" disabled>
                                                <option value="0">Please Select...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ---------------------------------------------------
                             CARD 6 — Booking Type & Source
                             How the booking was sourced (Dealer/Online,
                             Dealer/DSA), who the sales consultant is,
                             delivery timing, and finance mode. Finance
                             mode drives whether Financier/Loan-Status are
                             required (In-house only) — see
                             toggleFinanceFields() in after_scripts.
                        --------------------------------------------------- --}}
                        <div class="card booking-card" data-card-id="booking-source">
                            <div class="booking-card__header">
                                <span class="booking-card__handle" title="Drag to reorder"><i class="la la-ellipsis-v"></i></span>
                                <h2 class="booking-card__title">Booking Type &amp; Source</h2>
                                <span class="booking-card__badge" data-progress-for="booking-source"></span>
                                <button type="button" class="booking-card__toggle" aria-label="Collapse/expand Booking Type & Source">
                                    <i class="la la-chevron-up"></i>
                                </button>
                            </div>
                            <div class="booking-card__body">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="bookingmode">Booking Mode <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'booking_mode' : 'bookingmode' }}" id="bookingmode" class="form-control form-select" required>
                                                <option value="Dealer" {{ old($isEdit ? 'booking_mode' : 'bookingmode', $entry?->b_mode ?? '') == 'Dealer' ? 'selected' : '' }}>Dealer</option>
                                                <option value="Online" {{ old($isEdit ? 'booking_mode' : 'bookingmode', $entry?->b_mode ?? '') == 'Online' ? 'selected' : '' }}>Online</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="refrenceno">Online Book Ref No. <span class="required-mark" style="display: none;">*</span></label>
                                            <input type="text" name="{{ $isEdit ? 'refrence_no' : 'refrenceno' }}" id="refrenceno" class="form-control" disabled
                                                   value="{{ old($isEdit ? 'refrence_no' : 'refrenceno', $entry?->online_bk_ref_no ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="bookingsource">Booking Source <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'booking_source' : 'bookingsource' }}" id="bookingsource" class="form-control form-select" required>
                                                <option value="Dealer" {{ old($isEdit ? 'booking_source' : 'bookingsource', $entry?->b_source ?? '') == 'Dealer' ? 'selected' : '' }}>Dealer Sourcing</option>
                                                <option value="DSA" {{ old($isEdit ? 'booking_source' : 'bookingsource', $entry?->b_source ?? '') == 'DSA' ? 'selected' : '' }}>DSA</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="dsadetails">Select DSA <span class="required-mark" style="display: none;">*</span></label>
                                            <select name="{{ $isEdit ? 'dsa_details' : 'dsadetails' }}" id="dsadetails" class="form-control form-select" disabled>
                                                <option value="" disabled selected>-- Select DSA --</option>
                                                @foreach ($data['dsa_details'] ?? [] as $dsa)
                                                    @php $dsaId = is_object($dsa) ? $dsa->id : ($dsa['id'] ?? ''); @endphp
                                                    <option value="{{ $dsaId }}" data-location="{{ is_object($dsa) ? ($dsa->location ?? '') : ($dsa['location'] ?? '') }}"
                                                        {{ old($isEdit ? 'dsa_details' : 'dsadetails', $entry?->dsa_id ?? '') == $dsaId ? 'selected' : '' }}>
                                                        {{ is_object($dsa) ? ($dsa->name ?? 'N/A') : ($dsa['name'] ?? 'N/A') }}
                                                        -
                                                        {{ is_object($dsa) ? ($dsa->mobile ?? 'N/A') : ($dsa['mobile'] ?? 'N/A') }}
                                                        -
                                                        {{ is_object($dsa) ? ($dsa->location ?? 'N/A') : ($dsa['location'] ?? 'N/A') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">

                                            <label for="saleconsultant">
                                                Sales Consultant
                                                <span class="required-mark">*</span>
                                            </label>

                                            @php
                                                $selectedSalesConsultant = old(
                                                    'saleconsultant',
                                                    $data['saleconsultant']
                                                        ?? $enquiry?->x8_sc_code
                                                        ?? $entry?->consultant
                                                        ?? ''
                                                );

                                                $selectedSalesConsultant = strtoupper(
                                                    trim((string) $selectedSalesConsultant)
                                                );
                                            @endphp

                                            <select name="saleconsultant"
                                                    id="saleconsultant"
                                                    class="form-control form-select"
                                                    required>

                                                <option value="">Please Select...</option>

                                                @foreach ($data['salesconsultants'] ?? [] as $consultant)

                                                    @php
                                                        $conCode = is_object($consultant)
                                                            ? ($consultant->employee_code ?? '')
                                                            : ($consultant['employee_code'] ?? '');

                                                        $displayName = is_object($consultant)
                                                            ? ($consultant->display_name ?? '')
                                                            : ($consultant['display_name'] ?? '');

                                                        $employeeCode = is_object($consultant)
                                                            ? ($consultant->employee_code ?? '')
                                                            : ($consultant['employee_code'] ?? '');

                                                        $isSelected =
                                                            strtoupper(trim((string) $selectedSalesConsultant))
                                                            ===
                                                            strtoupper(trim((string) $conCode));
                                                    @endphp

                                                    <option value="{{ $conCode }}"
                                                        {{ $isSelected ? 'selected' : '' }}>
                                                        {{ $displayName }} - {{ $employeeCode }}
                                                    </option>

                                                @endforeach

                                            </select>

                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label>Delivery Date Type <span class="required-mark">*</span></label>
                                            <div>
                                                @php $delT = old($isEdit ? 'delivery_type' : 'deliverytype', $entry?->del_type ?? 'Expected'); @endphp
                                                <label><input type="radio" name="{{ $isEdit ? 'delivery_type' : 'deliverytype' }}" value="Expected" {{ $delT == 'Expected' ? 'checked' : '' }}>
                                                    Expected</label>&nbsp;&nbsp;&nbsp;
                                                <label><input type="radio" name="{{ $isEdit ? 'delivery_type' : 'deliverytype' }}" value="Confirmed" {{ $delT == 'Confirmed' ? 'checked' : '' }}>
                                                    Confirmed</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="expecteddeldate">Delivery Date <span class="required-mark">*</span></label>
                                            @php $delDate = $entry?->del_date ?? ''; @endphp
                                            <input type="text" name="expecteddeldate" id="expecteddeldate" class="form-control"
                                                placeholder="dd-mmm-yyyy" required value="{{ old('expecteddeldate', site_date($delDate, '')) }}">
                                            <input type="hidden" name="{{ $isEdit ? 'expected_del_date_actual' : 'hiddenexpecteddeldate' }}" id="hiddenexpecteddeldate"
                                                value="{{ old($isEdit ? 'expected_del_date_actual' : 'hiddenexpecteddeldate', $delDate) }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="finmode">Finance Mode <span class="required-mark">*</span></label>
                                            <select name="{{ $isEdit ? 'fin_mode' : 'finmode' }}" id="finmode" class="form-control form-select" required>
                                                <option value="" disabled {{ empty($entry?->fin_mode) && empty($q['financier']) && empty($enquiry->fin_mode) ? 'selected' : '' }}>
                                                    -- Select Finance Mode --
                                                </option>
                                                <option value="In-house" {{ $fmode == 'In-house' ? 'selected' : '' }}>In-house</option>
                                                <option value="Customer Self" {{ $fmode == 'Customer Self' ? 'selected' : '' }}>Customer Self</option>
                                                <option value="Cash" {{ $fmode == 'Cash' ? 'selected' : '' }}>Cash</option>
                                                <option value="Yet To Decide" {{ $fmode == 'Yet To Decide' ? 'selected' : '' }}>Yet To Decide</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group" id="financierbox" style="display: none;">
                                            <label for="financier">Financier <span class="required-mark" style="display: none;">*</span></label>
                                            <select name="financier" id="financier" class="form-control form-select">
                                                <option value="">Select Financier</option>
                                                @foreach ($data['financiers'] ?? [] as $financier)
                                                    <option value="{{ $financier->id }}"
                                                        data-shortname="{{ $financier->short_name ?? '' }}"
                                                        {{ old('financier', $entry?->financier ?? ($q['financier'] ?? ($enquiry->financier ?? ''))) == $financier->id ? 'selected' : '' }}>
                                                        {{ $financier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="financiershortname">Financier Short Name</label>
                                            <input type="text" name="financiershortname" id="financiershortname"
                                                class="form-control" readonly>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group" id="loanstatusbox">

                                            <label for="loanstatus">
                                                Loan File Status
                                                <span class="required-mark" style="display: none;">*</span>
                                            </label>

                                            @php
                                                if ($isEdit) {

                                                    // First priority: old submitted value
                                                    $lStatus = old(
                                                        'loan_status',
                                                        $data['finance']?->loan_status
                                                    );

                                                    // If finance row does not exist yet, default In-house to Pending
                                                    if (
                                                        (empty($lStatus)) &&
                                                        $fmode === 'In-house'
                                                    ) {
                                                        $lStatus = 'Pending';
                                                    }

                                                } else {

                                                    $lStatus = old(
                                                        'loanstatus',
                                                        $enquiry?->loan_status ?? ''
                                                    );

                                                }

                                                $lStatus = trim((string) $lStatus);
                                            @endphp

                                            <select
                                                name="{{ $isEdit ? 'loan_status' : 'loanstatus' }}"
                                                id="loanstatus"
                                                class="form-control form-select"
                                                disabled
                                                required
                                            >

                                                <option value="" disabled
                                                    {{ $lStatus === '' ? 'selected' : '' }}>
                                                    -- Select Loan File Status --
                                                </option>

                                                <option value="Pending"
                                                    {{ strcasecmp($lStatus, 'Pending') === 0 ? 'selected' : '' }}>
                                                    Pending
                                                </option>

                                                <option value="Complete"
                                                    {{ strcasecmp($lStatus, 'Complete') === 0 ? 'selected' : '' }}>
                                                    Complete
                                                </option>

                                            </select>

                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="details">Remarks</label>
                                            <textarea name="details" id="details" class="form-control" rows="4"
                                                placeholder="Enter any additional remarks...">{{ old('details', $entry?->details ?? ($enquiry->remarks ?? '')) }}</textarea>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>{{-- /#bookingCardsContainer --}}

                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button type="submit" id="submitBtn"
                                class="btn btn-success btn-lg px-5 py-3 shadow-lg fw-bold text-uppercase">
                                <i class="ik {{ $isEdit ? 'ik-check' : 'ik-plus' }} mr-2"></i> {{ $isEdit ? 'Update Booking' : 'Add Booking' }}
                            </button>
                        </div>
                    </div>

                    <!-- Proof Preview Modal -->
                    <div class="modal fade" id="proofModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="proofModalFileName"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body text-center">
                                    <iframe id="proofModalPreview" style="width:100%; height:500px;" frameborder="0"></iframe>
                                </div>

                                <div class="modal-footer">
                                    <a id="proofModalDownload" class="btn btn-success" download>Download</a>

                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title" id="errorModalLabel">Form Errors</h2>

                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_styles')
    <style>
        /* --- REMOVE NUMBER ARROWS --- */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .required-mark { color: #dc3545; margin-left: 2px; }
        label .required-mark { display: inline !important; }
        input.numeric-only { -moz-appearance: textfield; }
        input.numeric-only::-webkit-outer-spin-button,
        input.numeric-only::-webkit-inner-spin-button {
            -webkit-appearance: none; margin: 0;
        }
        .proof-chip {
            display: inline-flex; align-items: center; background-color: #f1f3f5;
            border: 1px solid #ced4da; border-radius: 50px; padding: 6px 14px;
            margin-right: 12px; font-size: 0.95rem; max-width: 320px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); transition: all 0.2s ease; user-select: none;
        }
        .proof-chip:hover {
            background-color: var(--tblr-bg-surface-secondary); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .proof-chip i { font-size: 1.4rem; margin-right: 10px; color: var(--tblr-muted); }
        .proof-chip .file-name {
            max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: 12px;
        }
        .is-valid { border-color: #28a745 !important; box-shadow: 0 0 5px rgba(40, 167, 69, 0.5); }
        .is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
        #modalProofPdf, #modalProofImg { max-height: 100vh; object-fit: contain; }
        .select2-container--bootstrap5 .select2-selection--single .select2-selection__arrow,
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            display: none !important;
        }
        .select2-container--bootstrap5 .select2-selection--single,
        .select2-container .select2-selection--single {
            height: calc(1.5em + 0.75rem + 2px) !important; padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
            font-size: 1rem; font-weight: 400; line-height: 1.5; color: var(--tblr-body-color); background-color: var(--tblr-card-bg);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important; background-position: right 0.75rem center !important;
            background-size: 16px 12px !important; border: 1px solid #ced4da !important; border-radius: 0.375rem !important;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .select2-container--bootstrap5.select2-container--focus .select2-selection--single,
        .select2-container.select2-container--focus .select2-selection--single {
            border-color: #86b7fe !important; outline: 0; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, .25) !important;
        }
        .page-header { display: block; }

        /* =================================================================
           COLLAPSIBLE / DRAG-REORDERABLE FORM CARDS
           Styling for the new .booking-card shell wrapped around each of
           the six form sections. Kept deliberately light (border + a
           little shadow on drag) so the dense field grid inside stays the
           visual focus — "clean, minimal, max content in minimum area".
        ================================================================= */
        .booking-cards-container { display: flex; flex-direction: column; gap: 14px; margin-top: 14px; }
        .booking-card { margin: 0 !important; transition: box-shadow .15s ease; }
        .booking-card.is-dragging { opacity: .5; }
        .booking-card__header {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; cursor: default;
            border-bottom: 1px solid #eef0f2;
        }
        .booking-card__handle { cursor: grab; color: #9ca3af; font-size: 16px; }
        .booking-card__handle:active { cursor: grabbing; }
        .booking-card__title { margin: 0; font-size: 1.1rem; flex: 1; }
        .booking-card__badge {
            font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 999px;
            background: #eef2ff; color: #4338ca; white-space: nowrap;
        }
        .booking-card__badge.is-complete { background: #ecfdf3; color: #027a48; }
        .booking-card__toggle {
            border: none; background: none; color: #6b7280; padding: 4px 6px;
            transition: transform .18s ease;
        }
        .booking-card__toggle.is-collapsed { transform: rotate(180deg); }
        .booking-card__body { padding: 16px; }
        .booking-card__body.is-collapsed { display: none; }
        .sortable-ghost.booking-card { opacity: .35; }
    </style>
@endpush

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    {{-- Bootstrap 4.6.2's bundle was loaded here only to provide jQuery's $.fn.modal() plugin
         API, which conflicts with the Tabler theme's own Bootstrap 5 JS. Both .modal('show')
         call sites converted to the vanilla bootstrap.Modal API already used throughout the
         rest of this controller's views (show.blade.php etc.) - see BUG-118. --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- SortableJS powers the new drag-to-reorder form cards. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
        // Site-wide date display format (see .ai/rules/conventions.md section 13) - flatpickr's
        // token syntax matches PHP's date() tokens, so the PHP-side format string is reused as-is.
        const SITE_DATE_FORMAT = '@php echo app(\App\Services\DateFormatService::class)->phpFormat(); @endphp';
        @php
            $rawAccessories = $entry?->accessories ?? ($q['accessories'] ?? []);

            $prefillAccessories = is_array($rawAccessories)
                ? $rawAccessories
                : ($rawAccessories
                    ? array_filter(explode(',', $rawAccessories))
                    : []);
        @endphp
        let uploadedFile = null;
        let salesUsers = @json($data['allusers'] ?? []);
        console.log('✅ SLS Users Loaded:', salesUsers.length);

        // Inject data passed from the controller
        const prefillData = {
            hasQuotation: @json($quotation ? true : false),
            segment: @json(old($isEdit ? 'segment_id' : 'segment', $entry?->segment_code ?? ($enquiry->segment_code ?? ''))),
            model: @json(old('model', $entry?->model_code ?? ($enquiry->model_code ?? ''))),
            variant: @json(old('variant', $entry?->variant_code ?? ($enquiry->variant_code ?? ''))),
            color: @json(old('color', $entry?->color_code ?? ($enquiry->color_code ?? ''))),
            care_of_type: @json(
                old(
                    $isEdit ? 'care_of' : 'careof',
                    $entry?->care_of_type
                        ?? $enquiry?->care_of_type
                        ?? $data['care_of_type']
                        ?? ''
                )
            ),
            care_of: @json(
                old(
                    $isEdit ? 'care_of_name' : 'careofname',
                    $entry?->care_of
                        ?? $enquiry?->care_of
                        ?? $data['care_of']
                        ?? ''
                )
            ),
            accessories: @json($prefillAccessories),
            branch: @json(old('branch', $entry?->branch_code ?? ($enquiry->dealer_branch ?? ''))),
            location: @json(old($isEdit ? 'location_id' : 'location', $entry?->location_code ?? ($enquiry->dealer_location ?? ''))),
            pincode: @json(old('pincode', $entry?->pincode ?? ($enquiry->zipcode ?? ''))),
            vpo: @json(old('vpo', $entry?->vpo ?? ($enquiry->vpo ?? ''))),
            tehsil: @json(old('customer_tehsil', $entry?->tehsil ?? ($enquiry->tehsil ?? ''))),
            district: @json(old('customer_district', $entry?->district ?? ($enquiry->district ?? ''))),
            city: @json(old('city', $entry?->city ?? ($enquiry->city ?? ''))),
            territory: @json(old('territory', $entry?->territory ?? ($enquiry->territory ?? ''))),
            col_by: @json(old('user', $entry?->col_by ?? '')),
            chassis: @json(old('chassis', $entry?->chassis_no ?? ''))
        };

        function handleProof(input) {
            const previewDiv = document.getElementById('proofPreview');
            previewDiv.innerHTML = '';
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'File Too Large',
                        text: 'Maximum allowed file size is 2MB.'
                    });
                    input.value = '';
                    return;
                }
                const fileURL = URL.createObjectURL(file);
                previewDiv.innerHTML = `
                    <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                        style="cursor:pointer" onclick="openProofModal('${fileURL}','${file.name.replace(/'/g,"\\'")}')">
                        <i class="la la-paperclip"></i>
                        <span class="fw-medium small">${file.name}</span>
                    </span>
                `;
            }
        }

        function openProofModal(url, name) {
            document.getElementById('proofModalFileName').innerText = name;
            document.getElementById('proofModalDownload').href = url;
            document.getElementById('proofModalPreview').src = url;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('proofModal')).show();
        }
        document.getElementById('proofInput')?.addEventListener('change', function() { handleProof(this); });

        $('#proofModal').on('show.bs.modal', function() { $(this).appendTo('body'); });

        // =====================================================================
        // CARD LAYOUT — collapse/expand, drag-to-reorder, per-card progress
        // badge, and localStorage persistence for the six .booking-card
        // sections above. Purely presentational: it never disables a field
        // or touches its name/value, so it cannot change what gets submitted.
        // =====================================================================
        const CARD_LAYOUT_KEY = 'xlr8BookingFormLayout';

        function loadCardLayout() {
            try {
                const raw = localStorage.getItem(CARD_LAYOUT_KEY);
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        function saveCardLayout(layout) {
            try { localStorage.setItem(CARD_LAYOUT_KEY, JSON.stringify(layout)); } catch (e) { /* storage unavailable */ }
        }

        // Recomputes the "X/Y filled" badge for one card, based on its own
        // required, non-disabled fields (radio/checkbox groups count as a
        // single requirement; a group is "filled" once any option is set).
        function updateCardProgress(card) {
            const badge = card.querySelector('.booking-card__badge');
            if (!badge) return;

            const fields = card.querySelectorAll('[required]:not(:disabled)');
            const seenRadioGroups = new Set();
            let total = 0, filled = 0;

            fields.forEach(field => {
                if (field.type === 'radio') {
                    if (seenRadioGroups.has(field.name)) return;
                    seenRadioGroups.add(field.name);
                    total++;
                    if (card.querySelector(`input[name="${field.name}"]:checked`)) filled++;
                    return;
                }
                total++;
                if (field.type === 'checkbox') {
                    if (field.checked) filled++;
                } else if (String(field.value || '').trim() !== '') {
                    filled++;
                }
            });

            if (total === 0) {
                badge.textContent = '';
                badge.classList.remove('is-complete');
                return;
            }

            badge.textContent = `${filled}/${total}`;
            badge.classList.toggle('is-complete', filled === total);
        }

        function updateAllCardProgress() {
            document.querySelectorAll('.booking-card').forEach(updateCardProgress);
        }

        function setCardCollapsed(card, collapsed) {
            card.querySelector('.booking-card__body').classList.toggle('is-collapsed', collapsed);
            card.querySelector('.booking-card__toggle').classList.toggle('is-collapsed', collapsed);
        }

        function initCardLayout() {
            const container = document.getElementById('bookingCardsContainer');
            if (!container) return;

            const saved = loadCardLayout();

            // Restore saved card order, if any (ids not present in the saved
            // order — e.g. after a future template change — are appended so
            // nothing becomes unreachable).
            if (saved?.order?.length) {
                const cards = Array.from(container.querySelectorAll('.booking-card'));
                const byId = Object.fromEntries(cards.map(c => [c.dataset.cardId, c]));
                saved.order.forEach(id => { if (byId[id]) container.appendChild(byId[id]); });
            }

            // Restore collapsed state (defaults to expanded so nothing is
            // hidden the first time a user opens the form).
            container.querySelectorAll('.booking-card').forEach(card => {
                const id = card.dataset.cardId;
                setCardCollapsed(card, !!saved?.collapsed?.[id]);
            });

            function persist() {
                const order = Array.from(container.querySelectorAll('.booking-card')).map(c => c.dataset.cardId);
                const collapsed = {};
                container.querySelectorAll('.booking-card').forEach(card => {
                    collapsed[card.dataset.cardId] = card.querySelector('.booking-card__body').classList.contains('is-collapsed');
                });
                saveCardLayout({ order, collapsed });
            }

            // Collapse/expand toggle.
            container.querySelectorAll('.booking-card__toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const card = btn.closest('.booking-card');
                    const body = card.querySelector('.booking-card__body');
                    setCardCollapsed(card, !body.classList.contains('is-collapsed'));
                    persist();
                });
            });

            // Drag-to-reorder via the handle only, so dragging never fights
            // with clicking a field inside the card body.
            new Sortable(container, {
                handle: '.booking-card__handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: persist,
            });

            // Keep progress badges live as the user fills the form.
            container.addEventListener('input', e => updateCardProgress(e.target.closest('.booking-card')));
            container.addEventListener('change', e => updateCardProgress(e.target.closest('.booking-card')));
            updateAllCardProgress();
        }

        // Expands the card containing a given field, used by the
        // validator's invalidHandler below so a hidden error is never
        // silently missed just because its card was collapsed.
        function expandCardContaining(element) {
            const card = element.closest?.('.booking-card');
            if (card) setCardCollapsed(card, false);
        }

        (function() {
            'use strict';

            function initBookingForm() {
                initSelect2();
                initFlatpickr();
                initMasks();
                initValidation();
                setInitialState();
                bindEventListeners();
                initUppercaseInputs();
                initNumericOnlyFields();
                initCardLayout();

                $('#customercat').on('change', function() {
                    const isFirm = this.value === 'Firm';
                    $('#ownedByOption').toggle(isFirm);
                    if (!isFirm && $('#careof').val() === '5') {
                        $('#careof').val('').trigger('change');
                    }
                    $('#careofnamelabel').html(isFirm ? 'Owner Name <span class="required-mark">*</span>' : 'Care Of Name <span class="required-mark">*</span>');
                }).trigger('change');

                if (prefillData.branch) {
                    $('#branch').trigger('change');
                }

                if (prefillData.segment) {
                    $('#segment').trigger('change');
                }

                if ($('#financier').val()) {
                    $('#financier').trigger('change');
                }
            }

            function initSelect2() { $('#accessories, #chassis').select2(); }

            function initFlatpickr() {
                flatpickr('#customerdob', {
                    dateFormat: SITE_DATE_FORMAT, maxDate: 'today', allowInput: false,
                    onChange: function(selectedDates, dateStr, instance) {
                        const dob = selectedDates[0];
                        if (dob) {
                            const age = calculateAge(dob);
                            $('#customerage').val(age);
                            $('#hiddencustomerdob').val(instance.formatDate(dob, 'Y-m-d'));

                            if (age < 18) {
                                Swal.fire({
                                    icon: 'warning', title: 'Age Restriction',
                                    text: `Customer age is ${age} years, which is below 18. Please select a valid date.`
                                });
                                instance.clear();
                                $('#customerage').val('');
                                $('#hiddencustomerdob').val('');
                            }
                        }
                    }
                });

                let initDob = $('#customerdob').val();
                if (initDob) {
                    let parts = initDob.split('-');
                    if (parts.length === 3) {
                        let months = { Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5, Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11 };
                        let dobDate = new Date(parts[2], months[parts[1]] || 0, parts[0]);
                        if (!isNaN(dobDate)) $('#customerage').val(calculateAge(dobDate));
                    }
                }

                const bookingPicker = flatpickr('#bookingdate', {
                    dateFormat: SITE_DATE_FORMAT, maxDate: 'today', allowInput: false,
                    onChange: function(selectedDates, dateStr, instance) {
                        const bookingDate = selectedDates[0];
                        $('#hiddenbookingdate').val(instance.formatDate(bookingDate, 'Y-m-d'));
                        if (bookingDate && window.deliveryPicker) {
                            window.deliveryPicker.set('minDate', bookingDate);
                            if (window.deliveryPicker.selectedDates[0] && window.deliveryPicker.selectedDates[0] < bookingDate) {
                                window.deliveryPicker.clear();
                                $('#hiddenexpecteddeldate').val('');
                                alert('Delivery date cannot be earlier than booking date.');
                            }
                        }
                    }
                });

                window.deliveryPicker = flatpickr('#expecteddeldate', {
                    dateFormat: SITE_DATE_FORMAT, allowInput: false, minDate: 'today',
                    onChange: function(selectedDates, dateStr, instance) {
                        $('#hiddenexpecteddeldate').val(instance.formatDate(selectedDates[0], 'Y-m-d'));
                    }
                });

                flatpickr('#receiptdate', {
                    dateFormat: SITE_DATE_FORMAT, maxDate: 'today', allowInput: false,
                    onChange: function(selectedDates, dateStr, instance) {
                        $('#hiddenreceiptdate').val(instance.formatDate(selectedDates[0], 'Y-m-d'));
                    }
                });
            }

            function initNumericOnlyFields() {
                const numericFields = ['#manufacturingyear', '#odometerreading', '#expectedprice', '#offeredprice', '#exchangebonus'];
                numericFields.forEach(selector => {
                    const $field = $(selector);
                    $field.addClass('numeric-only');
                    $field.on('input', function() {
                        let val = this.value;
                        if (this.id === 'manufacturingyear' || this.id === 'odometerreading') {
                            val = val.replace(/[^0-9]/g, '');
                        } else {
                            val = val.replace(/[^0-9.]/g, '');
                            const parts = val.split('.');
                            if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
                        }
                        this.value = val;
                    });
                    $field.on('keypress', function(e) {
                        if (this.id === 'manufacturingyear' || this.id === 'odometerreading') {
                            if (!/[0-9]/.test(e.key)) e.preventDefault();
                        } else {
                            if (!/[0-9.]/.test(e.key)) e.preventDefault();
                        }
                    });
                });
            }

            function calculateAge(dob) {
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                return age;
            }

            function initMasks() {
                $('#panno').mask('AAAAA0000A', { placeholder: 'ABCDE1234F' });
                $('#adharno').mask('0000-0000-0000', { placeholder: '1234-5678-9012' });
                $('#refrenceno').mask('AAAAAAAAAA', { placeholder: 'Booking Reference No.' });
                $('#gstn').attr('maxlength', 15).on('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                });

                $('#receiptvoucherinput').mask('00000', { placeholder: '12345', reverse: true });
                $('#receiptvoucherinput').on('keydown keypress', function(e) {
                    if (e.which === 32) e.preventDefault();
                });
                $('#receiptvoucherinput').on('paste', function(e) {
                    setTimeout(() => this.value = this.value.replace(/ /g, ''), 0);
                });
            }

            function initValidation() {
                $.validator.addMethod('panFormat', function(value, element) {
                    return this.optional(element) || /[A-Z]{5}[0-9]{4}[A-Z]{1}/.test(value);
                }, 'Please enter a valid PAN number e.g., ABCDE1234F');

                $.validator.addMethod('udaiFormat', function(value, element) {
                    return this.optional(element) || /\d{4}-\d{4}-\d{4}/.test(value);
                }, 'Please enter a valid Aadhar No. e.g., 1234-5678-9012');

                $.validator.addMethod('receiptFormat', function(value, element) {
                    return this.optional(element) || /\d{1,5}/.test(value);
                }, 'Please enter a valid Receipt number (1-5 digits)');

                $.validator.addMethod('gstnFormat', function(value, element) {
                    return this.optional(element) || /^\d{2}[A-Z]{5}\d{4}[A-Z]{1}\d{1}Z[A-Z0-9]{1}$/.test(value);
                }, 'Please enter a valid GSTIN e.g., 08CDBPB0580N2ZK');

                const bookingForm = $('#bookingForm');
                bookingForm.validate({
                    ignore: ':disabled',
                    rules: {
                        ['{{ $isEdit ? "customer_type" : "customertype" }}']: { required: true },
                        customercat: { required: true },
                        bookingdate: { required: true },
                        ['{{ $isEdit ? "col_type" : "coltype" }}']: { required: true },
                        user: { required: function() { return $('#coltype').val() === '2' || $('#coltype').val() === '3'; } },
                        gstn: { gstnFormat: true, required: function() { return !$('#notrequiredgst').is(':checked'); } },
                        ['{{ $isEdit ? "booking_amount" : "bookingamount" }}']: { required: true, number: true },
                        ['{{ $isEdit ? "receipt_no" : "receiptno" }}']: { required: function() { return $('#coltype').val() === '1'; }, receiptFormat: true },
                        receiptdate: { required: function() { return $('#coltype').val() === '1' || $('#coltype').val() === '4'; } },
                        mode: { required: function() { return $('#coltype').val() === '1' || $('#coltype').val() === '4'; } },
                        name: { required: true },
                        ['{{ $isEdit ? "care_of" : "careof" }}']: { required: true },
                        ['{{ $isEdit ? "care_of_name" : "careofname" }}']: { required: true },
                        mobile: { required: true, digits: true, minlength: 10, maxlength: 10 },
                        ['{{ $isEdit ? "alt_mobile" : "altmobile" }}']: { digits: true, minlength: 10, maxlength: 10 },
                        gender: { required: true },
                        occupation: { required: true },
                        ['{{ $isEdit ? "pan_no" : "panno" }}']: { panFormat: true },
                        ['{{ $isEdit ? "adhar_no" : "adharno" }}']: { udaiFormat: true },
                        customerdob: { required: true },
                        branch: { required: true },
                        ['{{ $isEdit ? "location_id" : "location" }}']: { required: function() { return parseInt($('#location').val()) !== 0; } },
                        ['{{ $isEdit ? "location_other" : "locationother" }}']: { required: function() { return parseInt($('#location').val()) === 0; } },
                        ['{{ $isEdit ? "ref_customer_name" : "refcustomername" }}']: { required: function() { return $('#referredby').is(':checked'); } },
                        ['{{ $isEdit ? "ref_mobile_no" : "refmobileno" }}']: { required: function() { return $('#referredby').is(':checked'); }, digits: true, minlength: 10, maxlength: 10 },
                        ['{{ $isEdit ? "ref_existing_model" : "refexistingmodel" }}']: { required: function() { return $('#referredby').is(':checked'); } },
                        ['{{ $isEdit ? "ref_variant" : "refvariant" }}']: { required: function() { return $('#referredby').is(':checked'); } },
                        ['{{ $isEdit ? "ref_chassis_reg_no" : "refchassisregno" }}']: { required: function() { return $('#referredby').is(':checked'); } },
                        ['{{ $isEdit ? "buyer_type" : "buyertype" }}']: { required: true },
                        enummaster1: { required: function() { return ['Additional Buy', 'Exchange Buy', 'Scrappage'].includes($('#buyertype').val()); } },
                        ['{{ $isEdit ? "vehicle_details" : "vehicledetails" }}']: { required: function() { return ['Additional Buy', 'Exchange Buy', 'Scrappage'].includes($('#buyertype').val()); } },
                        enummaster2: { required: function() { return false; } },
                        ['{{ $isEdit ? "vehicle_details2" : "vehicledetails2" }}']: { required: function() { return false; } },
                        ['{{ $isEdit ? "registration_no" : "registrationno" }}']: { required: function() { return ['Exchange Buy', 'Scrappage'].includes($('#buyertype').val()); } },
                        ['{{ $isEdit ? "manufacturing_year" : "manufacturingyear" }}']: { required: function() { return ['Exchange Buy', 'Scrappage'].includes($('#buyertype').val()); } },
                        ['{{ $isEdit ? "odometer_reading" : "odometerreading" }}']: { required: function() { return $('#buyertype').val() === 'Exchange Buy'; } },
                        ['{{ $isEdit ? "expected_price" : "expectedprice" }}']: { required: function() { return $('#buyertype').val() === 'Exchange Buy'; } },
                        ['{{ $isEdit ? "offered_price" : "offeredprice" }}']: { required: function() { return $('#buyertype').val() === 'Exchange Buy'; } },
                        ['{{ $isEdit ? "exchange_bonus" : "exchangebonus" }}']: { required: function() { return $('#buyertype').val() === 'Exchange Buy'; } },
                        ['{{ $isEdit ? "segment_id" : "segment" }}']: { required: true },
                        model: { required: true },
                        variant: { required: true },
                        color: { required: true },
                        ['{{ $isEdit ? "booking_mode" : "bookingmode" }}']: { required: true },
                        ['{{ $isEdit ? "refrence_no" : "refrenceno" }}']: { required: function() { return $('#bookingmode').val() === 'Online'; } },
                        saleconsultant: { required: true },
                        ['{{ $isEdit ? "booking_source" : "bookingsource" }}']: { required: true },
                        ['{{ $isEdit ? "dsa_details" : "dsadetails" }}']: { required: function() { return $('#bookingsource').val() === 'DSA'; } },
                        ['{{ $isEdit ? "delivery_type" : "deliverytype" }}']: { required: true },
                        expecteddeldate: { required: true },
                        ['{{ $isEdit ? "fin_mode" : "finmode" }}']: { required: true },
                        financier: { required: function() { return $('#finmode').val() === 'In-house'; } },
                        ['{{ $isEdit ? "loan_status" : "loanstatus" }}']: { required: function() { return $('#finmode').val() === 'In-house'; } }
                    },
                    errorElement: 'span',
                    errorPlacement: function(error, element) {
                        error.addClass('text-danger');
                        error.insertAfter(element);
                    },
                    highlight: function(element) { $(element).removeClass('is-valid').addClass('is-invalid'); },
                    unhighlight: function(element) { $(element).removeClass('is-invalid').addClass('is-valid'); },
                    onfocusout: function(element) { this.element(element); },
                    // NEW: if any invalid field lives inside a collapsed card, expand
                    // that card so the highlighted field/message is actually visible
                    // (previously a collapsed section could hide a real error).
                    invalidHandler: function(event, validator) {
                        (validator.errorList || []).forEach(function(err) {
                            expandCardContaining(err.element);
                        });
                    },
                    submitHandler: function(form) {
                        if ($('#bookingForm').valid()) {
                            form.submit();
                        } else {
                            showErrorModal();
                            return false;
                        }
                    }
                });
            }

            function setInitialState() {
                toggleCustomerFields($('#customertype').val());
                toggleCollectionFields($('#coltype').val());
                toggleFinanceFields($('#finmode').val());
                togglePurchaseFields($('#buyertype').val(), true);
                $('#customernamelabel').html('Customer Name <span class="required-mark">*</span>');

                if (prefillData.col_by && [2, 3].includes(parseInt($('#coltype').val()))) {
                    var optionExists = $('#user').find("option[value='" + prefillData.col_by + "']").length > 0;
                    if (optionExists) {
                        $('#user').val(prefillData.col_by).trigger('change');
                    } else {
                        // Fallback: preserve saved value so it doesn't get silently wiped on save
                        $('#user').append(new Option(
                            'Previous Collector (' + prefillData.col_by + ')',
                            prefillData.col_by,
                            true,
                            true
                        ));
                        console.warn('Collector ' + prefillData.col_by + ' not in current SLS list — preserved as fallback option.');
                    }
                }
            }

            $('#finmode').on('change', function() { toggleFinanceFields(this.value); });
            $('#financier').on('change', function() {
                const shortName = $(this).find(':selected').data('shortname') || '';
                $('#financiershortname').val(shortName);
            });

            function bindEventListeners() {
                $('#customertype').on('change', function() { toggleCustomerFields(this.value); });
                $('#notrequiredgst').on('change', function() {
                    const isChecked = $(this).is(':checked');
                    $('#gstn').prop('disabled', isChecked).prop('required', !isChecked).val('').removeClass('is-invalid is-valid');
                    $('label.error[for="gstn"]').remove();
                    $('#bookingForm').validate().resetForm();
                    toggleRequiredMark($('#gstn'), !isChecked);
                });

                $('#customercat').on('change', function() {
                    const isFirm = this.value === 'Firm';
                    if (isFirm) {
                        $('#careof').html(`<option value="">Please Select...</option><option value="5" selected>Owned By</option>`);
                        $('#careofname').prop('disabled', false).prop('required', true);
                        toggleRequiredMark($('#careofname'), true);
                    } else {
                        const careOfType = String(prefillData.care_of_type || '');

                        $('#careof').html(`
                            <option value="">Please Select...</option>
                            <option value="1" ${careOfType === '1' ? 'selected' : ''}>Son of</option>
                            <option value="2" ${careOfType === '2' ? 'selected' : ''}>Daughter of</option>
                            <option value="3" ${careOfType === '3' ? 'selected' : ''}>Married to</option>
                            <option value="4" ${careOfType === '4' ? 'selected' : ''}>Guardian Name</option>
                        `);

                        $('#careofname')
                            .prop('disabled', false)
                            .prop('required', true);

                        toggleRequiredMark($('#careofname'), true);
                    }
                    $('#customernamelabel').text(isFirm ? 'Firm Name' : 'Customer Name');
                });

                $('#coltype').on('change', function() { toggleCollectionFields(this.value); });

                $('#referredby').on('change', function() {
                    const isChecked = this.checked;
                    const referralFields = [$('#refcustomername'), $('#refmobileno'), $('#refexistingmodel'), $('#refvariant'), $('#refchassisregno')];
                    referralFields.forEach(field => {
                        field.prop('disabled', !isChecked).prop('required', isChecked);
                        if (!isChecked) {
                            field.val('').removeClass('is-invalid');
                            field.next('.invalid-feedback').remove();
                            field.next('label.error').remove();
                        }
                    });
                    toggleRequiredMark($('#refcustomername, #refmobileno, #refexistingmodel, #refvariant, #refchassisregno'), isChecked);
                    if (!isChecked && $('#bookingForm').data('validator')) $('#bookingForm').validate().resetForm();
                });

                $('#bookingsource').on('change', function() {
                    const isDSA = this.value === 'DSA';
                    $('#dsadetails').prop('disabled', !isDSA).prop('required', isDSA);
                    if (isDSA) {
                        $('#dsadetails').next('.select2-container').removeClass('select2-disabled-custom');
                    } else {
                        $('#dsadetails').next('.select2-container').addClass('select2-disabled-custom');
                        $('#dsadetails').val('').trigger('change');
                    }
                    toggleRequiredMark($('#dsadetails'), isDSA);
                    $('#bookingForm').validate().settings.rules["{{ $isEdit ? 'dsa_details' : 'dsadetails' }}"].required = isDSA;
                });

                $('#finmode').on('change', function() { toggleFinanceFields(this.value); });
                $('#bookingmode').on('change', function() {
                    const isOnline = this.value === 'Online';
                    $('#refrenceno').prop('disabled', !isOnline).prop('required', isOnline);
                    toggleRequiredMark($('#refrenceno'), isOnline);
                });

                $('#buyertype').on('change', function(e) { togglePurchaseFields(this.value, !e.originalEvent); });

                $('#segment').on('change', function() {
                    const segment = this.value;
                    $.ajax({
                        url: '{{ backpack_url('sales/booking/models') }}/' + segment, method: 'GET',
                        success: function(data) {
                            populateSelect($('#model'), data, 'name', 'code');
                            $('#model').prop('disabled', false);
                            resetFields($('#variant'), $('#color'), $('#chassis'));
                            if (prefillData.model) {
                                $('#model').val(prefillData.model).trigger('change');
                                prefillData.model = null;
                            }
                        },
                        error: handleAjaxError('Error fetching models')
                    });
                });

                $('#model').on('change', function() {
                    const modelId = this.value;
                    $.ajax({
                        url: '{{ backpack_url('sales/booking/variants') }}/' + encodeURIComponent(modelId), method: 'GET',
                        success: function(data) {
                            populateSelect($('#variant'), data, 'name', 'code', null, function(option, item) {
                                option.dataset.seating = item.seating_capacity || '0';
                            });
                            $('#variant').prop('disabled', false);
                            resetFields($('#color'), $('#chassis'));
                            resetAccessories();
                            if (prefillData.variant) {
                                $('#variant').val(prefillData.variant).trigger('change');
                                prefillData.variant = null;
                            }
                        },
                        error: handleAjaxError('Error fetching variants')
                    })
                });

                $('#variant').on('change', function() {
                    const variantId = this.value;
                    const seating = $(this).find(':selected').data('seating') || '0';
                    $('#seating').val(seating);

                    const modelId = $('#model').val();
                    const segmentName = $('#segment').find(':selected').text();
                    if (!variantId) return;

                    $.ajax({
                        url: '{{ backpack_url('sales/booking/colors') }}/' + variantId, method: 'GET',
                        success: function(data) {
                            let colorsArray = (typeof data === 'object' && !Array.isArray(data)) ? Object.values(data) : (Array.isArray(data) ? data : []);
                            if (colorsArray.length > 0) {
                                populateSelect($('#color'), colorsArray, 'name', 'code', null, function(option, item) {
                                    option.dataset.variantCode = item.variant_code || '';
                                });
                                $('#color').prop('disabled', false);
                                resetFields($('#chassis'));
                                if (prefillData.color) {
                                    $('#color').val(prefillData.color).trigger('change');
                                    prefillData.color = null;
                                }
                            } else {
                                resetFields($('#color'), $('#chassis'));
                                $('#color').prop('disabled', true);
                            }
                        },
                        error: handleAjaxError('Error fetching colors')
                    });

                    if (segmentName && modelId && variantId) {
                        $.ajax({
                            url: '{{ backpack_url('sales/booking/accessories') }}/'
                            + encodeURIComponent($('#segment').val())
                            + '/'
                            + encodeURIComponent($('#model').val())
                            + '/'
                            + encodeURIComponent(this.value), method: 'GET',
                            success: function(data) {
                            const $accessories = $('#accessories');

                            const preserveSelectedAccessories = !!prefillData.variant;
                            const selectedAccessories = preserveSelectedAccessories
                                ? ($accessories.val() || [])
                                : [];

                            $accessories.select2('destroy')
                                .prop('disabled', false)
                                .empty();

                            $.each(data, function(i, item) {
                                $accessories.append(
                                    $('<option>', {
                                        value: item.part_no,
                                        text: item.display_name || item.item
                                    }).attr('data-price', item.ndp || 0)
                                );
                            });

                            if (preserveSelectedAccessories) {
                                const availableValues = selectedAccessories.filter(function(value) {
                                    return $accessories.find("option[value='" + value + "']").length > 0;
                                });

                                $accessories.val(availableValues);
                            }

                            $accessories.select2({
                                placeholder: 'Please Select...',
                                allowClear: true
                            });

                            $accessories.trigger('change');

                            updateAccessoriesAmount();
                        },error: handleAjaxError('Error fetching accessories')
                        });
                    }
                });

                $('#color').on('change', function() {
                    const selectedColor = $(this).find(':selected');
                    $('#vhid').val(selectedColor.data('vid'));
                    resetFields($('#chassis'));
                    $('#chassis').prop('disabled', true);

                    if (prefillData.chassis) {
                        if ($('#chassis').find("option[value='" + prefillData.chassis + "']").length === 0) {
                            $('#chassis').append(new Option(prefillData.chassis, prefillData.chassis, true, true));
                        } else {
                            $('#chassis').val(prefillData.chassis);
                        }
                        $('#chassis').prop('disabled', false);
                        prefillData.chassis = null;
                    }
                });

                $('#accessories').on('select2:select select2:unselect', function() {
                    updateAccessoriesAmount();
                });

                $('#expectedprice, #offeredprice, #exchangebonus').on('input', calculatePriceGap);

                $('#receiptvoucherinput').on('change.duplicate', function() {
                    const input = this;
                    const fieldName = '{{ $isEdit ? "receipt_no" : "receiptvoucherno" }}';
                    const type = $('#coltype').val() === '1' ? 'type1' : 'type4';
                    attachDuplicateCheck($(input), fieldName, type);
                });

                $('#branch').on('change', function() {
                    $.ajax({
                        url: '{{ backpack_url('sales/booking/locations-by-branch') }}/' + this.value + '?type=sales', method: 'GET',
                        success: function(data) {
                            let html = '<option value="" disabled selected>-- Select Location --</option>';
                            if (Array.isArray(data) && data.length > 0) {
                                data.forEach(function(loc) { html += `<option value="${loc.code}">${loc.name} (${loc.code})</option>`; });
                            }
                            html += '<option value="0">OTHER</option>';
                            $('#location').html(html).prop('disabled', false);

                            if (prefillData.location) {
                                $('#location').val(prefillData.location).trigger('change');
                                prefillData.location = null;
                            }
                        },
                        error: function(xhr) {
                            $('#location').html(`<option value="" disabled selected>-- Select Location --</option><option value="0">OTHER</option>`).prop('disabled', false);
                        }
                    });
                });

                $('#location').on('change', function() {
                    const isOther = parseInt(this.value) === 0;
                    $('#locationother').prop('disabled', !isOther).prop('required', isOther);
                    if (!isOther) $('#locationother').val('');
                    toggleRequiredMark($('#locationother'), isOther);
                });
            }

            function initUppercaseInputs() {
                $('input[type="text"]').not('.no-uppercase').not('#remarks').on('input', function() {
                    const start = this.selectionStart, end = this.selectionEnd;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(start, end);
                });
            }

            function toggleCustomerFields(type) {
                const isDummy = type === 'Dummy';
                const $coltype = $('#coltype');
                $coltype.prop('disabled', isDummy);
                if (isDummy) $coltype.val('').trigger('change');

                $('#proofInput').prop('disabled', isDummy || {{ $isEdit ? 'true' : 'false' }});
                if (isDummy) { $('#proofInput').val(''); $('#proofPreview').empty(); }

                const financeFields = ['#finmode', '#financier', '#financiershortname', '#loanstatus'];
                financeFields.forEach(selector => {
                    const $el = $(selector);
                    $el.prop('disabled', isDummy);
                    if (isDummy) {
                        if (selector === '#finmode') $el.val('');
                        if (selector === '#financier') $el.val('').trigger('change');
                        if (selector === '#loanstatus') $el.val('');
                    }
                });

                $('#bookingamount, #receiptvoucherinput, #receiptdate').prop('disabled', isDummy);
                toggleRequiredMark(
                    ['#coltype', '#proofInput', '#finmode', '#financier', '#loanstatus'],
                    !isDummy && !{{ $isEdit ? 'true' : 'false' }}
                );
                $('#bookingForm').validate().settings.rules["{{ $isEdit ? 'col_type' : 'coltype' }}"].required = !isDummy;
                $('#bookingForm').validate().settings.rules["{{ $isEdit ? 'fin_mode' : 'finmode' }}"].required = !isDummy;
            }

            function toggleCollectionFields(type) {
                const isFieldSales = type == '2', isFieldDSA = type == '3', isField = isFieldSales || isFieldDSA, isUsedCar = type == '4', isReceipt = type == '1';
                $('#user').prop('disabled', !isField).prop('required', isField);
                toggleRequiredMark($('#user'), isField);
                const userSelect = $('#user');
                userSelect.html('<option value="">Please Select...</option>');

                if (isFieldSales) {
                    if (salesUsers && salesUsers.length > 0) {
                        salesUsers.forEach(function(u) {
                            userSelect.append(`<option value="${u.person_code || u.id || ''}">${u.display_name || u.username || 'N/A'} - ${u.employee_code || u.username || ''}</option>`);
                        });
                    } else {
                        userSelect.append('<option value="">No Sales Users Available</option>');
                    }
                    userSelect.prop('disabled', false).prop('required', true);
                } else if (isFieldDSA) {
                    @foreach ($data['dsa_details'] ?? [] as $dsa)
                        @php $dsaId = is_object($dsa) ? $dsa->id : ($dsa['id'] ?? ''); @endphp
                        userSelect.append($('<option>', { value: @json($dsaId), text: @json((is_object($dsa) ? $dsa->name : ($dsa['name'] ?? 'N/A')) . ' - ' . (is_object($dsa) ? $dsa->mobile : ($dsa['mobile'] ?? 'N/A')) . ' - ' . (is_object($dsa) ? $dsa->location : ($dsa['location'] ?? 'N/A')) ) }).attr('data-location', @json(is_object($dsa) ? $dsa->location : ($dsa['location'] ?? ''))));
                    @endforeach
                }

                if (isUsedCar) {
                    $('#bookingamount').siblings('label').html('Received Amount<span class="required-mark">*</span>');
                    $('#receiptdate').siblings('label').html('Voucher Date<span class="required-mark">*</span>');
                } else {
                    $('#bookingamount').siblings('label').html('Booking Amount<span class="required-mark">*</span>');
                    $('#receiptdate').siblings('label').html('Receipt Date<span class="required-mark">*</span>');
                }

                const input = $('#receiptvoucherinput'), label = $('#receiptvoucherlabel'), warning = $('#receiptvoucherwarning'), group = $('#receiptvouchergroup');
                let inputName, inputPlaceholder, inputMask, labelText;

                if (isReceipt) {
                    inputName = '{{ $isEdit ? "receipt_no" : "receiptno" }}'; inputPlaceholder = '12345'; inputMask = '00000'; labelText = 'Receipt No.';
                    input.unmask().mask(inputMask, { placeholder: inputPlaceholder, reverse: true });
                    attachDuplicateCheck(input, inputName, 'type1');
                    input.attr('name', inputName).attr('placeholder', inputPlaceholder).prop('required', true);
                    label.html(labelText + '<span class="required-mark">*</span>'); group.show();
                    if (!"{{ $isEdit }}") input.val('');
                    warning.hide(); input.removeClass('is-invalid');
                } else if (isUsedCar) {
                    inputName = '{{ $isEdit ? "voucher_no" : "voucherno" }}'; inputPlaceholder = 'Enter Voucher No.'; labelText = 'Voucher No.';
                    input.unmask();
                    attachDuplicateCheck(input, inputName, 'type4');
                    input.attr('name', inputName).attr('placeholder', inputPlaceholder).prop('required', true);
                    label.html(labelText + '<span class="required-mark">*</span>'); group.show();
                    if (!"{{ $isEdit }}") input.val('');
                    warning.hide(); input.removeClass('is-invalid');
                } else {
                    group.hide(); input.val('').prop('required', false);
                }

                const receiptDatePicker = $('#receiptdate').data('flatpickr');
                if (isReceipt || isUsedCar) receiptDatePicker?.enable(); else receiptDatePicker?.disable();

                if (isFieldDSA) {
                    $('#bookingsource').val('DSA').prop('disabled', true).trigger('change');
                    if ($('#user').val()) $('#dsadetails').val($('#user').val()).prop('disabled', true).trigger('change');
                    $('#user').off('change.syncDSA').on('change.syncDSA', function() { $('#dsadetails').val(this.value).trigger('change').prop('disabled', true); });
                } else {
                    $('#bookingsource').prop('disabled', false).trigger('change');
                    $('#user').off('change.syncDSA');
                }
            }

            function toggleFinanceFields(mode) {
                const isInHouse = mode === 'In-house';
                $('#financierbox').toggle(isInHouse);
                $('#financier, #financiershortname, #loanstatus').prop('disabled', !isInHouse).prop('required', isInHouse);
                toggleRequiredMark('#financier, #loanstatus', isInHouse);
                if (!isInHouse) {
                    $('#financier').val('').trigger('change');
                    $('#loanstatus').val('Pending');
                    $('#financiershortname').val('');
                }
            }

            function togglePurchaseFields(type, isInitial = false) {
                const fields = {
                    base: [$('#enummaster1'), $('#vehicledetails')],
                    extraMake: [$('#enummaster2'), $('#vehicledetails2')],
                    exchange: [$('#registrationno'), $('#manufacturingyear'), $('#odometerreading'), $('#expectedprice'), $('#offeredprice'), $('#exchangebonus')]
                };

                function disableAll(arr) {
                    arr.forEach($el => {
                        $el.prop('disabled', true).prop('required', false).removeClass('is-invalid is-valid').siblings('span.text-danger').remove();
                        if (!isInitial) $el.val('').trigger('change');
                        toggleRequiredMark($el, false);
                    });
                }
                function makeRequired(arr) { arr.forEach($el => { $el.prop('disabled', false).prop('required', true); toggleRequiredMark($el, true); }); }
                function makeOptional(arr) { arr.forEach($el => { $el.prop('disabled', false).prop('required', false); toggleRequiredMark($el, false); }); }

                disableAll([...fields.base, ...fields.extraMake, ...fields.exchange]);

                if (type === 'Additional Buy') {
                    makeRequired(fields.base); makeOptional(fields.extraMake);
                } else if (type === 'Exchange Buy') {
                    makeRequired(fields.base); disableAll(fields.extraMake); makeRequired(fields.exchange);
                } else if (type === 'Scrappage') {
                    makeRequired(fields.base); makeRequired([$('#registrationno'), $('#manufacturingyear')]);
                    disableAll(fields.extraMake); disableAll([$('#odometerreading'), $('#expectedprice'), $('#offeredprice'), $('#exchangebonus')]);
                }

                if (isInitial) {
                    const expected = parseFloat($('#expectedprice').val()) || 0, offered = parseFloat($('#offeredprice').val()) || 0, bonus = parseFloat($('#exchangebonus').val()) || 0;
                    $('#difference').val(Math.round(expected - offered - bonus));
                } else {
                    $('#difference').val('');
                }
            }

            function calculatePriceGap() {
                const expected = parseFloat($('#expectedprice').val()) || 0, offered = parseFloat($('#offeredprice').val()) || 0, bonus = parseFloat($('#exchangebonus').val()) || 0;
                $('#difference').val(Math.round(expected - offered - bonus));
            }

            function populateSelect(selector, data, textKey, valueKey, extra = null, callback = null) {
                selector.html('<option value="0" selected disabled>Please Select...</option>');
                if (typeof data === 'object' && !Array.isArray(data)) {
                    $.each(data, function(code, name) { selector.append(new Option(name, code)); });
                } else {
                    $.each(data, function(_, item) {
                        const option = new Option(item[textKey], item[valueKey]);
                        if (callback) callback(option, item);
                        selector.append(option);
                    });
                }
                if (extra) selector.append(extra);
            }

            function resetFields(...fields) {
                fields.forEach(field => { field.html('<option value="0" selected disabled>Please Select...</option>').prop('disabled', true); });
            }

            function resetAccessories() {
                $('#accessories').empty().prop('disabled', true); $('#apackamount').val('0');
            }

            function updateAccessoriesAmount() {
                let total = 0;

                $('#accessories option:selected').each(function() {
                    total += parseFloat($(this).data('price')) || 0;
                });

                $('#apackamount').val(total.toFixed(2));
            }

            function attachDuplicateCheck(input, fieldName, type) {
                input.off('change.duplicate').on('change.duplicate', function() {
                    const value = this.value.trim();
                    if (value) {
                        $.ajax({
                            url: '{{ backpack_url('sales/booking/check-receipt') }}/' + value, method: 'GET',
                            success: function(data) {
                                if (data !== 0) {
                                    $('#receiptvoucherwarning').show().text(fieldName.charAt(0).toUpperCase() + fieldName.slice(1).replace(/-/g, ' ') + ' already exists');
                                    input.addClass('is-invalid'); $('#submitBtn').prop('disabled', true);
                                } else {
                                    $('#receiptvoucherwarning').hide(); input.removeClass('is-invalid'); $('#submitBtn').prop('disabled', false);
                                }
                            },
                            error: handleAjaxError('Error checking number')
                        });
                    }
                });
                input.off('input.duplicate').on('input.duplicate', function() { resetDuplicateState(); });
            }

            function resetDuplicateState() {
                $('#receiptvoucherwarning').hide(); $('#receiptvoucherinput').removeClass('is-invalid'); $('#submitBtn').prop('disabled', false);
            }

            function showErrorModal() {
                const errors = $('#bookingForm').validate().errorList;
                let errorHtml = '<ul>';
                $.each(errors, function(_, error) { errorHtml += '<li>' + error.message + '</li>'; });
                errorHtml += '</ul>';
                $('#errorModal .modal-body').html(errorHtml);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('errorModal')).show();
            }

            function toggleRequiredMark(selector, show) {
                if (typeof selector === 'string') {
                    $(selector).siblings('label').find('.required-mark').css('display', show ? 'inline' : 'none');
                } else if (Array.isArray(selector)) {
                    selector.forEach(sel => $(sel).siblings('label').find('.required-mark').css('display', show ? 'inline' : 'none'));
                } else {
                    $(selector).siblings('label').find('.required-mark').css('display', show ? 'inline' : 'none');
                }
            }

            function handleAjaxError(message) {
                return function(xhr) { console.error(message, xhr); alert(message + '. Please try again.'); };
            }

            $(document).ready(initBookingForm);
        })();

        $('#bookingForm').on('submit', function(e) {
            $('#bookingsource, #dsadetails, #user, #chassis, #segment, #model, #variant, #color').prop('disabled', false);
        });

        $(document).ready(function() {
            const initialBookingSource = $('#bookingsource').val();
            $('#dsadetails').prop('disabled', initialBookingSource !== 'DSA');
            if (initialBookingSource !== 'DSA') $('#dsadetails').next('.select2-container').addClass('select2-disabled-custom');
            else $('#dsadetails').next('.select2-container').removeClass('select2-disabled-custom');
            $('#bookingsource').trigger('change');

            $('#registrationno, #refchassisregno').on('input', function() { this.value = this.value.replace(/\s+/g, '').toUpperCase(); });
            $('#registrationno, #refchassisregno').on('keydown', function(e) { if (e.key === ' ' || e.keyCode === 32) e.preventDefault(); });
        });

        function debounce(func, wait) {
            let timeout;
            return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => { func.apply(this, args); }, wait); };
        }

        function setupDynamicLocation(selectId, inputId, inputName) {
            const $select = $(`#${selectId}`), $input = $(`#${inputId}`);
            $select.on('change', function() {
                if ($(this).val() === 'OTHER') {
                    $input.removeClass('d-none').attr('name', inputName);
                    if ($select.prop('required')) $input.prop('required', true);
                    $select.removeAttr('name');
                } else {
                    $input.addClass('d-none').removeAttr('name').prop('required', false);
                    $select.attr('name', inputName);
                }
            });
        }

        setupDynamicLocation('vpo_select', 'vpo_input', 'vpo');
        setupDynamicLocation('tehsil_select', 'tehsil_input', 'customer_tehsil');
        setupDynamicLocation('district_select', 'district_input', 'customer_district');
        setupDynamicLocation('state_select', 'state_input', 'city');

        function updateBookingTerritory() {
            let distVal = $('#district_select').val();
            if (distVal === 'OTHER') distVal = $('#district_input').val();
            distVal = String(distVal || '').trim().toUpperCase();
            if (distVal) {
                if (['BIKANER', 'CHURU', 'SUJANGARH'].includes(distVal)) $('#territory').val('OWN TERRITORY');
                else $('#territory').val('OTHER TERRITORY');
            }
        }
        $('#district_select').on('change', updateBookingTerritory);
        $('#district_input').on('input', updateBookingTerritory);

        $('#zipcode').on('input blur', debounce(function() {
            const pincode = ($('#zipcode').val() || '').trim();
            const $vpoSelect = $('#vpo_select'), $tehsilSelect = $('#tehsil_select'), $districtSelect = $('#district_select'), $stateSelect = $('#state_select');

            if (pincode.length !== 6) {
                $vpoSelect.html('<option value="">Select VPO</option>'); $tehsilSelect.html('<option value="">Select Tehsil</option>');
                $districtSelect.html('<option value="">Select District</option>'); $stateSelect.html('<option value="">Select State</option>');
                return;
            }
            $vpoSelect.html('<option value="">Loading...</option>'); $tehsilSelect.html('<option value="">Loading...</option>');
            $districtSelect.html('<option value="">Loading...</option>'); $stateSelect.html('<option value="">Loading...</option>');

            fetch(`https://api.postalpincode.in/pincode/${pincode}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data[0] && data[0].Status === 'Success') {
                        const postOffices = data[0].PostOffice || [];
                        let vpos = [], tehsils = [], districts = [], states = [];
                        postOffices.forEach(po => {
                            if (po.Name && !vpos.includes(po.Name)) vpos.push(po.Name);
                            let block = (po.Block && po.Block !== 'NA') ? po.Block : po.District;
                            if (block && !tehsils.includes(block)) tehsils.push(block);
                            if (po.District && !districts.includes(po.District)) districts.push(po.District);
                            if (po.State && !states.includes(po.State)) states.push(po.State);
                        });

                        const buildOptions = (arr, placeholder, currentValue) => {
                            let html = `<option value="">${placeholder}</option>`;
                            let valueFound = false, matchedValue = null, safeCurrent = (currentValue || '').toString().trim().toLowerCase();
                            arr.forEach(val => { if (val.toString().trim().toLowerCase() === safeCurrent) { valueFound = true; matchedValue = val; } });
                            if (!valueFound && safeCurrent.length > 0) {
                                arr.forEach(val => {
                                    const safeVal = val.toString().trim().toLowerCase();
                                    if (safeVal.includes(safeCurrent) || safeCurrent.includes(safeVal)) { valueFound = true; matchedValue = val; }
                                });
                            }
                            arr.forEach(val => {
                                const selected = (valueFound && val === matchedValue) ? 'selected' : '';
                                html += `<option value="${val}" ${selected}>${val}</option>`;
                            });
                            const otherSelected = (!valueFound && currentValue) ? 'selected' : '';
                            html += `<option value="OTHER" ${otherSelected}>Other</option>`;
                            return { html, valueFound, matchedValue: valueFound ? matchedValue : currentValue };
                        };

                        const handleRender = (selectId, inputId, inputName, optionsArr, placeholder, currentValue) => {
                            const renderData = buildOptions(optionsArr, placeholder, currentValue);
                            $(`#${selectId}`).html(renderData.html);
                            if (!renderData.valueFound && currentValue) {
                                $(`#${inputId}`).val(currentValue).removeClass('d-none').attr('name', inputName);
                                if ($(`#${selectId}`).prop('required')) $(`#${inputId}`).prop('required', true);
                                $(`#${selectId}`).removeAttr('name');
                            } else {
                                $(`#${inputId}`).addClass('d-none').removeAttr('name').prop('required', false);
                                $(`#${selectId}`).attr('name', inputName);
                            }
                        };

                        handleRender('vpo_select', 'vpo_input', 'vpo', vpos, 'Select VPO', prefillData.vpo);
                        handleRender('tehsil_select', 'tehsil_input', 'customer_tehsil', tehsils, 'Select Tehsil', prefillData.tehsil);
                        handleRender('district_select', 'district_input', 'customer_district', districts, 'Select District', prefillData.district);
                        handleRender('state_select', 'state_input', 'city', states, 'Select State', prefillData.city);
                        $('#district_select').trigger('change');
                        if (prefillData.territory) setTimeout(() => { if (!$('#territory').val()) $('#territory').val(prefillData.territory); }, 50);
                    } else {
                        $vpoSelect.html('<option value="">No VPO Found</option>'); $tehsilSelect.html('<option value="">No Tehsil Found</option>');
                        $districtSelect.html('<option value="">No District Found</option>'); $stateSelect.html('<option value="">No State Found</option>');
                    }
                })
                .catch(error => {
                    $vpoSelect.html('<option value="">Select VPO</option>'); $tehsilSelect.html('<option value="">Select Tehsil</option>');
                    $districtSelect.html('<option value="">Select District</option>'); $stateSelect.html('<option value="">Select State</option>');
                });
        }, 400));

        if (prefillData.pincode && String(prefillData.pincode).length === 6) {
            $('#zipcode').val(prefillData.pincode).trigger('blur');
        }
    </script>
    @section('after_scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        <script>
            $(document).ready(function() {
                $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            });
        </script>
    @endsection
@endpush
