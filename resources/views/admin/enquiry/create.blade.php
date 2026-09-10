{{-- =========================== ENQUIRY FORM =========================== --}}

@extends(backpack_view('blank'))

@section('title', isset($enquiry) ? 'Edit Enquiry' : 'Add New Enquiry')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .enquiry-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 2rem;
            background: #fff;
        }

        .enquiry-card .card-header {
            background: #fff;
            border-bottom: 1px solid #edf2f9;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .required-mark {
            color: red;
        }

        /* --- REMOVE NUMBER ARROWS --- */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        .table-responsive table {
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    @php
        $isVirtual = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'VIRTUAL';
        $isReference = isset($enquiry) && strtoupper($enquiry->source_code ?? '') === 'REFERENCE';
        $isWhatsapp = isset($enquiry) && strtoupper($enquiry->source_code ?? '') === 'WHATSAPP';

        // Dynamic Heading string
        $enqTypeStr = 'Enquiry';
        if ($isReference) {
            $enqTypeStr = 'Reference Enquiry';
        } elseif ($isVirtual) {
            $enqTypeStr = 'Virtual Enquiry';
        } elseif ($isWhatsapp) {
            $enqTypeStr = 'WhatsApp Campaign Enquiry';
        }

        // Dynamic Flexbox Ordering for the cards
        $order = [
            'whatsapp' => 0,
            'credentials' => 1,
            'vehicle' => 2,
            'customer_primary' => 3,
            'exchange' => 4,
            'sc_detail' => 5,
        ];

        if ($isReference) {
            $order['credentials'] = 1;
            $order['customer_primary'] = 2;
            $order['vehicle'] = 3;
            $order['sc_detail'] = 4;
            $order['exchange'] = 5;
        } elseif ($isVirtual) {
            $order['customer_primary'] = 1;
            $order['sc_detail'] = 2;
            $order['credentials'] = 3;
            $order['vehicle'] = 4;
            $order['exchange'] = 5;
        } elseif ($isWhatsapp) {
            $order['whatsapp'] = 1;
            $order['customer_primary'] = 2;
            $order['vehicle'] = 3;
            $order['sc_detail'] = 4;
            $order['credentials'] = 5;
            $order['exchange'] = 6;
        }

        // Display Mappings for Codes
        $devMap = collect($deviation_stages ?? [])
            ->pluck('value', 'code')
            ->toArray();
        $remMap = collect($sc_fup_remarks ?? [])
            ->pluck('value', 'code')
            ->toArray();
        $remTypeMap = collect($sc_fup_remarks_types ?? [])
            ->pluck('value', 'code')
            ->toArray();

        $enqStageMap = collect($enquiry_stages ?? [])
            ->pluck('value', 'code')
            ->toArray();
        $custStageMap = collect($customer_stages ?? [])
            ->pluck('value', 'code')
            ->toArray();
        $fupTypeMap = collect($follow_up_types ?? [])
            ->pluck('value', 'code')
            ->toArray();

        // ========================================================================
        // GLOBAL DATA RESOLUTION FOR THE TOP COMPARISON CARD
        // ========================================================================
        $oemScCode = old('sc_code', $enquiry->sc_code ?? '');
        $oemScDisplay = '';
        $oemScMileId = '';
        $oemScBranch = '';
        $oemScLocation = '';

        if ($oemScCode && isset($saleconsultants)) {
            $matchedSc = collect($saleconsultants)->firstWhere('person_code', $oemScCode);
            if ($matchedSc) {
                $oemScDisplay = ($matchedSc['display_name'] ?? '') . ' - ' . ($matchedSc['employee_code'] ?? '');
                $oemScMileId = $matchedSc['employee_code'] ?? '';
                $oemScBranch = \App\Services\OrgService::branchName($matchedSc['primary_branch_code'] ?? '');
                $oemScLocation = \App\Services\OrgService::locationName($matchedSc['primary_loc_code'] ?? '');
            }
        }

        $creScCode = $enquiry?->x8_sc_code;
        $creScDisplay = '—';
        $creScMileId = '—';
        $creScBranch = '—';
        $creScLocation = '—';

        if ($creScCode && isset($saleconsultants)) {
            $matchedCreSc = collect($saleconsultants)->firstWhere('person_code', $creScCode);
            if ($matchedCreSc) {
                $creScDisplay = ($matchedCreSc['display_name'] ?? '') . ' - ' . ($matchedCreSc['employee_code'] ?? '');
                $creScMileId = $enquiry?->x8_sc_mile_id ?? ($matchedCreSc['employee_code'] ?? '');
                $creScBranch = \App\Services\OrgService::branchName($matchedCreSc['primary_branch_code'] ?? '');
                $creScLocation = \App\Services\OrgService::locationName($matchedCreSc['primary_loc_code'] ?? '');
            } else {
                $creScDisplay = $creScCode;
            }
        }

        $lastCre = isset($creFups) && count($creFups) > 0 ? (is_array($creFups) ? end($creFups) : $creFups->last()) : null;
        $creNextDate = $lastCre?->cre_next_fup_date;
        $scNextDate = $enquiry?->next_planned_followup_date;

        $comparisonRows = [];
        $isQuick = false;
        
        if (isset($enquiry) && in_array(strtoupper($enquiry->current_origin ?? ''), ['LONG', 'QUICK'])) {
            $isQuick = strtoupper($enquiry->current_origin ?? '') === 'QUICK';
            $fmtDate = fn($d) => !empty($d) ? \Carbon\Carbon::parse($d)->format('d-M-Y') : '—';
            $fmtDateTime = fn($d) => !empty($d) ? \Carbon\Carbon::parse($d)->format('d-M-Y H:i') : '—';

            $comparisonRows = [
                ['label' => 'Enquiry No.', 'dump' => $isQuick ? ($enquiry->quick_enquiry_no ?: '—') : ($enquiry->enquiry_no ?: '—'), 'cre' => 'XENQ-'.$enquiry->id, 'skip_comparison' => true],
                ['label' => 'Enquiry Date', 'dump' => $fmtDate($isQuick ? ($enquiry->quick_enquiry_date ?? '') : ($enquiry->enquiry_date ?? '')), 'cre' => $fmtDateTime($enquiry->created_at)],
                ['label' => 'Enquiry Assign Date', 'dump' => $fmtDate($isQuick ? ($enquiry->quick_enq_assign_date ?? '') : ($enquiry->enq_assign_date ?? '')), 'cre' => $fmtDate($enquiry->x8_enq_assign_date)],
                ['label' => 'Booking No.', 'dump' => $enquiry->oem_booking_no ?? '—', 'cre' => $enquiry->x8_booking_no ?? ($enquiry->booking_no ?? '—')],
                ['label' => 'Booking Date', 'dump' => $fmtDate($enquiry->oem_booking_date ?? ''), 'cre' => $fmtDate($enquiry->x8_booking_date ?? ($enquiry->booking_date ?? ''))],
                ['label' => 'Booking Cancellation Date', 'dump' => $fmtDate($enquiry->oem_cancellation_date ?? ''), 'cre' => $fmtDate($enquiry->x8_cancellation_date ?? ($enquiry->cancellation_date ?? ''))],
                ['label' => 'Model', 'dump' => $enquiry->model ?: '—', 'cre' => collect($models ?? [])->firstWhere('code', $enquiry->model_code)['name'] ?? ($enquiry->model_code ?: '—')],
                ['label' => 'Variant', 'dump' => $enquiry->variant ?: '—', 'cre' => collect($variants ?? [])->firstWhere('code', $enquiry->variant_code)['name'] ?? ($enquiry->variant_code ?: '—')],
                ['label' => 'Likely Purchase in Days', 'dump' => collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry->likely_purchase_days)['value'] ?? ($enquiry->likely_purchase_days ?: '—'), 'cre' => collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry->cre_likely_purchase_days)['value'] ?? ($enquiry->cre_likely_purchase_days ?: '—')],
                ['label' => 'Mobile No.', 'dump' => $enquiry->mobile ?: '—', 'cre' => $enquiry->mobile ?: '—'],
                ['label' => 'Purchase Type', 'dump' => collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type)['value'] ?? ($enquiry->purchase_type ?: '—'), 'cre' => collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type_crm)['value'] ?? ($enquiry->purchase_type_crm ?: '—'), 'manual_mismatch' => 'mismatch_purchase_type'],
                ['label' => 'SC Name', 'dump' => !empty($oemScDisplay) ? $oemScDisplay : ($enquiry->sc_code ?? '—'), 'cre' => $creScDisplay ?: '—'],
                ['label' => 'SC Mile ID', 'dump' => !empty($oemScMileId) ? $oemScMileId : '—', 'cre' => $creScMileId ?: '—'],
                ['label' => 'Next Fup Date', 'dump' => $fmtDateTime($scNextDate), 'cre' => $fmtDateTime($creNextDate), 'manual_mismatch' => 'mismatch_next_fup'],
                ['label' => 'Enquiry Stage', 'dump' => $enqStageMap[$enquiry->dms_enquiry_stage ?? ''] ?? ($enquiry->dms_enquiry_stage ?? ($enquiry->stage ?: '—')), 'cre' => $enqStageMap[$lastCre?->cre_enq_stage ?? ''] ?? ($lastCre?->cre_enq_stage ?: '—'), 'manual_mismatch' => 'mismatch_enq_stage'],
                ['label' => 'Booking Cancellation Reason', 'dump' => $enquiry->oem_cancel_reason ?? '—', 'cre' => $enquiry->cancel_reason ?? '—'],
                ['label' => 'Booking Cancellation Remarks', 'dump' => $enquiry->oem_cancel_remarks ?? '—', 'cre' => $enquiry->cancel_remarks ?? '—'],
                ['label' => 'Latest Followup Remarks', 'dump' => $remMap[$enquiry->recent_fup_comments ?? ''] ?? ($enquiry->recent_fup_comments ?? ($enquiry->remarks ?: '—')), 'cre' => $lastCre?->cre_fup_remarks ?: '—', 'manual_mismatch' => 'mismatch_fup_remarks'],
            ];

            if (!empty($enquiry->test_drive_no)) {
                $comparisonRows[] = [
                    'label' => 'Test Drive Verification',
                    'dump' => '—',
                    'cre' => '—',
                    'manual_mismatch' => 'mismatch_test_drive'
                ];
            }

            // HIDE BOOKING RELATED ROWS IF BOTH DATA POINTS ARE EMPTY OR DASH
            $bookingLabels = ['Booking No.', 'Booking Date', 'Booking Cancellation Date', 'Booking Cancellation Reason', 'Booking Cancellation Remarks'];
            
            $comparisonRows = array_filter($comparisonRows, function($row) use ($bookingLabels) {
                if (in_array($row['label'], $bookingLabels)) {
                    $d = trim(strip_tags((string)$row['dump']));
                    $c = trim(strip_tags((string)$row['cre']));
                    if (in_array($d, ['—', '-', '']) && in_array($c, ['—', '-', ''])) {
                        return false;
                    }
                }
                return true;
            });
        }
    @endphp

    <div class="container-fluid pb-5">

        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h2 class="mb-0 fw-bold">
                {{ isset($enquiry) ? 'Edit ' . $enqTypeStr . ' : XENQ-' . $enquiry->id : 'Add New ' . $enqTypeStr }}
            </h2>
        </div>

        <form method="POST" id="enquiryForm"
            action="{{ isset($enquiry) ? backpack_url('enquiry/' . $enquiry->id) : backpack_url('enquiry') }}"
            enctype="multipart/form-data">
            @csrf
            @if (isset($enquiry))
                @method('PUT')
            @endif

            {{-- Capture previous URL to redirect back to the exact listing page --}}
            <input type="hidden" name="http_referrer" value="{{ old('http_referrer', url()->previous()) }}">

            {{-- ERROR DISPLAY BLOCK --}}
            @if ($errors->any())
                <div class="alert alert-danger rounded-3 shadow-sm pb-0 mb-4">
                    <ul class="mb-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Hidden fields to preserve validation for frozen OEM SC Location/Branch --}}
            <input type="hidden" name="dealer_branch" value="{{ old('dealer_branch', $enquiry->dealer_branch ?? '') }}">
            <input type="hidden" name="dealer_location"
                value="{{ old('dealer_location', $enquiry->dealer_location ?? '') }}">

            {{-- =========================== VIRTUAL CALL DETAILS =========================== --}}
            @if ($isVirtual)
                <div class="card enquiry-card">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Virtual Call Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Virtual Number</label>
                                <input type="text" name="virtual_no" class="form-control"
                                    value="{{ $enquiry->virtual_no ?? '' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Call Date</label>
                                <input type="text" name="virtual_call_date" class="form-control"
                                    value="{{ !empty($enquiry->virtual_call_date) ? \Carbon\Carbon::parse($enquiry->virtual_call_date)->format('d-M-Y H:i') : '' }}"
                                    readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Call Duration</label>
                                <input type="text" name="call_duration" class="form-control"
                                    value="{{ $enquiry->call_duration ?? '' }}" readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Customer Mobile <span class="text-danger">*</span></label>
                                <input type="text" id="mobile" name="mobile" maxlength="10" class="form-control"
                                    value="{{ old('mobile', $enquiry->mobile ?? '') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Call Nature <span class="text-danger">*</span></label>
                                <select name="call_nature" id="call_nature" class="form-control form-select" required>
                                    <option value="">Select Option</option>
                                    @foreach ($call_nature_virtual as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('call_nature', $enquiry->call_nature ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks <small class="text-muted"></small></label>
                                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $enquiry->remarks ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div id="full_enquiry_form" class="{{ $isVirtual ? 'd-none' : 'd-flex flex-column' }}">

                {{-- =========================== NEW COMPARISON CARD (TOP) =========================== --}}
                @if(!empty($comparisonRows))
                <div class="card enquiry-card" style="order: -1;">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Data Overview: CRE vs SC</h4>
                    </div>
                    <div class="card-body">
                        {{-- Hidden input moved here so the backend still processes mismatches --}}
                        <input type="hidden" name="comparison_rendered" value="1">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                    <tr>
                                        <th class="text-center p-3" style="width: 25%;">Parameters</th>
                                        <th class="text-center p-3" style="width: 30%;">OEM Dump Data ({{ $isQuick ? 'Quick' : 'Long' }})</th>
                                        <th class="text-center p-3" style="width: 30%;">CRE X8 Data</th>
                                        <th class="text-center p-3" style="width: 15%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($comparisonRows as $row)
                                    @php
                                        // Standardize strings for automatic matching
                                        $d = trim(strip_tags((string)$row['dump']));
                                        $c = trim(strip_tags((string)$row['cre']));
                                        $bothEmpty = in_array($d, ['—', '-', '']) && in_array($c, ['—', '-', '']);
                                        
                                        if (in_array($d, ['—', '-', ''])) $d = '';
                                        if (in_array($c, ['—', '-', ''])) $c = '';
                                        $isAutoMismatch = ($d !== $c);
                                    @endphp
                                    <tr>
                                        <td class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">{{ $row['label'] }}</td>
                                        <td class="align-middle p-2">
                                            <div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $row['dump'] }}</div>
                                        </td>
                                        <td class="align-middle p-2">
                                            <div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $row['cre'] }}</div>
                                        </td>
                                        <td class="align-middle p-2">
                                            <div class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center" style="min-height: 38px;">
                                                @if(isset($row['manual_mismatch']))
                                                    {{-- Manual Checkbox Logic --}}
                                                    <div class="d-flex align-items-center">
                                                        <input class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="{{ $row['manual_mismatch'] }}" value="1"
                                                            style="width: 1.1rem; height: 1.1rem;">
                                                        <label class="form-check-label ms-2 mb-0 fw-bold text-secondary" style="font-size: 0.9rem; cursor: pointer;">Mismatch</label>
                                                    </div>
                                                @elseif(isset($row['skip_comparison']) && $row['skip_comparison'])
                                                    {{-- Bypass Match/Mismatch completely --}}
                                                    <span class="text-secondary fw-bold" style="font-size: 1rem;">—</span>
                                                @else
                                                    {{-- Automatic Comparison Logic --}}
                                                    @if($bothEmpty)
                                                        <span class="text-secondary fw-bold" style="font-size: 1rem;">—</span>
                                                    @elseif($isAutoMismatch)
                                                        <span class="text-danger fw-bold d-flex align-items-center" style="font-size: 0.9rem;">
                                                            <i class="la la-times-circle me-1" style="font-size: 1.2rem;"></i> Mismatch
                                                        </span>
                                                    @else
                                                        <span class="text-success fw-bold d-flex align-items-center" style="font-size: 0.9rem;">
                                                            <i class="la la-check-circle me-1" style="font-size: 1.2rem;"></i> Match
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- =========================== WHATSAPP CAMPAIGN DETAILS =========================== --}}
                @if ($isWhatsapp)
                    <div class="card enquiry-card" style="order: {{ $order['whatsapp'] }};">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">WhatsApp Campaign Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Lead Date & Time</label>
                                    <input type="text" class="form-control"
                                        value="{{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y H:i') : '—' }}"
                                        readonly style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Campaign Name</label>
                                    <input type="text" class="form-control"
                                        value="{{ $enquiry->wapp_campaign_name ?? '—' }}" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Date</label>
                                    <input type="text" class="form-control"
                                        value="{{ !empty($enquiry->wapp_campaign_date) ? \Carbon\Carbon::parse($enquiry->wapp_campaign_date)->format('d-M-Y') : '—' }}"
                                        readonly style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Segment</label>
                                    <input type="text" class="form-control"
                                        value="{{ $enquiry->wapp_campaign_segment ?? '—' }}" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Model</label>
                                    <input type="text" class="form-control"
                                        value="{{ $enquiry->wapp_campaign_model ?? '—' }}" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- =========================== 1. ENQUIRY CREDENTIALS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['credentials'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Enquiry Credentials</h4>
                    </div>
                    <div class="card-body">

                        @if (isset($enquiry) && !$isVirtual)
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-center align-middle mb-0"
                                    style="background-color: #e9ecef;">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center px-3">Enquiry Platform</th>
                                            <th class="text-center px-3">Enquiry No.</th>
                                            <th class="text-center px-3">Enquiry Date</th>
                                            <th class="text-center px-3">Assignment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (
                                            !empty($enquiry->quick_enquiry_no) ||
                                                !empty($enquiry->quick_enquiry_date) ||
                                                !empty($enquiry->quick_enq_assign_date))
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-center px-3 text-dark">
                                                    OEM Quick</td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->quick_enquiry_no ?: '—' }}</div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->quick_enquiry_date ? \Carbon\Carbon::parse($enquiry->quick_enquiry_date)->format('d-M-Y') : '—' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->quick_enq_assign_date ? \Carbon\Carbon::parse($enquiry->quick_enq_assign_date)->format('d-M-Y') : '—' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif

                                        @if (!empty($enquiry->enquiry_no) || !empty($enquiry->enquiry_date))
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-center px-3 text-dark">
                                                    OEM Long</td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->enquiry_no ?: '—' }}</div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->enquiry_date ? \Carbon\Carbon::parse($enquiry->enquiry_date)->format('d-M-Y') : '—' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enquiry->enq_assign_date ? \Carbon\Carbon::parse($enquiry->enq_assign_date)->format('d-M-Y') : '—' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">
                                                Xceler8</td>
                                            <td>
                                                <div class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                    {{ 'XENQ-' . $enquiry->id }}</div>
                                            </td>
                                            <td>
                                                <div class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                    {{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y') : '—' }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                    {{ !empty($enquiry->x8_enq_assign_date) ? \Carbon\Carbon::parse($enquiry->x8_enq_assign_date)->format('d-M-Y') : '—' }}
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Type <span class="text-danger">*</span></label>
                                <select name="enquiry_type" id="enquiry_type" class="form-control form-select" required>
                                    <option value="">Select Enquiry Type</option>
                                    @foreach ($enquiry_types as $etype)
                                        <option value="{{ $etype['code'] }}"
                                            {{ old('enquiry_type', $enquiry->enquiry_type ?? '') == $etype['code'] ? 'selected' : '' }}>
                                            {{ $etype['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Source <span class="text-danger"
                                        id="source_code_asterisk">*</span></label>
                                <select name="source_code" id="source_code" class="form-control form-select" required>
                                    <option value="">Select Enquiry Source</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Sub Source <span class="text-danger d-none"
                                        id="sub_source_asterisk">*</span></label>
                                <select name="sub_source" id="sub_source" class="form-control form-select" disabled>
                                    <option value="">Select Enquiry Sub Source</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Planned Campaign <span class="text-danger d-none"
                                        id="planned_campaign_asterisk">*</span></label>
                                <select name="planned_campaign" id="planned_campaign" class="form-control form-select"
                                    disabled>
                                    <option value="">Select Planned Campaign</option>
                                    @foreach ($campaigns as $name)
                                        <option value="{{ $name }}"
                                            {{ old('planned_campaign', $enquiry->planned_campaign ?? '') == $name ? 'selected' : '' }}>
                                            {{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">DMS Enquiry No. <small class="text-muted"></small></label>
                                <input type="text" name="dms_enq_no" class="form-control"
                                    value="{{ old('dms_enq_no', $enquiry->dms_enq_no ?? '') }}" placeholder="Enter DMS Enquiry No.">
                            </div>

                            <div class="row w-100 m-0 p-0 {{ $isReference ? 'd-flex' : 'd-none' }}" id="referenceFields">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referee Type <span class="text-danger">*</span></label>
                                    <select name="referred_by" id="referred_by" class="form-control form-select">
                                        <option value="">Select Referee Type</option>
                                        <option value="Customer"
                                            {{ old('referred_by', $enquiry->referred_by ?? '') == 'Customer' ? 'selected' : '' }}>
                                            Customer</option>
                                        <option value="Team Member"
                                            {{ old('referred_by', $enquiry->referred_by ?? '') == 'Team Member' ? 'selected' : '' }}>
                                            Team Member</option>
                                        <option value="Promoter"
                                            {{ old('referred_by', $enquiry->referred_by ?? '') == 'Promoter' ? 'selected' : '' }}>
                                            Promoter</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referee Phone Number <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="referee_phone" id="referee_phone" class="form-control"
                                        maxlength="10" value="{{ old('referee_phone', $enquiry->referee_phone ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referee Name <span class="text-danger">*</span></label>
                                    <input type="text" name="referee_name" id="referee_name" class="form-control"
                                        value="{{ old('referee_name', $enquiry->referee_name ?? '') }}">
                                </div>
                            </div>

                            {{-- <div class="col-md-3 mb-3">
                                <label class="form-label">Likely Purchase In Days <span
                                        class="text-danger">*</span></label>
                                <select name="likely_purchase_date" class="form-control form-select">
                                    <option value="">Select Likely Purchase In Days</option>
                                    @foreach ($likely_purchase_dates as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('likely_purchase_date', $enquiry->likely_purchase_date ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div> --}}

                            {{-- <div class="col-md-3 mb-3">
                                <label class="form-label">Select Dealer Branch <span class="text-danger">*</span></label>
                                <select name="dealer_branch" id="dealer_branch" class="form-control form-select"
                                    required>
                                    <option value="">Select Dealer Branch</option>
                                    @foreach ($branches as $code => $name)
                                        <option value="{{ $code }}"
                                            {{ old('dealer_branch', $enquiry->dealer_branch ?? '') == $code ? 'selected' : '' }}>
                                            {{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Select Dealer Location <span
                                        class="text-danger">*</span></label>
                                <select name="dealer_location" id="dealer_location" class="form-control form-select"
                                    required>
                                    <option value="">Select Dealer Location</option>
                                </select>
                            </div> --}}
                        </div>
                    </div>
                </div>

                {{-- =========================== 2. VEHICLE DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['vehicle'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Vehicle Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">

                            {{-- Always show raw DB Model if it exists in DB --}}
                            @if (isset($enquiry) && !empty($enquiry->model))
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Model Family</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->model }}" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                            @endif

                            {{-- Always show raw DB Variant if it exists in DB --}}
                            @if (isset($enquiry) && !empty($enquiry->variant))
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Variant Family</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->variant }}" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                            @endif

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Segment <span class="text-danger">*</span></label>
                                <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                    <option value="">Select Segment</option>
                                    @foreach ($segments as $code => $name)
                                        <option value="{{ $code }}"
                                            {{ old('segment_code', $enquiry->segment_code ?? '') == $code ? 'selected' : '' }}>
                                            {{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Model <span class="text-danger">*</span></label>
                                <select name="model_code" id="model_code" class="form-control form-select" required>
                                    <option value="">Select Model</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Variant <span class="text-danger">*</span></label>
                                <select name="variant_code" id="variant_code" class="form-control form-select" required>
                                    <option value="">Select Variant</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Color @if (!$isReference && !$isWhatsapp)
                                        <span class="text-danger"></span>
                                    @endif
                                </label>
                                <select name="color_code" id="color_code" class="form-control form-select"
                                    @if (!$isReference && !$isWhatsapp)  @endif>
                                    <option value="">Select Color</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fuel Type <span class="text-danger">*</span></label>
                                <input type="text" id="fuel_type" class="form-control" readonly>
                                <input type="hidden" id="fuel_type_id" name="fuel_type">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Transmission <span class="text-danger">*</span></label>
                                <input type="text" id="transmission" name="transmission" class="form-control"
                                    readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Drivetrain <span class="text-danger">*</span></label>
                                <input type="text" id="drivetrain" name="drivetrain" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Seating <span class="text-danger">*</span></label>
                                <input type="text" id="seating" name="seating" class="form-control" readonly>
                            </div>

                            <div class="row w-100 m-0 p-0" id="bevSection" style="display: none;">
                                <div class="col-md-4 mb-3">
                                    <label>Do you have an EV? <span class="text-danger">*</span></label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="has_ev" value="Yes"
                                                {{ old('has_ev', $enquiry->has_ev ?? '') == 'Yes' ? 'checked' : '' }}>
                                            <label class="form-check-label">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="has_ev" value="No"
                                                {{ old('has_ev', $enquiry->has_ev ?? '') == 'No' ? 'checked' : '' }}>
                                            <label class="form-check-label">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row w-100 m-0 p-0" id="commercialSection" style="display:none;">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Usage Area <small class="text-muted"></small></label>
                                    <select name="usage_area" class="form-control form-select">
                                        <option value="">Select Usage Area</option>
                                        @foreach ($usage_areas as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('usage_area', $enquiry->usage_area ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">KM Travelled Daily <small
                                            class="text-muted"></small></label>
                                    <select name="km_travelled_daily" class="form-control form-select">
                                        <option value="">Select KM Travelled Daily</option>
                                        @foreach ($km_travelled_daily as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('km_travelled_daily', $enquiry->km_travelled_daily ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application Type <small class="text-muted"></small></label>
                                    <select name="application_type" id="application_type"
                                        class="form-control form-select">
                                        <option value="">Select Application Type</option>
                                        @foreach ($application_types as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('application_type', $enquiry->application_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application <small class="text-muted"></small></label>
                                    <select name="application" id="application" class="form-control form-select">
                                        <option value="">Select Application</option>
                                        @foreach ($applications as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('application', $enquiry->application ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== 3. CUSTOMER PRIMARY DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['customer_primary'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Customer Primary Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $enquiry->name ?? '') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Care Of <small class="text-muted"></small></label>
                                <select name="care_of_type" id="care_of_type" class="form-control form-select">
                                    <option value="">Please Select...</option>
                                    <option value="1" {{ old('care_of_type', $enquiry->care_of_type ?? '') == '1' ? 'selected' : '' }}>Son of</option>
                                    <option value="2" {{ old('care_of_type', $enquiry->care_of_type ?? '') == '2' ? 'selected' : '' }}>Daughter of</option>
                                    <option value="3" {{ old('care_of_type', $enquiry->care_of_type ?? '') == '3' ? 'selected' : '' }}>Married to</option>
                                    <option value="4" {{ old('care_of_type', $enquiry->care_of_type ?? '') == '4' ? 'selected' : '' }}>Guardian Name</option>
                                    <option value="5" id="ownedByOption" style="display: none;" {{ old('care_of_type', $enquiry->care_of_type ?? '') == '5' ? 'selected' : '' }}>Owned By</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Care Of Name <small class="text-muted"></small></label>
                                <input type="text" name="care_of" id="care_of" class="form-control uppercase"
                                    value="{{ old('care_of', $enquiry->care_of ?? '') }}">
                            </div>
                            @if (!$isVirtual)
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" id="mobile" name="mobile" maxlength="10"
                                        class="form-control" value="{{ old('mobile', $enquiry->mobile ?? '') }}"
                                        required>
                                </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Alternate Mobile <small class="text-muted"></small></label>
                                <input type="text" id="alternate_mobile" name="alternate_mobile" maxlength="15"
                                    class="form-control"
                                    value="{{ old('alternate_mobile', $enquiry->alternate_mobile ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email ID <small class="text-muted"></small></label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $enquiry->email ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-control form-select" required>
                                    <option value="">Select Gender</option>
                                    @foreach ($genders as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('gender', $enquiry->gender ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Address block smoothly integrated --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Pin Code <span class="text-danger">*</span></label>
                                <input type="text" id="zipcode" name="zipcode" maxlength="6" class="form-control"
                                    value="{{ old('zipcode', $enquiry->zipcode ?? '') }}" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">VPO <span class="text-danger">*</span></label>
                                <select id="vpo_select" class="form-control form-select" required>
                                    <option value="">Select VPO</option>
                                </select>
                                <input type="text" id="vpo_input" class="form-control mt-2 d-none"
                                    placeholder="Enter VPO Manually" value="{{ $enquiry->vpo ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Tehsil <span class="text-danger">*</span></label>
                                <select id="tehsil_select" class="form-control form-select" required>
                                    <option value="">Select Tehsil</option>
                                </select>
                                <input type="text" id="tehsil_input" class="form-control mt-2 d-none"
                                    placeholder="Enter Tehsil Manually" value="{{ $enquiry->tehsil ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">District <span class="text-danger">*</span></label>
                                <select id="district_select" class="form-control form-select" required>
                                    <option value="">Select District</option>
                                </select>
                                <input type="text" id="district_input" class="form-control mt-2 d-none"
                                    placeholder="Enter District Manually" value="{{ $enquiry->district ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">State <span class="text-danger">*</span></label>
                                <select id="state_select" class="form-control form-select" required>
                                    <option value="">Select State</option>
                                </select>
                                <input type="text" id="state_input" class="form-control mt-2 d-none"
                                    placeholder="Enter State Manually" value="{{ $enquiry->city ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Territory <span class="text-danger">*</span></label>
                                <select id="territory" name="territory" class="form-control form-select" required>
                                    <option value="">Select Territory</option>
                                    <option value="OWN TERRITORY"
                                        {{ old('territory', $enquiry->territory ?? '') == 'OWN TERRITORY' ? 'selected' : '' }}>
                                        OWN TERRITORY</option>
                                    <option value="OTHER TERRITORY"
                                        {{ old('territory', $enquiry->territory ?? '') == 'OTHER TERRITORY' ? 'selected' : '' }}>
                                        OTHER TERRITORY</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== 4. EXCHANGE & FINANCE =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['exchange'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Exchange & Finance</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if (isset($enquiry))
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Purchase Type (SC Input)</label>
                                    <input type="text" class="form-control"
                                        value="{{ collect($purchase_types ?? [])->firstWhere('code', $enquiry?->purchase_type)['value'] ?? ($enquiry?->purchase_type ?? '—') }}"
                                        readonly style="background-color: #e9ecef;">
                                    {{-- Hidden field to retain the value for validation / saving --}}
                                    <input type="hidden" name="purchase_type"
                                        value="{{ old('purchase_type', $enquiry->purchase_type ?? '') }}">
                                </div>
                            @endif

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Purchase Type (CRE Input) <span
                                        class="text-danger">*</span></label>
                                <select name="purchase_type_crm" id="purchase_type_crm" class="form-control form-select"
                                    required>
                                    <option value="">Select Purchase Type (CRE Input)</option>
                                    @foreach ($purchase_types as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                                <select name="fin_mode" id="fin_mode" class="form-control form-select">
                                    <option value="" disabled selected>-- Select Finance Mode --</option>
                                    <option value="In-house"
                                        {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'In-house' ? 'selected' : '' }}>
                                        In-house</option>
                                    <option value="Customer Self"
                                        {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Customer Self' ? 'selected' : '' }}>
                                        Customer Self</option>
                                    <option value="Cash"
                                        {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Cash' ? 'selected' : '' }}>Cash
                                    </option>
                                    <option value="Yet To Decide"
                                        {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Yet To Decide' ? 'selected' : '' }}>
                                        Yet To Decide</option>
                                    <option value="Purchase Plan Cancelled"
                                        {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Purchase Plan Cancelled' ? 'selected' : '' }}>
                                        Purchase Plan Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3" id="financierbox" style="display:none;">
                                <label class="form-label">Financier <small class="text-muted"></small></label>
                                <select name="financier" id="financier" class="form-control form-select">
                                    <option value="">Select Financier</option>
                                    @foreach ($financiers ?? [] as $financier)
                                        <option value="{{ $financier->id }}"
                                            data-shortname="{{ $financier->short_name ?? '' }}"
                                            {{ (string) old('financier', $enquiry->financier ?? '') === (string) $financier->id ? 'selected' : '' }}>
                                            {{ $financier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3" id="financiershortnamebox" style="display:none;">
                                <label class="form-label">Financier Short Name</label>
                                <input type="text" id="financiershortname" class="form-control" readonly
                                    tabindex="-1" style="background-color: #e9ecef; pointer-events: none;">
                            </div>

                            {{-- NEW: Additional Buy Vehicle Section --}}
                            <div class="row w-100 m-0 p-0" id="additional_vehicle_section" style="display:none;">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Existing Make</label>
                                    <select name="brand_make" id="brand_make" class="form-control form-select">
                                        <option value="">Select Make</option>
                                        @foreach ($existing_car_oems as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('brand_make', $enquiry->brand_make ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Existing Model</label>
                                    <input type="text" name="brand_model" class="form-control"
                                        value="{{ old('brand_model', $enquiry->brand_model ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Existing Vehicle No.</label>
                                    <input type="text" name="vehicle_no" class="form-control"
                                        value="{{ old('vehicle_no', $enquiry->vehicle_no ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Existing Make Year</label>
                                    <input type="number" name="make_year" class="form-control"
                                        value="{{ old('make_year', $enquiry->make_year ?? '') }}">
                                </div>
                            </div>

                            @if (isset($enquiry) &&
                                    in_array($enquiry->purchase_type, ['Exchange Buy', 'Scrappage']) &&
                                    ($enquiry->brand_make || $enquiry->expected_price || $enquiry->lost_reason))
                                <div class="col-md-12 mt-3">
                                    <div class="bg-light p-3 rounded border">
                                        <h6 class="mb-3 text-secondary">Exchange Valuation Details (Processed by Exchange
                                            Team)</h6>
                                        <div class="row">
                                            <div class="col-md-3 mb-3"><label class="form-label">Make:</label> <input
                                                    class="form-control" disabled value="{{ $enquiry->brand_make }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Model:</label> <input
                                                    class="form-control" disabled value="{{ $enquiry->brand_model }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Reg No:</label> <input
                                                    class="form-control" disabled value="{{ $enquiry->vehicle_no }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Mfg Year:</label> <input
                                                    class="form-control" disabled value="{{ $enquiry->make_year }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Odometer:</label> <input
                                                    class="form-control" disabled value="{{ $enquiry->odo_reading }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Expected Price:</label>
                                                <input class="form-control" disabled
                                                    value="{{ $enquiry->expected_price }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Offered Price:</label>
                                                <input class="form-control" disabled
                                                    value="{{ $enquiry->offered_price }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Exchange Bonus:</label>
                                                <input class="form-control" disabled
                                                    value="{{ $enquiry->exchange_bonus }}">
                                            </div>
                                            <div class="col-md-3 mb-3"><label class="form-label text-danger">Reason for
                                                    Case Lost:</label> <input class="form-control" disabled
                                                    value="{{ $enquiry->lost_reason }}"></div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- =========================== 5. SALES CONSULTANT DETAIL =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['sc_detail'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Sales Consultant Details</h4>
                    </div>
                    <div class="card-body">

                        @if (isset($enquiry) && !$isReference && !$isVirtual && !$isWhatsapp)
                            @php
                                $oemScCode = old('sc_code', $enquiry->sc_code ?? '');
                                $oemScDisplay = '';
                                $oemScMileId = '';
                                $oemScBranch = '';
                                $oemScLocation = '';

                                if ($oemScCode && isset($saleconsultants)) {
                                    $matchedSc = collect($saleconsultants)->firstWhere('person_code', $oemScCode);
                                    if ($matchedSc) {
                                        $oemScDisplay =
                                            ($matchedSc['display_name'] ?? '') .
                                            ' - ' .
                                            ($matchedSc['employee_code'] ?? '');
                                        $oemScMileId = $matchedSc['employee_code'] ?? '';
                                        $oemScBranch = \App\Services\OrgService::branchName(
                                            $matchedSc['primary_branch_code'] ?? '',
                                        );
                                        $oemScLocation = \App\Services\OrgService::locationName(
                                            $matchedSc['primary_loc_code'] ?? '',
                                        );
                                    }
                                }
                            @endphp

                            <div class="row mb-4" style="pointer-events:none;">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">OEM Assigned SC</label>
                                    <input type="text" class="form-control" value="{{ $oemScDisplay }}" readonly
                                        tabindex="-1" style="background-color: #e9ecef;">
                                    <input type="hidden" name="sc_code" value="{{ $oemScCode }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">OEM Assigned SC Mile ID</label>
                                    <input type="text" id="oem_sc_mile_id" class="form-control"
                                        value="{{ $oemScMileId }}" readonly tabindex="-1"
                                        style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">OEM Assigned SC Branch</label>
                                    <input type="text" id="oem_sc_branch" class="form-control"
                                        value="{{ $oemScBranch }}" readonly tabindex="-1"
                                        style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">OEM Assigned SC Location</label>
                                    <input type="text" id="oem_sc_location" class="form-control"
                                        value="{{ $oemScLocation }}" readonly tabindex="-1"
                                        style="background-color: #e9ecef;">
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC @if (isset($enquiry))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select name="x8_sc_code" id="x8_sc_code" class="form-control form-select"
                                    @if (isset($enquiry)) required @endif>
                                    <option value="">Select X8 SC</option>
                                    @foreach ($saleconsultants as $consultant)
                                        <option value="{{ $consultant['person_code'] }}"
                                            data-mile-id="{{ $consultant['employee_code'] ?? '' }}"
                                            data-branch="{{ \App\Services\OrgService::branchName($consultant['primary_branch_code'] ?? '') }}"
                                            data-location="{{ \App\Services\OrgService::locationName($consultant['primary_loc_code'] ?? '') }}"
                                            {{ old('x8_sc_code', $enquiry->x8_sc_code ?? '') == $consultant['person_code'] ? 'selected' : '' }}>
                                            {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Mile ID</label>
                                <input type="text" name="x8_sc_mile_id" id="x8_sc_mile_id" class="form-control"
                                    value="{{ old('x8_sc_mile_id', $enquiry->x8_sc_mile_id ?? '') }}" readonly
                                    tabindex="-1" style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Branch</label>
                                <input type="text" id="x8_sc_branch" class="form-control" readonly tabindex="-1"
                                    style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Location</label>
                                <input type="text" id="x8_sc_location" class="form-control" readonly tabindex="-1"
                                    style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                        </div>

                    </div>
                </div>

                {{-- =========================== 6. CUSTOMER SECONDARY DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: 6;">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Customer Secondary Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Type</label>
                                <select name="occupation_type" class="form-control form-select">
                                    <option value="">Select Occupation Type</option>
                                    @foreach ($occupation_types as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('occupation_type', $enquiry->occupation_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Type <small class="text-muted"></small></label>
                                <select name="customer_type" class="form-control form-select">
                                    <option value="">Select Customer Type</option>
                                    @foreach ($customer_types as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('customer_type', $enquiry->customer_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Sub Type <small class="text-muted"></small></label>
                                <select name="occupation_sub_type" class="form-control form-select">
                                    <option value="">Select Occupation Sub Type</option>
                                    @foreach ($occupation_sub_types as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('occupation_sub_type', $enquiry->occupation_sub_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Company Name <small class="text-muted"></small></label>
                                <input type="text" name="company_name" class="form-control"
                                    value="{{ old('company_name', $enquiry->company_name ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">D.O.B. <small class="text-muted"></small></label>
                                <input type="text" id="dob" name="dob" class="form-control"
                                    value="{{ old('dob', $enquiry->dob ?? '') }}" placeholder="Select D.O.B.">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-control form-select">
                                    <option value="">Select Marital Status</option>
                                    @foreach ($marital_statuses as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('marital_status', $enquiry->marital_status ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Marriage <small class="text-muted"></small></label>
                                <input type="text" id="marriage_date" name="marriage_date" class="form-control"
                                    value="{{ old('marriage_date', $enquiry->marriage_date ?? '') }}"
                                    placeholder="Select Marriage Date">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age Group <small class="text-muted"></small></label>
                                <select name="age_group" class="form-control form-select">
                                    <option value="">Select Age Group</option>
                                    @foreach ($age_groups as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('age_group', $enquiry->age_group ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== 7. CONSIDERATION SET =========================== --}}
                <div class="card enquiry-card" style="order: 7;">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Consideration Set</h4>
                    </div>

                    <div class="card-body">

                        {{-- ================= SET 1 ================= --}}
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 1 - Brand</label>

                                <select id="consider_make" name="consider_make" class="form-control form-select">

                                    <option value="No Consideration"
                                        {{ old(
                                            'consider_make',
                                            $enquiry->consider_make ??
                                                'No
                                                                                                                                                                                                                                            Consideration',
                                        ) == 'No Consideration'
                                            ? 'selected'
                                            : '' }}>
                                        No Consideration
                                    </option>

                                    @foreach ($existing_car_oems as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('consider_make', $enquiry->consider_make ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 1 - Model</label>

                                <input type="text" id="consider_model" name="consider_model" class="form-control"
                                    value="{{ old('consider_model', $enquiry->consider_model ?? '') }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 1 - Variant</label>

                                <input type="text" id="consider_variant" name="consider_variant" class="form-control"
                                    value="{{ old('consider_variant', $enquiry->consider_variant ?? '') }}" readonly>
                            </div>
                        </div>


                        {{-- ================= SET 2 ================= --}}
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 2 - Brand</label>

                                <select id="consider_make_2" name="consid_brand2" class="form-control form-select">

                                    <option value="">No Consideration</option>

                                    @foreach ($existing_car_oems as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('consid_brand2', $enquiry->consid_brand2 ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 2 - Model</label>

                                <input type="text" id="consider_model_2" name="consid_model2" class="form-control"
                                    value="{{ old('consid_model2', $enquiry->consid_model2 ?? '') }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 2 - Variant</label>

                                <input type="text" id="consider_variant_2" name="consid_variant2"
                                    class="form-control"
                                    value="{{ old('consid_variant2', $enquiry->consid_variant2 ?? '') }}" readonly>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- =========================== EDIT ONLY: FOLLOW-UPS & STAGES =========================== --}}
                @if (isset($enquiry))

                    @php
                        $currentOrigin = strtoupper($enquiry->current_origin ?? '');
                    @endphp

                    @if ($currentOrigin === 'LONG' || $currentOrigin === 'QUICK')
                        <div class="card enquiry-card" style="order: 8;">
                            <div class="card-header">
                                <h4 class="mb-0 fw-bold">SC Follow Up Details</h4>
                            </div>
                            <div class="card-body">
                                @if ($currentOrigin === 'LONG')
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered text-center align-middle mb-0"
                                            style="background-color: #e9ecef;">
                                            <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                                <tr>
                                                    <th class="text-center px-3">Fup Count</th>
                                                    <th class="text-center px-3">Type</th>
                                                    <th class="text-center px-3">Status</th>
                                                    <th class="text-center px-3">Planned Date</th>
                                                    <th class="text-center px-3">Actual Date</th>
                                                    <th class="text-center px-3">Duration</th>
                                                    <th class="text-center px-3">Deviation</th>
                                                    <th class="text-center px-3">Enquiry Status</th>
                                                    <th class="text-center px-3">Remark Type</th>
                                                    <th class="text-center px-3">Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if (isset($fups) && count($fups) > 0)
                                                    @php $fupCount = count($fups); @endphp
                                                    @foreach ($fups as $index => $fup)
                                                        @php
                                                            $isHidden = false;
                                                            if ($fupCount > 4) {
                                                                // Show 1st FUP (index 0) and last 3 FUPs. Hide the rest.
                                                                if ($index > 0 && $index < $fupCount - 3) {
                                                                    $isHidden = true;
                                                                }
                                                            }
                                                        @endphp
                                                        <tr class="{{ $isHidden ? 'hidden-fup-row d-none' : '' }}">
                                                            <td
                                                                class="fw-bold align-middle table-secondary text-center px-3 text-dark">
                                                                {{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }}
                                                                Fup
                                                            </td>
                                                            <td>
                                                                <div
                                                                    class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                                    {{ $fupTypeMap[$fup->followup_type ?? ''] ?? ($fup->followup_type ?? '—') }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div
                                                                    class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                                    {{ $fup->followup_status ?? '—' }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div
                                                                    class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                                    {{ !empty($fup->planned_followup_date) ? \Carbon\Carbon::parse($fup->planned_followup_date)->format('d-M-Y') : '—' }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div
                                                                    class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                                    {{ !empty($fup->actual_followup_date) ? \Carbon\Carbon::parse($fup->actual_followup_date)->format('d-M-Y') : '—' }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div
                                                                    class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                                    {{ $fup->call_duration ?? '—' }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                                    style="min-width: 150px;">
                                                                    {{ $devMap[$fup->deviation_stage ?? ''] ?? ($fup->deviation_stage ?? '—') }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                                    style="min-width: 120px;">
                                                                    {{ $enqStageMap[$fup->enquiry_status ?? ''] ?? ($fup->enquiry_status ?? '—') }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                                    style="min-width: 120px;">
                                                                    {{ $remTypeMap[$fup->remark_type ?? ''] ?? ($fup->remark_type ?? '—') }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                                    style="min-width: 150px;">
                                                                    {{ $fup->comments ?? '—' }}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @if ($fupCount > 4 && $index == 0)
                                                            <tr id="toggleFupsRow" style="background-color: #f8f9fa;">
                                                                <td colspan="10" class="text-center py-2">
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-secondary fw-bold shadow-sm"
                                                                        id="toggleFupsBtn">
                                                                        <i class="la la-angle-down"></i> Show
                                                                        {{ $fupCount - 4 }} More FUPs
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="10" class="text-muted py-3 bg-white text-center">No
                                                            Follow-up Data Found</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                @elseif ($currentOrigin === 'QUICK')
                                    @php
                                        $tatMins = '—';
                                        if (
                                            !empty($enquiry?->first_planned_followup_date) &&
                                            !empty($enquiry?->first_actual_followup_date)
                                        ) {
                                            $pDate = \Carbon\Carbon::parse($enquiry->first_planned_followup_date);
                                            $aDate = \Carbon\Carbon::parse($enquiry->first_actual_followup_date);
                                            $tatMins = abs($pDate->diffInMinutes($aDate));
                                        }
                                    @endphp
                                    <div class="row mb-4" style="opacity: 0.85; pointer-events:none;">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Enquiry Status</label>
                                            <input type="text" class="form-control"
                                                value="{{ $enqStageMap[$enquiry?->quick_status ?? ''] ?? ($enquiry?->quick_status ?? ($enquiry?->stage ?? '—')) }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Fup Count</label>
                                            <input type="text" class="form-control"
                                                value="{{ $enquiry?->fup_count ?? '—' }}" readonly
                                                style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">1st Plan Fup Date</label>
                                            <input type="text" class="form-control"
                                                value="{{ !empty($enquiry?->first_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->first_planned_followup_date)->format('d-M-Y H:i') : '—' }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">1st Act Fup Date</label>
                                            <input type="text" class="form-control"
                                                value="{{ !empty($enquiry?->first_actual_followup_date) ? \Carbon\Carbon::parse($enquiry->first_actual_followup_date)->format('d-M-Y H:i') : '—' }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">TAT (In Mins)</label>
                                            <input type="text" class="form-control" value="{{ $tatMins }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Recent Plan Fup Date</label>
                                            <input type="text" class="form-control"
                                                value="{{ !empty($enquiry?->recent_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->recent_planned_followup_date)->format('d-M-Y H:i') : '—' }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Recent Act Fup Date</label>
                                            <input type="text" class="form-control"
                                                value="{{ !empty($enquiry?->recent_actual_followup_date) ? \Carbon\Carbon::parse($enquiry->recent_actual_followup_date)->format('d-M-Y H:i') : '—' }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Next Fup Date</label>
                                            <input type="text" class="form-control"
                                                value="{{ !empty($enquiry?->next_fup_date) ? \Carbon\Carbon::parse($enquiry->next_fup_date)->format('d-M-Y H:i') : (!empty($enquiry?->next_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->next_planned_followup_date)->format('d-M-Y H:i') : '—') }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Recent Fup Type</label>
                                            <input type="text" class="form-control"
                                                value="{{ $fupTypeMap[$enquiry?->followup_type ?? ''] ?? ($enquiry?->followup_type ?: '—') }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">1st Fup Remarks</label>
                                            <input type="text" class="form-control"
                                                value="{{ $remMap[$enquiry?->first_fup_remarks ?? ''] ?? ($enquiry?->first_fup_remarks ?: '—') }}"
                                                readonly style="background-color: #e9ecef;"
                                                title="{{ $remMap[$enquiry?->first_fup_remarks ?? ''] ?? ($enquiry?->first_fup_remarks ?? '') }}">
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <label class="form-label">Recent Fup Remarks</label>
                                            <input type="text" class="form-control"
                                                value="{{ $remTypeMap[$enquiry?->followup_remarks_type ?? ''] ?? ($enquiry?->followup_remarks_type ?: '—') }}"
                                                readonly style="background-color: #e9ecef;">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        {{-- 9. SC ENQUIRY STAGE --}}
                        <div class="card enquiry-card" style="order: 9;">
                            <div class="card-header">
                                <h4 class="mb-0 fw-bold">SC Enquiry Stage</h4>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-3 mb-3"><label class="form-label">Enquiry Stage</label><input
                                            type="text" class="form-control"
                                            value="{{ $enqStageMap[$enquiry?->dms_enquiry_stage ?? ''] ?? ($enquiry?->dms_enquiry_stage ?? ($enquiry?->stage ?? '—')) }}"
                                            readonly style="background-color: #e9ecef;"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive Count</label><input
                                            type="text" class="form-control"
                                            value="{{ $enquiry?->td_count ?? '0' }}" readonly
                                            style="background-color: #e9ecef;"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive No.</label><input
                                            type="text" class="form-control"
                                            value="{{ $enquiry?->test_drive_no ?? '—' }}" readonly
                                            style="background-color: #e9ecef;"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive Date</label><input
                                            type="text" class="form-control"
                                            value="{{ isset($enquiry) && $enquiry->td_date ? \Carbon\Carbon::parse($enquiry->td_date)->format('d-M-Y') : '—' }}"
                                            readonly style="background-color: #e9ecef;"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Booking Date</label><input
                                            type="text" class="form-control"
                                            value="{{ isset($enquiry) && ($enquiry->x8_booking_date || $enquiry->booking_date) ? \Carbon\Carbon::parse($enquiry->x8_booking_date ?? $enquiry->booking_date)->format('d-M-Y') : '—' }}"
                                            readonly style="background-color: #e9ecef;"></div>

                                    {{-- <div class="col-md-3 mb-3">
                                        <label class="form-label">Booking No.</label>
                                        <input type="text" name="booking_no" class="form-control"
                                            value="{{ old('booking_no', $enquiry?->booking_no ?? '') }}">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">OTF No.</label>
                                        <input type="text" name="otf_no" class="form-control"
                                            value="{{ old('otf_no', $enquiry?->otf_no ?? ($enquiry?->oem_otf_no ?? '')) }}">
                                    </div> --}}

                                    {{-- <div class="col-md-3 mb-3">
                                        <label class="form-label">DMS Enquiry No.</label>
                                        <input type="text" name="dms_enq_no" class="form-control"
                                            value="{{ old('dms_enq_no', $enquiry?->dms_enq_no ?? '') }}">
                                    </div> --}}

                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Likely Purchase In Days </label>
                                        <input type="text" class="form-control"
                                            value="{{ collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry?->likely_purchase_days)['value'] ?? ($enquiry?->likely_purchase_days ?? '—') }}"
                                            readonly style="background-color: #e9ecef;">
                                        {{-- Hidden input preserves the value on submit --}}
                                        <input type="hidden" name="likely_purchase_days"
                                            value="{{ old('likely_purchase_days', $enquiry?->likely_purchase_days ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif



                    {{-- 10. CRE ENQUIRY STAGE --}}
                    <div class="card enquiry-card" style="order: 10;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">CRE Enquiry Stage</h4>
                        </div>
                        <div class="card-body">
                            {{-- History Table --}}
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-center align-middle mb-0"
                                    style="background-color: #e9ecef;">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center px-3">Fup Count</th>
                                            <th class="text-center px-3">Planned Date</th>
                                            <th class="text-center px-3">Actual Date</th>
                                            <th class="text-center px-3">Deviation Stage</th>
                                            <th class="text-center px-3">Enquiry Stage</th>
                                            <th class="text-center px-3">Customer Stage</th>
                                            <th class="text-center px-3">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (isset($creFups) && count($creFups) > 0)
                                            @php $creFupCount = count($creFups); @endphp
                                            @foreach ($creFups as $index => $cre)
                                                @php
                                                    $isHidden = false;
                                                    if ($creFupCount > 4) {
                                                        // Show 1st FUP (index 0) and last 3 FUPs. Hide the rest.
                                                        if ($index > 0 && $index < $creFupCount - 3) {
                                                            $isHidden = true;
                                                        }
                                                    }
                                                @endphp
                                                <tr class="{{ $isHidden ? 'hidden-cre-fup-row d-none' : '' }}">
                                                    <td
                                                        class="fw-bold align-middle table-secondary text-center px-3 text-dark">
                                                        {{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }}
                                                        Fup
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                            {{ $cre?->cre_planned_fup_date ? \Carbon\Carbon::parse($cre->cre_planned_fup_date)->format('d-M-Y H:i') : '—' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                            {{ $cre?->cre_actual_fup_date ? \Carbon\Carbon::parse($cre->cre_actual_fup_date)->format('d-M-Y H:i') : '—' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-control bg-white h-auto border-0 text-center">
                                                            {{ $devMap[$cre?->cre_fup_deviation_stage ?? ''] ?? ($cre?->cre_fup_deviation_stage ?: '—') }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-control bg-white h-auto border-0 text-center">
                                                            {{ $enqStageMap[$cre?->cre_enq_stage ?? ''] ?? ($cre?->cre_enq_stage ?: '—') }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-control bg-white h-auto border-0 text-center">
                                                            {{ $custStageMap[$cre?->cre_customer_stage ?? ''] ?? ($cre?->cre_customer_stage ?: '—') }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                            style="min-width: 150px;">{{ $cre?->cre_fup_remarks ?: '—' }}
                                                        </div>
                                                    </td>
                                                </tr>
                                                @if ($creFupCount > 4 && $index == 0)
                                                    <tr id="toggleCreFupsRow" style="background-color: #f8f9fa;">
                                                        <td colspan="7" class="text-center py-2">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary fw-bold shadow-sm"
                                                                id="toggleCreFupsBtn">
                                                                <i class="la la-angle-down"></i> Show
                                                                {{ $creFupCount - 4 }} More FUPs
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="7" class="text-muted py-3 bg-white text-center">No CRE
                                                    Follow-up Data Found</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            {{-- Likely Purchase Details --}}
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Likely Purchase Date</label>
                                    <input type="text" id="cre_likely_purchase_date" name="cre_likely_purchase_date"
                                        class="form-control"
                                        value="{{ !empty($enquiry->cre_likely_purchase_date) ? \Carbon\Carbon::parse($enquiry->cre_likely_purchase_date)->format('d-M-Y') : '' }}"
                                        placeholder="DD-MMM-YYYY">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Likely Purchase In Days</label>
                                    <select id="cre_likely_purchase_days" name="cre_likely_purchase_days"
                                        class="form-control form-select">
                                        <option value="">Select Option</option>
                                        @foreach ($likely_purchase_dates as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('cre_likely_purchase_days', $enquiry->cre_likely_purchase_days ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Editable Input Row --}}
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Customer Stage</label>
                                    <select name="cre_customer_stage" id="cre_customer_stage"
                                        class="form-control form-select">
                                        <option value="">Select Option</option>
                                        @foreach ($customer_stages as $item)
                                            <option value="{{ $item['code'] }}"
                                                {{ old('cre_customer_stage', $enquiry->cre_customer_stage ?? '') == $item['code'] ? 'selected' : '' }}>
                                                {{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Enquiry Stage</label>
                                    <select name="cre_enq_stage" id="cre_enq_stage" class="form-control form-select"
                                        disabled>
                                        <option value="">Select Option</option>
                                    </select>
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Next Fup Date</label>
                                    <input type="text" id="cre_next_fup_date" name="cre_next_fup_date"
                                        class="form-control" value="{{ old('cre_next_fup_date') }}"
                                        placeholder="DD-MMM-YYYY HH:MM">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">CRE Followup Remarks</label>
                                    <textarea name="cre_fup_remarks" class="form-control" rows="1">{{ old('cre_fup_remarks') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if ($currentOrigin === 'LONG' || $currentOrigin === 'QUICK')
                        {{-- 11. COMPARISON - SC Vs CRE --}}
                        <div class="card enquiry-card" style="order: 11;">
                            <div class="card-header">
                                <h4 class="mb-0 fw-bold">Comparison - CRE V/s SC</h4>
                            </div>
                            <div class="card-body">
                                @php
                                    $scNextDate = $enquiry?->next_planned_followup_date;

                                    $lastCre =
                                        isset($creFups) && count($creFups) > 0
                                            ? (is_array($creFups)
                                                ? end($creFups)
                                                : $creFups->last())
                                            : null;
                                    $creNextDate = $lastCre?->cre_next_fup_date;

                                    // Resolve X8 Assigned SC Name
                                    $creScCode = $enquiry?->x8_sc_code;
                                    $creScDisplay = '—';
                                    if ($creScCode && isset($saleconsultants)) {
                                        $matchedCreSc = collect($saleconsultants)->firstWhere(
                                            'person_code',
                                            $creScCode,
                                        );
                                        if ($matchedCreSc) {
                                            $creScDisplay =
                                                ($matchedCreSc['display_name'] ?? '') .
                                                ' - ' .
                                                ($matchedCreSc['employee_code'] ?? '');
                                        } else {
                                            $creScDisplay = $creScCode;
                                        }
                                    }
                                @endphp

                                {{-- Hidden input to tell the backend this comparison table was rendered --}}
                                <input type="hidden" name="comparison_rendered" value="1">

                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered text-center align-middle mb-0"
                                        style="background-color: #e9ecef;">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr>
                                                <th class="text-center p-3">Description</th>
                                                <th class="text-center p-3" style="width: 25%;">CRE</th>
                                                <th class="text-center p-3" style="width: 25%;">SC</th>
                                                <th class="text-center p-3" style="width: 20%;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Enquiry Stage</td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enqStageMap[$lastCre?->cre_enq_stage ?? ''] ?? ($lastCre?->cre_enq_stage ?? '—') }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $enqStageMap[$enquiry?->dms_enquiry_stage ?? ''] ?? ($enquiry?->dms_enquiry_stage ?? ($enquiry?->stage ?? '—')) }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_enq_stage" value="1"
                                                            style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Next Fup Date</td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $creNextDate ? \Carbon\Carbon::parse($creNextDate)->format('d-M-Y H:i') : '—' }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $scNextDate ? \Carbon\Carbon::parse($scNextDate)->format('d-M-Y H:i') : '—' }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_next_fup" value="1"
                                                            style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Latest Fup Remarks</td>
                                                <td class="align-middle p-2">
                                                    <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                        style="max-height: 50px; overflow-y: auto;">
                                                        {{ $lastCre?->cre_fup_remarks ?? '—' }}</div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div class="form-control bg-white h-auto border-0 text-wrap text-center"
                                                        style="max-height: 50px; overflow-y: auto;">
                                                        {{ $remMap[$enquiry?->recent_fup_comments] ?? ($enquiry?->recent_fup_comments ?? ($enquiry?->remarks ?? '—')) }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center"
                                                        style="height: 100%; min-height: 38px;">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_fup_remarks" value="1"
                                                            style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Purchase Type</td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ collect($purchase_types ?? [])->firstWhere('code', $enquiry?->purchase_type_crm)['value'] ?? ($enquiry?->purchase_type_crm ?? '—') }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ collect($purchase_types ?? [])->firstWhere('code', $enquiry?->purchase_type)['value'] ?? ($enquiry?->purchase_type ?? '—') }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_purchase_type"
                                                            value="1" style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Likely Purchase In Days</td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry?->cre_likely_purchase_days)['value'] ?? ($enquiry?->cre_likely_purchase_days ?? '—') }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry?->likely_purchase_days)['value'] ?? ($enquiry?->likely_purchase_days ?? '—') }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_likely_purchase"
                                                            value="1" style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                    Assigned SC</td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ $creScDisplay }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                        {{ !empty($oemScDisplay) ? $oemScDisplay : $enquiry?->sc_code ?? '—' }}
                                                    </div>
                                                </td>
                                                <td class="align-middle p-2">
                                                    <div
                                                        class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                        <input
                                                            class="form-check-input border-secondary cursor-pointer m-0"
                                                            type="checkbox" name="mismatch_assigned_sc" value="1"
                                                            style="width: 1.2rem; height: 1.2rem;">
                                                        <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            @if (!empty($enquiry?->test_drive_no))
                                                <tr>
                                                    <td
                                                        class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">
                                                        Test Drive Verification</td>
                                                    <td class="align-middle p-2">
                                                        <div
                                                            class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                            —</div>
                                                    </td>
                                                    <td class="align-middle p-2">
                                                        <div
                                                            class="form-control bg-white h-auto border-0 text-nowrap text-center">
                                                            —</div>
                                                    </td>
                                                    <td class="align-middle p-2">
                                                        <div
                                                            class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center">
                                                            <input
                                                                class="form-check-input border-secondary cursor-pointer m-0"
                                                                type="checkbox" name="mismatch_test_drive"
                                                                value="1" style="width: 1.2rem; height: 1.2rem;">
                                                            <label class="form-check-label ms-2 mb-0">Mismatch</label>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- =========================== REMARKS =========================== --}}
                @if (!$isVirtual)
                    <div class="card enquiry-card" style="order: 12;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">Remarks</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">

                                    <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $enquiry?->remarks ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            <div class="d-flex justify-content-center mt-3 mb-5">
                <button type="submit" class="btn btn-success btn-lg px-5 py-2 shadow-sm fw-bold">
                    {{ isset($enquiry) ? 'Update Enquiry' : 'Save Enquiry' }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const debounce = (func, delay = 500) => {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        };

        function formatDuration(input) {
            let val = input.value.replace(/\D/g, '');
            if (val.length > 6) val = val.substring(0, 6);
            let h = val.substring(0, 2);
            let m = val.substring(2, 4);
            let s = val.substring(4, 6);
            if (m.length === 2 && parseInt(m) > 59) m = '59';
            if (s.length === 2 && parseInt(s) > 59) s = '59';
            let formatted = h;
            if (val.length > 2) formatted += ':' + m;
            if (val.length > 4) formatted += ':' + s;
            input.value = formatted;
        }

        function loadKeywordDropdown(keyword, parent, $target, placeholder = 'Select Option', selected = '', parentKeyword =
            '') {
            if (!parent) return $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true);
            let url = "{{ route('admin.master.keyword-values', ['keyword' => '__K__', 'parent' => '__P__']) }}".replace(
                '__K__', encodeURIComponent(keyword)).replace('__P__', encodeURIComponent(parent));
            if (parentKeyword) {
                url += (url.includes('?') ? '&' : '?') + 'parent_keyword=' + encodeURIComponent(parentKeyword);
            }

            $.ajax({
                url: url,
                type: "GET",
                beforeSend: () => $target.html('<option>Loading...</option>').prop('disabled', true),
                success: (response) => {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, (_, item) => {
                        let isSelected = (String(selected).toUpperCase() === String(item.code)
                            .toUpperCase()) ? 'selected' : '';
                        html += `<option value="${item.code}" ${isSelected}>${item.value}</option>`;
                    });
                    $target.html(html).prop('disabled', false);
                },
                error: () => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true)
            });
        }

        function fetchDropdown(url, $target, placeholder, selected = '', callback = null) {
            $target.html('<option value="">Loading...</option>').prop('disabled', true);
            $.get(url).done((response) => {
                let html = `<option value="">${placeholder}</option>`;
                $.each(response, (code, item) => {
                    let val = typeof item === 'object' ? item.name : item;
                    let attrs = typeof item === 'object' ?
                        `data-fuel="${item.fuel_type || ''}" data-fuel-id="${item.fuel_type_id || ''}" data-transmission="${item.transmission || ''}" data-drivetrain="${item.drivetrain || ''}" data-seating="${item.seating || ''}"` :
                        '';
                    html +=
                        `<option value="${code}" ${selected == code ? 'selected' : ''} ${attrs}>${val}</option>`;
                });
                $target.html(html).prop('disabled', false);
                if (callback) callback();
            }).fail(() => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true));
        }

        function populateScDetails($select, prefix) {
            const $option = $select.find('option:selected');
            const mileId = $option.data('mile-id') || '';
            const branch = $option.data('branch') || '';
            const location = $option.data('location') || '';

            $(`#${prefix}_mile_id`).val(mileId);
            $(`#${prefix}_branch`).val(branch);
            $(`#${prefix}_location`).val(location);
        }

        const currentEnquiry = {
            isEdit: @json(isset($enquiry)),
            enquiryType: @json(old('enquiry_type', $enquiry->enquiry_type ?? '')),
            source: @json(old('source_code', $enquiry->source_code ?? '')),
            subSource: @json(old('sub_source', $enquiry->sub_source ?? '')),
            segment: @json(old('segment_code', $enquiry->segment_code ?? '')),
            model: @json(old('model_code', $enquiry->model_code ?? '')),
            variant: @json(old('variant_code', $enquiry->variant_code ?? '')),
            color: @json(old('color_code', $enquiry->color_code ?? '')),
            dealerBranch: @json(old('dealer_branch', $enquiry->dealer_branch ?? '')),
            dealerLocation: @json(old('dealer_location', $enquiry->dealer_location ?? '')),
            applicationType: @json(old('application_type', $enquiry->application_type ?? '')),
            application: @json(old('application', $enquiry->application ?? '')),
            vpo: @json(old('vpo', $enquiry->vpo ?? '')),
            tehsil: @json(old('tehsil', $enquiry->tehsil ?? '')),
            district: @json(old('district', $enquiry->district ?? '')),
            city: @json(old('city', $enquiry->city ?? '')),
            territory: @json(old('territory', $enquiry->territory ?? '')),
            creEnqStage: @json(old('cre_enq_stage', $enquiry->cre_enq_stage ?? ''))
        };

        $(function() {
            // Bind X8 SC dropdown to auto-fill its corresponding details
            $('#x8_sc_code').on('change', function() {
                populateScDetails($(this), 'x8_sc');
            });

            // Trigger the change on page load to fill details for Edit mode
            if ($('#x8_sc_code').val()) {
                $('#x8_sc_code').trigger('change');
            }

            const isVirtual = @json($isVirtual);
            if (isVirtual && $('#call_nature').length > 0) {
                const $fullForm = $('#full_enquiry_form');

                function toggleVirtualForm() {
                    const text = $('#call_nature').find('option:selected').text().trim().toUpperCase();

                    // Changed from includes('SALES') to strict exact match
                    if (text === 'SALES') {
                        $fullForm.removeClass('d-none').addClass('d-flex flex-column');
                        $fullForm.find('input, select, textarea').prop('disabled', false);
                        setTimeout(() => {
                            $('#segment_code').trigger('change');
                            $('#source_code').trigger('change');
                        }, 50);
                    } else {
                        $fullForm.removeClass('d-flex flex-column').addClass('d-none');
                        $fullForm.find('input, select, textarea').prop('disabled', true);
                    }
                }
                $('#call_nature').on('change', toggleVirtualForm);
                toggleVirtualForm();
            }

            const $segmentCode = $('#segment_code');
            const $modelCode = $('#model_code');
            const $variantCode = $('#variant_code');
            const $colorCode = $('#color_code');
            const $sourceCode = $('#source_code');
            const $subSource = $('#sub_source');
            const $plannedCampaign = $('#planned_campaign');



            let maxDob = new Date();
            maxDob.setFullYear(maxDob.getFullYear() - 18);

            flatpickr("#dob", {
                dateFormat: "d-M-Y",
                maxDate: maxDob,
                allowInput: true, // Allows clearing the date manually
                onChange: function(selectedDates, dateStr, instance) {
                    let $ageGroup = $('select[name="age_group"]');

                    if (selectedDates.length > 0) {
                        // 1. Calculate Exact Age
                        const today = new Date();
                        const birthDate = selectedDates[0];
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const m = today.getMonth() - birthDate.getMonth();
                        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }

                        let selectedCode = '';

                        // 2. Map exactly to your DB Codes
                        if (age < 30) {
                            selectedCode = '30_YEARS'; // < 30 Years
                        } else if (age >= 30 && age <= 45) {
                            selectedCode = '30_45_YEARS'; // 30 - 45 Years
                        } else if (age > 45) {
                            selectedCode = '45_YEARS'; // > 45 Years
                        }

                        // 3. Auto-select and Freeze the dropdown (removes arrow)
                        if (selectedCode) {
                            $ageGroup.val(selectedCode).trigger('change');
                            $ageGroup.css({
                                'pointer-events': 'none',
                                'background-color': '#e9ecef',
                                '-webkit-appearance': 'none',
                                '-moz-appearance': 'none',
                                'appearance': 'none'
                            }).attr('tabindex', '-1');
                        }

                        // 4. Calculate Minimum Marriage Date (18 years after DOB)
                        let minMarriageDate = new Date(birthDate);
                        minMarriageDate.setFullYear(minMarriageDate.getFullYear() + 18);
                        if (window.marriagePicker) {
                            window.marriagePicker.set('minDate', minMarriageDate);
                        }
                    } else {
                        // Clear and Unfreeze if date is completely removed (restores arrow)
                        $ageGroup.val('').trigger('change');
                        $ageGroup.css({
                            'pointer-events': 'auto',
                            'background-color': '',
                            '-webkit-appearance': '',
                            '-moz-appearance': '',
                            'appearance': ''
                        }).removeAttr('tabindex');
                        if (window.marriagePicker) {
                            window.marriagePicker.set('minDate', null); // Reset min limit
                        }
                    }
                }
            });

            // Page Load Check for Edit Mode (Freezes the dropdown if a DOB was already saved)
            if (($('#dob').val() || '').trim() !== '') {
                $('select[name="age_group"]').css({
                    'pointer-events': 'none',
                    'background-color': '#e9ecef',
                    '-webkit-appearance': 'none',
                    '-moz-appearance': 'none',
                    'appearance': 'none'
                }).attr('tabindex', '-1');
            } else {
                $('select[name="age_group"]').css({
                    'pointer-events': 'auto',
                    'background-color': '',
                    '-webkit-appearance': '',
                    '-moz-appearance': '',
                    'appearance': ''
                }).removeAttr('tabindex');
            }

            flatpickr("#cre_next_fup_date", {
                dateFormat: "d-M-Y H:i",
                enableTime: true,
                allowInput: false
            });

            // Toggle FUPs button listener
            $('#toggleFupsBtn').on('click', function() {
                const $hiddenRows = $('.hidden-fup-row');
                if ($hiddenRows.hasClass('d-none')) {
                    $hiddenRows.removeClass('d-none');
                    $(this).html('<i class="la la-angle-up"></i> Hide FUPs');
                } else {
                    $hiddenRows.addClass('d-none');
                    $(this).html('<i class="la la-angle-down"></i> Show ' + $hiddenRows.length +
                        ' More FUPs');
                }
            });

            // Toggle CRE FUPs button listener
            $('#toggleCreFupsBtn').on('click', function() {
                const $hiddenRows = $('.hidden-cre-fup-row');
                if ($hiddenRows.hasClass('d-none')) {
                    $hiddenRows.removeClass('d-none');
                    $(this).html('<i class="la la-angle-up"></i> Hide FUPs');
                } else {
                    $hiddenRows.addClass('d-none');
                    $(this).html('<i class="la la-angle-down"></i> Show ' + $hiddenRows.length +
                        ' More FUPs');
                }
            });

            flatpickr("#cre_likely_purchase_date", {
                dateFormat: "d-M-Y",
                allowInput: true, // Allows the user to backspace/clear the date if they want to manually pick days
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        // Calculate difference in days from today
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const selectedDate = selectedDates[0];
                        selectedDate.setHours(0, 0, 0, 0);

                        const diffTime = selectedDate - today;
                        const diffDays = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));

                        let selectedCode = '';

                        // Exact mapping based on your DB Keyword Codes
                        if (diffDays <= 15) {
                            selectedCode = '15_DAYS';
                        } else if (diffDays > 15 && diffDays <= 45) {
                            selectedCode = '15_45_DAYS';
                        } else if (diffDays > 45) {
                            selectedCode = '45_DAYS';
                        }

                        // Auto-select the dropdown and FREEZE IT
                        $('#cre_likely_purchase_days').val(selectedCode).trigger('change');
                        $('#cre_likely_purchase_days').css({
                            'pointer-events': 'none',
                            'background-color': '#e9ecef'
                        }).attr('tabindex', '-1');
                    } else {
                        // Clear if date is removed via calendar and UNFREEZE IT
                        $('#cre_likely_purchase_days').val('').trigger('change');
                        $('#cre_likely_purchase_days').css({
                            'pointer-events': 'auto',
                            'background-color': ''
                        }).removeAttr('tabindex');
                    }
                }
            });

            // NEW: Instantly unfreeze the dropdown if the user manually backspaces/deletes the text
            $('#cre_likely_purchase_date').on('input blur clear', function() {
                if ($(this).val().trim() === '') {
                    $('#cre_likely_purchase_days').css({
                        'pointer-events': 'auto',
                        'background-color': ''
                    }).removeAttr('tabindex');
                }
            });

            // Page Load Check: If a date is already saved/filled, freeze the days dropdown immediately
            if (($('#cre_likely_purchase_date').val() || '').trim() !== '') {
                $('#cre_likely_purchase_days').css({
                    'pointer-events': 'none',
                    'background-color': '#e9ecef'
                }).attr('tabindex', '-1');
            } else {
                $('#cre_likely_purchase_days').css({
                    'pointer-events': 'auto',
                    'background-color': ''
                }).removeAttr('tabindex');
            }

            // Initialize freeze on page load if a date already exists in the database
            if ($('#cre_likely_purchase_date').val()) {
                $('#cre_likely_purchase_days').css({
                    'pointer-events': 'none',
                    'background-color': '#e9ecef'
                }).attr('tabindex', '-1');
            }

            window.marriagePicker = flatpickr("#marriage_date", {
                dateFormat: "d-M-Y",
                maxDate: "today",
                allowInput: true // Allow user to manually clear it
            });

            // Listen for Marital Status Changes
            $('select[name="marital_status"]').on('change', function() {
                let statusText = $(this).find('option:selected').text().trim().toUpperCase();

                if (statusText === 'MARRIED') {
                    // Enable Marriage Date
                    $('#marriage_date').prop('disabled', false).css('background-color', '#fff');
                } else {
                    // Disable and clear Marriage Date
                    window.marriagePicker.clear();
                    $('#marriage_date').prop('disabled', true).css('background-color', '#e9ecef');
                }
            });

            // Initialize Marital Status Logic on Page Load
            setTimeout(() => {
                $('select[name="marital_status"]').trigger('change');
            }, 100);

            // Safer Finance Mode listener to show Financier dropdowns and prevent clearing on load
            $('#fin_mode').on('change', function() {
                const isInHouse = $(this).val() === 'In-house';

                // Show or hide both the financier and short name boxes based on the selection
                $('#financierbox, #financiershortnamebox').toggle(isInHouse);

                // Only clear the values if it is NOT In-house. 
                if (!isInHouse) {
                    $('#financier').val('');
                    $('#financiershortname').val('');
                }
            }).trigger('change');

            // Auto-populate Financier Short Name when a Financier is selected
            $('#financier').on('change', function() {
                const shortName = $(this).find(':selected').data('shortname') || '';
                $('#financiershortname').val(shortName);
            });

            // Trigger on page load so it pre-fills if editing an existing record
            if ($('#financier').val()) {
                $('#financier').trigger('change');
            }

            // NEW: Purchase Type listener to show/hide Additional Vehicle fields
            $('#purchase_type_crm').on('change', function() {
                const ptText = $(this).find('option:selected').text().trim().toUpperCase();

                if (ptText.includes('ADDITIONAL')) {
                    $('#additional_vehicle_section').show();
                } else {
                    $('#additional_vehicle_section').hide();
                }
            });

            // Trigger on page load so it opens immediately if editing an Additional Buy case
            setTimeout(() => {
                $('#purchase_type_crm').trigger('change');
            }, 100);

            // ================= CONSIDERATION SET 1 =================
            $('#consider_make').on('change', function() {

                const isValid =
                    $(this).val() &&
                    $(this).val() !== 'No Consideration';

                $('#consider_model, #consider_variant')
                    .prop('readonly', !isValid)
                    .val(isValid ? undefined : '');

            }).trigger('change');


            // ================= CONSIDERATION SET 2 =================
            $('#consider_make_2').on('change', function() {

                const isValid =
                    $(this).val() &&
                    $(this).val() !== 'No Consideration';

                $('#consider_model_2, #consider_variant_2')
                    .prop('readonly', !isValid)
                    .val(isValid ? undefined : '');

            }).trigger('change');

            // $('#enquiry_type').on('change', function() {
            //     // Get both the hidden value (code) and the visible text
            //     const typeVal = String($(this).val()).trim().toUpperCase();
            //     const typeText = $(this).find('option:selected').text().trim().toUpperCase().replace(/\s+/g,
            //         '');

            //     // Check for WALK_IN (value), or any text variations like WALKIN, WALK-IN
            //     if (typeVal === 'WALK_IN' || typeText.includes('WALKIN') || typeText.includes('WALK_IN')) {
            //         // Disable Source & hide asterisk
            //         $sourceCode.html('<option value="">Select Enquiry Source</option>').val('').prop(
            //             'disabled', true).prop('required', false);
            //         $('#source_code_asterisk').addClass('d-none');

            //         // Disable Sub Source & hide asterisk
            //         $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
            //             'disabled', true).prop('required', false);
            //         $('#sub_source_asterisk').addClass('d-none');

            //         // Trigger change to hide any reference fields
            //         $sourceCode.trigger('change');
            //     } else {
            //         // Enable Source & show asterisk
            //         $sourceCode.prop('disabled', false).prop('required', true);
            //         $('#source_code_asterisk').removeClass('d-none');

            //         loadKeywordDropdown('ENQ_SOURCE', $(this).val(), $sourceCode, 'Select Enquiry Source',
            //             currentEnquiry.source);

            //         // Reset Sub Source
            //         $subSource.html('<option value="">Select Enquiry Sub Source</option>').prop('disabled',
            //             true).prop('required', false);
            //         $('#sub_source_asterisk').addClass('d-none');

            //         if (currentEnquiry.isEdit) setTimeout(() => $sourceCode.trigger('change'), 300);
            //     }
            // });
            $('#enquiry_type').on('change', function() {
                // Safely extract the exact, unmodified value for the DB lookup
                const rawVal = $(this).val() || '';
                const typeText = String($(this).find('option:selected').text() || '').trim().toUpperCase()
                    .replace(/\s+/g, '');

                // 1. If empty selection (e.g., user selects "Select Enquiry Type")
                if (rawVal === '') {
                    $sourceCode.html('<option value="">Select Enquiry Source</option>').val('').prop(
                        'disabled', true).prop('required', false);
                    $('#source_code_asterisk').addClass('d-none');
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
                        'disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                    return;
                }

                // 2. If Walk-In (Bypass sources entirely)
                if (rawVal.toUpperCase() === 'WALK_IN' || typeText.includes('WALKIN') || typeText.includes(
                        'WALK_IN')) {
                    $sourceCode.html('<option value="">Select Enquiry Source</option>').val('').prop(
                        'disabled', true).prop('required', false);
                    $('#source_code_asterisk').addClass('d-none');
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
                        'disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                    $sourceCode.trigger('change');
                } else {
                    // 3. Normal Selection (Fetch Sources dynamically via AJAX)
                    $sourceCode.prop('disabled', false).prop('required', true);
                    $('#source_code_asterisk').removeClass('d-none');

                    // Call the AJAX loader using the exact database code (rawVal)
                    loadKeywordDropdown('ENQ_SOURCE', rawVal, $sourceCode, 'Select Enquiry Source',
                        currentEnquiry.source);

                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').prop('disabled',
                        true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                }
            });

            // CONDITIONAL DROPDOWN: Customer Stage -> Enquiry Stage
            $('#cre_customer_stage').on('change', function() {
                const rawVal = $(this).val() || '';
                const $enqStage = $('#cre_enq_stage');

                if (rawVal === '') {
                    $enqStage.html('<option value="">Select Option</option>').val('').prop('disabled',
                        true);
                    return;
                }

                $enqStage.prop('disabled', false);

                loadKeywordDropdown('ENQUIRY_STAGE', rawVal, $enqStage, 'Select Option', currentEnquiry
                    .creEnqStage, 'CUSTOMER_STAGE');
                // Wait slightly for AJAX to populate, then trigger change to fire handleStageRules()
                setTimeout(() => {
                    $enqStage.trigger('change');
                }, 600);
            });

            // Trigger on page load to pre-fill Edit mode correctly
            if ($('#cre_customer_stage').val()) {
                $('#cre_customer_stage').trigger('change');
            }

            // Helper to freeze/unfreeze Next Fup Date based on stage
            function handleStageRules() {
                const enqStageVal = ($('select[name="cre_enq_stage"]').val() || '').trim().toUpperCase();
                const $custStage = $('select[name="cre_customer_stage"]');
                let custStageVal = ($custStage.val() || '').trim().toUpperCase();
                let custStageText = ($custStage.find('option:selected').text() || '').trim().toUpperCase();

                // 1. If Enquiry Stage is LOST or DROPPED, auto-select LOST and freeze Customer Stage
                if (enqStageVal === 'LOST' || enqStageVal === 'DROPPED') {
                    $custStage.find('option').each(function() {
                        if ($(this).val() === 'LOST' || $(this).text().trim().toUpperCase() === 'LOST') {
                            $custStage.val($(this).val());
                        }
                    });
                    $custStage.css({
                        'pointer-events': 'none',
                        'background-color': '#e9ecef'
                    }).attr('tabindex', '-1');
                    custStageVal = 'LOST';
                    custStageText = 'LOST';
                } else {
                    $custStage.css({
                        'pointer-events': 'auto',
                        'background-color': ''
                    }).removeAttr('tabindex');
                }

                // 2. Freeze Next FUP Date if Customer Stage OR Enquiry Stage is LOST or DROPPED
                const isLostOrDropped = (
                    custStageVal === 'LOST' || custStageVal === 'DROPPED' ||
                    custStageText === 'LOST' || custStageText === 'DROPPED' ||
                    enqStageVal === 'LOST' || enqStageVal === 'DROPPED'
                );

                const $nextFup = $('#cre_next_fup_date');

                if (isLostOrDropped) {
                    $nextFup.val('')
                        .prop('required', false)
                        .css({
                            'pointer-events': 'none',
                            'background-color': '#e9ecef'
                        })
                        .attr('tabindex', '-1');
                } else {
                    $nextFup.css({
                            'pointer-events': 'auto',
                            'background-color': ''
                        })
                        .removeAttr('tabindex');
                }
            }

            // Bind listeners to both Enquiry Stage and Customer Stage
            $('select[name="cre_enq_stage"]').on('change', handleStageRules);
            $('select[name="cre_customer_stage"]').on('change', handleStageRules);

            // Trigger check on page load if editing
            if (currentEnquiry.isEdit) {
                setTimeout(handleStageRules, 150);
            }

            // Trigger check on page load if editing
            if (currentEnquiry.isEdit) {
                $('select[name="cre_enq_stage"]').trigger('change');
            }

            // Flag to prevent wiping data during the page load and AJAX calls
            let isInitialLoad = true;

            const toggleReferenceFields = () => {
                let sourceVal = String($sourceCode.val() || '').trim().toUpperCase();
                let expectedSource = String(currentEnquiry.source || '').trim().toUpperCase();

                let isRef = false;
                // Force it to stay visible if it's loading a Reference enquiry
                if (sourceVal === 'REFERENCE' || (isInitialLoad && expectedSource === 'REFERENCE')) {
                    isRef = true;
                }

                if (isRef) {
                    $('#referenceFields').removeClass('d-none').addClass('d-flex');
                    $('#referred_by, #referee_phone, #referee_name').prop('required', true);
                } else {
                    $('#referenceFields').removeClass('d-flex').addClass('d-none');
                    $('#referred_by, #referee_phone, #referee_name').prop('required', false);

                    // CRITICAL FIX: Ignore the "Loading..." state. 
                    // ONLY wipe if the page is fully loaded and the user picks a non-reference source.
                    if (!isInitialLoad && sourceVal && sourceVal !== 'REFERENCE' && sourceVal !==
                        'LOADING...') {
                        $('#referred_by, #referee_phone, #referee_name').val('');
                    }
                }
            };

            // Allow the dropdowns to finish loading before we lift the data protection
            setTimeout(() => {
                isInitialLoad = false;
            }, 1500);

            // $sourceCode.on('change', function() {
            //     const source = $(this).val();
            //     toggleReferenceFields();
            //     if (source === 'HYPERLOCAL') {
            //         $subSource.prop('disabled', false).prop('required', true);
            //         $('#sub_source_asterisk').removeClass('d-none');
            //         loadKeywordDropdown('ENQUIRY_SUB_SOURCE', source, $subSource,
            //             'Select Enquiry Sub Source', currentEnquiry.subSource);
            //     } else {
            //         $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
            //             'disabled', true).prop('required', false);
            //         $('#sub_source_asterisk').addClass('d-none');
            //     }
            //     $plannedCampaign.prop('disabled', source !== 'ACTIVATIONS').prop('required', source ===
            //         'ACTIVATIONS').val(source === 'ACTIVATIONS' ? $plannedCampaign.val() : '');
            // }).trigger('change');

            $sourceCode.on('change', function() {
                const source = $(this).val() || '';
                const sourceText = $(this).find('option:selected').text().trim().toUpperCase();

                // 1. Redirect to Reference Enquiries page on creation
                if (!currentEnquiry.isEdit && source === 'REFERENCE') {
                    Swal.fire({
                        title: 'Reference Enquiry',
                        text: 'You can share and manage Reference Enquiries on the dedicated Reference page. Click OK to redirect.',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'OK',
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href =
                                "{{ backpack_url('enquiries/reference/add') }}";
                        } else {
                            $(this).val('').trigger(
                                'change'); // Reset the dropdown if they hit cancel
                        }
                    });
                    return; // Stop execution before toggling fields
                }

                toggleReferenceFields();

                // 2. STRICT Sub-Source Rule: ONLY allow if Source is HYPERLOCAL
                if (source === 'HYPERLOCAL') {
                    $subSource.prop('disabled', false).prop('required', true);
                    $('#sub_source_asterisk').removeClass('d-none');
                    loadKeywordDropdown('ENQUIRY_SUB_SOURCE', source, $subSource,
                        'Select Enquiry Sub Source', currentEnquiry.subSource);
                } else {
                    // Grey out and clear for absolutely every other Source
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
                        'disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                }

                // 3. Planned Campaign Rule: ONLY allow if Source is ACTIVATIONS
                const isActivations = (source === 'ACTIVATIONS' || sourceText === 'ACTIVATIONS');
                if (isActivations) {
                    $plannedCampaign.prop('disabled', false).prop('required', true);
                    $('#planned_campaign_asterisk').removeClass('d-none');
                } else {
                    $plannedCampaign.val('').prop('disabled', true).prop('required', false);
                    $('#planned_campaign_asterisk').addClass('d-none');
                }
            });

            $('#application_type').on('change', function() {
                // Load the secondary dropdown options via AJAX
                loadKeywordDropdown('APPLICATION', $(this).val(), $('#application'), 'Select Application',
                    currentEnquiry.application);

                // Freeze the #application dropdown if Personal or Commercial is selected
                const valText = $(this).find('option:selected').text().trim().toUpperCase();

                if (valText === 'PERSONAL' || valText === 'COMMERCIAL') {
                    // Freeze the child 'application' dropdown
                    $('#application').css({
                        'pointer-events': 'none',
                        'background-color': '#e9ecef'
                    }).attr('tabindex', '-1');

                    // Clear its value after a slight delay to allow the AJAX loadKeywordDropdown to finish
                    setTimeout(() => {
                        $('#application').val('');
                    }, 100);
                } else {
                    // Unfreeze the child 'application' dropdown
                    $('#application').css({
                        'pointer-events': 'auto',
                        'background-color': ''
                    }).removeAttr('tabindex');
                }
            }).trigger('change');

            // $('#dealer_branch').on('change', function() {
            //     fetchDropdown("{{ backpack_url('enquiry/locations') }}/" + $(this).val(), $(
            //             '#dealer_location'), 'Select Dealer Location', currentEnquiry.isEdit ?
            //         currentEnquiry.dealerLocation : '');
            // });

            $segmentCode.on('change', function() {
                const segmentCode = $(this).val();
                const segmentText = $(this).find('option:selected').text().trim().toUpperCase();
                checkDuplicateEnquiry();

                $('#bevSection').toggle(segmentText === 'BEV');
                if (segmentText === 'BEV') {
                    $('#bevSection').find('input[type="radio"]').prop('required', true);
                } else {
                    $('#bevSection').find('input[type="radio"]').prop('required', false);
                }

                const isCommercial = ['LMM', 'COMMERCIAL', 'CV', 'HCV', 'LCV', 'SCV'].some(val =>
                    segmentText.includes(val));
                $('#commercialSection').toggle(isCommercial);

                if (!isCommercial) {
                    // Only clear the hidden fields so old data isn't accidentally submitted
                    $('#commercialSection').find('select,input').val('');
                }

                $modelCode.add($variantCode).add($colorCode).html('<option value="">Select Option</option>')
                    .prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (segmentCode) {
                    fetchDropdown("{{ backpack_url('enquiry/models') }}/" + segmentCode, $modelCode,
                        'Select Model', currentEnquiry.isEdit ? currentEnquiry.model : '', () => {
                            if (currentEnquiry.isEdit && currentEnquiry.model) $modelCode.trigger(
                                'change');
                        });
                }
            });

            $modelCode.on('change', function() {
                const modelCode = $(this).val();
                $variantCode.add($colorCode).html('<option value="">Select Option</option>').prop(
                    'disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (modelCode) {
                    fetchDropdown("{{ backpack_url('enquiry/variants') }}/" + modelCode, $variantCode,
                        'Select Variant', currentEnquiry.isEdit ? currentEnquiry.variant : '', () => {
                            if (currentEnquiry.isEdit && currentEnquiry.variant) $variantCode.trigger(
                                'change');
                        });
                }
            });

            $variantCode.on('change', function() {
                const variantCode = $(this).val();
                const $selected = $(this).find(':selected');
                $('#fuel_type').val($selected.data('fuel') || '');
                $('#fuel_type_id').val($selected.data('fuel-id') || '');
                $('#transmission').val($selected.data('transmission') || '');
                $('#drivetrain').val($selected.data('drivetrain') || '');
                $('#seating').val($selected.data('seating') || '');
                $colorCode.html('<option value="">Loading...</option>').prop('disabled', true);
                if (variantCode) {
                    fetchDropdown("{{ backpack_url('enquiry/colors') }}/" + variantCode, $colorCode,
                        'Select Color', currentEnquiry.isEdit ? currentEnquiry.color : '');
                }
            });

            const checkDuplicateEnquiry = debounce(() => {
                if (currentEnquiry.isEdit) return;
                const mobile = $('#mobile').val();
                const segment = $segmentCode.val();
                if (mobile.length !== 10 || !segment) return;

                $.get("{{ route('enquiry.check-duplicate') }}", {
                    mobile,
                    segment_code: segment
                }, function(response) {
                    if (response.exists) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Duplicate Enquiry Found',
                            html: `An enquiry already exists for this Mobile and Segment.<br>Enquiry No. : <b>${response.enquiry_no || 'XENQ-'+response.id}</b><br><br>Redirecting to edit page...`,
                            confirmButtonText: 'Go to Edit',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed && response.id) {
                                window.location.href = "{{ backpack_url('enquiry') }}/" +
                                    response.id + "/edit";
                            }
                        });
                    }
                });
            });
            $('#mobile').on('input', checkDuplicateEnquiry);

            function setupDynamicLocation(selectId, inputId, inputName) {
                const $select = $(`#${selectId}`);
                const $input = $(`#${inputId}`);
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
            setupDynamicLocation('tehsil_select', 'tehsil_input', 'tehsil');
            setupDynamicLocation('district_select', 'district_input', 'district');
            setupDynamicLocation('state_select', 'state_input', 'city');

            function updateTerritory() {
                // Get value from select, or from input if 'OTHER' is selected
                let distVal = $('#district_select').val();
                if (distVal === 'OTHER') {
                    distVal = $('#district_input').val();
                }

                distVal = String(distVal || '').trim().toUpperCase();

                if (distVal) {
                    if (['BIKANER', 'CHURU', 'SUJANGARH'].includes(distVal)) {
                        $('#territory').val('OWN TERRITORY');
                    } else {
                        $('#territory').val('OTHER TERRITORY');
                    }
                }
            }

            $('#district_select').on('change', updateTerritory);
            $('#district_input').on('input', updateTerritory);

            $('#zipcode').on('input blur', debounce(function() {
                const pincode = ($('#zipcode').val() || '').trim();
                const $bpoSelect = $('#vpo_select');
                const $tehsilSelect = $('#tehsil_select');
                const $districtSelect = $('#district_select');
                const $citySelect = $('#state_select');
                const $territorySelect = $('#territory');

                if (pincode.length !== 6) {
                    $bpoSelect.html('<option value="">Select VPO</option>');
                    $tehsilSelect.html('<option value="">Select Tehsil</option>');
                    $districtSelect.html('<option value="">Select District</option>');
                    $citySelect.html('<option value="">Select State</option>');
                    return;
                }

                $bpoSelect.html('<option value="">Loading...</option>');
                $tehsilSelect.html('<option value="">Loading...</option>');
                $districtSelect.html('<option value="">Loading...</option>');
                $citySelect.html('<option value="">Loading...</option>');

                fetch(`https://api.postalpincode.in/pincode/${pincode}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data[0] && data[0].Status === 'Success') {
                            const postOffices = data[0].PostOffice;
                            let bpos = [],
                                tehsils = [],
                                districts = [],
                                cities = [];
                            postOffices.forEach(po => {
                                if (po.Name && !bpos.includes(po.Name)) bpos.push(po.Name);
                                let block = (po.Block && po.Block !== "NA") ? po.Block : po
                                    .District;
                                if (block && !tehsils.includes(block)) tehsils.push(block);
                                if (po.District && !districts.includes(po.District))
                                    districts.push(po.District);
                                if (po.State && !cities.includes(po.State)) cities.push(po
                                    .State);
                            });

                            const buildOptions = (arr, placeholder, currentValue) => {
                                let html = `<option value="">${placeholder}</option>`;
                                let valueFound = false;
                                let matchedValue = null;
                                let safeCurrent = (currentValue || '').toString().trim()
                                    .toLowerCase();

                                arr.forEach(val => {
                                    if (val.toString().trim().toLowerCase() ===
                                        safeCurrent) {
                                        valueFound = true;
                                        matchedValue = val;
                                    }
                                });

                                if (!valueFound && safeCurrent.length > 0) {
                                    arr.forEach(val => {
                                        let safeVal = val.toString().trim()
                                            .toLowerCase();
                                        if (safeVal.includes(safeCurrent) || safeCurrent
                                            .includes(safeVal)) {
                                            valueFound = true;
                                            matchedValue = val;
                                        }
                                    });
                                }

                                arr.forEach(val => {
                                    const selected = (valueFound && val ===
                                        matchedValue) ? 'selected' : '';
                                    html +=
                                        `<option value="${val}" ${selected}>${val}</option>`;
                                });

                                const otherSelected = (!valueFound && currentValue) ?
                                    'selected' : '';
                                html += `<option value="OTHER" ${otherSelected}>Other</option>`;
                                return {
                                    html,
                                    valueFound,
                                    matchedValue: (valueFound ? matchedValue : currentValue)
                                };
                            };

                            const handleRender = (selectId, inputId, inputName, optionsArr,
                                placeholder, currentValue) => {
                                const renderData = buildOptions(optionsArr, placeholder,
                                    currentValue);
                                $(`#${selectId}`).html(renderData.html);
                                if (!renderData.valueFound && currentValue) {
                                    $(`#${inputId}`).val(currentValue).removeClass('d-none')
                                        .attr('name', inputName);
                                    if ($(`#${selectId}`).prop('required')) $(`#${inputId}`)
                                        .prop('required', true);
                                    $(`#${selectId}`).removeAttr('name');
                                } else {
                                    $(`#${inputId}`).addClass('d-none').removeAttr('name');
                                    $(`#${selectId}`).attr('name', inputName);
                                }
                            };

                            handleRender('vpo_select', 'vpo_input', 'vpo', bpos, 'Select VPO',
                                currentEnquiry.vpo);
                            handleRender('tehsil_select', 'tehsil_input', 'tehsil', tehsils,
                                'Select Tehsil', currentEnquiry.tehsil);
                            handleRender('district_select', 'district_input', 'district', districts,
                                'Select District', currentEnquiry.district);
                            handleRender('state_select', 'state_input', 'city', cities,
                                'Select State', currentEnquiry.city);

                            // Trigger the district change to apply territory logic automatically
                            $('#district_select').trigger('change');

                            // Restore existing territory from DB if it was overridden manually before
                            if (currentEnquiry.territory && currentEnquiry.isEdit) {
                                setTimeout(() => {
                                    if ($('#territory').val() === '') {
                                        $('#territory').val(currentEnquiry.territory);
                                    }
                                }, 50);
                            }

                        } else {
                            $bpoSelect.html('<option value="">No VPO Found</option>');
                            $tehsilSelect.html('<option value="">No Tehsil Found</option>');
                            $districtSelect.html('<option value="">No District Found</option>');
                            $citySelect.html('<option value="">No State Found</option>');
                        }
                    })
                    .catch(error => {
                        $bpoSelect.html('<option value="">Select VPO</option>');
                        $tehsilSelect.html('<option value="">Select Tehsil</option>');
                        $districtSelect.html('<option value="">Select District</option>');
                        $citySelect.html('<option value="">Select State</option>');
                    });
            }));

            $('#enquiry_type').trigger('change');
            if (currentEnquiry.isEdit && currentEnquiry.dealerBranch) $('#dealer_branch').trigger('change');
            $segmentCode.trigger('change');
            if ($('#zipcode').val().length === 6) $('#zipcode').trigger('blur');
        });
    </script>
@endpush
