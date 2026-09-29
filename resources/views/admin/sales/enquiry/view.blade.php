{{-- =========================== ENQUIRY FORM (VIEW ONLY - DROPDOWNS REMOVED) =========================== --}}

@extends(backpack_view('blank'))

@section('title', 'View Enquiry')

@push('after_styles')
    <style>
        .enquiry-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 2rem;
            background: var(--tblr-card-bg);
        }

        .enquiry-card .card-header {
            background: var(--tblr-card-bg);
            border-bottom: 1px solid #edf2f9;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        /* --- GLOBAL READ-ONLY FREEZE LOGIC --- */
        .view-only-wrapper .form-control,
        .view-only-wrapper textarea,
        .view-only-wrapper input[type="radio"],
        .view-only-wrapper input[type="text"],
        .view-only-wrapper input[type="number"],
        .view-only-wrapper input[type="email"] {
            background-color: #e9ecef !important;
            color: #6c757d !important;
            pointer-events: none !important;
            cursor: not-allowed !important;
            opacity: 0.8 !important;
            border-color: #dee2e6 !important;
        }

        .manual-mismatch-container { display: none !important; }
        .table-responsive table { white-space: nowrap; }
    </style>
@endpush

@section('content')
    @php
        $isLong = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'LONG';
        $isQuick = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'QUICK';
        $isVirtual = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'VIRTUAL';
        $isReference = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'REFERENCE';
        $isWhatsapp = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'WHATSAPP';
        $isXceler8 = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'XCELER8';
        $callNatureText = strtoupper(trim(collect($call_nature_virtual ?? [])->firstWhere('code', $enquiry->call_nature ?? '')['value'] ?? ($enquiry->call_nature ?? '')));
        $isVirtualSales = $isVirtual && $callNatureText === 'SALES';

        $enqTypeStr = 'Xceler8 Enquiry';
        if ($isReference) $enqTypeStr = 'Reference Enquiry';
        elseif ($isVirtual) $enqTypeStr = 'Virtual Number Enquiry';
        elseif ($isWhatsapp) $enqTypeStr = 'WhatsApp Enquiry';
        elseif ($isLong) $enqTypeStr = 'Dms Long Enquiry';
        elseif ($isQuick) $enqTypeStr = 'Dms Quick Enquiry';
        elseif ($isXceler8) $enqTypeStr = 'Xceler8 Enquiry';

        $order = [
            'whatsapp' => 0, 'credentials' => 1, 'vehicle' => 2,
            'customer_primary' => 3, 'exchange' => 4, 'sc_detail' => 5,
        ];

        if ($isReference) {
            $order['credentials'] = 1; $order['customer_primary'] = 2; $order['vehicle'] = 3; $order['sc_detail'] = 4; $order['exchange'] = 5;
        } elseif ($isVirtual) {
            $order['customer_primary'] = 1; $order['sc_detail'] = 2; $order['credentials'] = 3; $order['vehicle'] = 4; $order['exchange'] = 5;
        } elseif ($isWhatsapp) {
            $order['whatsapp'] = 1; $order['customer_primary'] = 2; $order['vehicle'] = 3; $order['sc_detail'] = 4; $order['credentials'] = 5; $order['exchange'] = 6;
        }

        $devMap = collect($deviation_stages ?? [])->pluck('value', 'code')->toArray();
        $remMap = collect($sc_fup_remarks ?? [])->pluck('value', 'code')->toArray();
        $remTypeMap = collect($sc_fup_remarks_types ?? [])->pluck('value', 'code')->toArray();
        $enqStageMap = collect($enquiry_stages ?? [])->pluck('value', 'code')->toArray();
        $custStageMap = collect($customer_stages ?? [])->pluck('value', 'code')->toArray();
        $fupTypeMap = collect($follow_up_types ?? [])->pluck('value', 'code')->toArray();

        $oemScCode = $enquiry->sc_code ?? '';
        $oemScDisplay = ''; $oemScMileId = ''; $oemScBranch = ''; $oemScLocation = '';

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
        $creScDisplay = '—'; $creScMileId = '—'; $creScBranch = '—'; $creScLocation = '—';

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
        $creSegmentName = \App\Services\OrgService::segments()[$enquiry->segment_code] ?? ($enquiry->segment_code ?: '—');
        $creModelName = \App\Services\OrgService::models($enquiry->segment_code)[$enquiry->model_code] ?? ($enquiry->model_code ?: '—');
        $variantData = \App\Services\OrgService::variants($enquiry->model_code)[$enquiry->variant_code] ?? null;
        $creVariantName = $variantData['name'] ?? ($enquiry->variant_code ?: '—');
        $creColorName = \App\Services\OrgService::colors($enquiry->variant_code)[$enquiry->color_code] ?? ($enquiry->color_code ?: '—');

        // Derived Logic for dynamic sections
        $isCommercial = false;
        foreach(['LMM', 'COMMERCIAL', 'CV', 'HCV', 'LCV', 'SCV'] as $c) {
            if (str_contains(strtoupper($creSegmentName), $c)) { $isCommercial = true; break; }
        }
        
        $ptText = strtoupper(collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type_crm)['value'] ?? ($enquiry->purchase_type_crm ?? ''));
        $showAdditionalVehicle = str_contains($ptText, 'ADDITIONAL');

        // Backward compatibility for legacy Care Of IDs
        $careOfValue = $enquiry->care_of_type ?? '';
        if ($careOfValue == '1') $careOfValue = 'Son of';
        elseif ($careOfValue == '2') $careOfValue = 'Daughter of';
        elseif ($careOfValue == '3') $careOfValue = 'Married to';
        elseif ($careOfValue == '4') $careOfValue = 'Guardian Name';
        elseif ($careOfValue == '5') $careOfValue = 'Owned By';
        else $careOfValue = collect($care_of_types ?? [])->firstWhere('code', $careOfValue)['value'] ?? $careOfValue;

        if (isset($enquiry) && in_array(strtoupper($enquiry->current_origin ?? ''), ['LONG', 'QUICK'])) {
            $isQuick = strtoupper($enquiry->current_origin ?? '') === 'QUICK';
            $fmtDate = fn($d) => !empty($d) ? \Carbon\Carbon::parse($d)->format('d-M-Y') : '—';
            $fmtDateTime = fn($d) => !empty($d) ? \Carbon\Carbon::parse($d)->format('d-M-Y H:i') : '—';

            $comparisonRows = [
                ['label' => 'Enquiry Number', 'dump' => $isQuick ? ($enquiry->quick_enquiry_no ?: '—') : ($enquiry->enquiry_no ?: '—'), 'cre' => 'XENQ-' . $enquiry->id, 'skip_comparison' => true],
                ['label' => 'Enquiry Date', 'dump' => $fmtDate($isQuick ? $enquiry->quick_enquiry_date ?? '' : $enquiry->enquiry_date ?? ''), 'cre' => $fmtDateTime($enquiry->created_at), 'skip_comparison' => true],
                ['label' => 'Enquiry Assign Date', 'dump' => $fmtDate($isQuick ? $enquiry->quick_enq_assign_date ?? '' : $enquiry->enq_assign_date ?? ''), 'cre' => $fmtDate($enquiry->x8_enq_assign_date), 'skip_comparison' => true],
                ['label' => 'Booking Number', 'dump' => $enquiry->oem_booking_no ?? '—', 'cre' => $enquiry->x8_booking_no ?? ($enquiry->booking_no ?? '—')],
                ['label' => 'Booking Date', 'dump' => $fmtDate($enquiry->oem_booking_date ?? ''), 'cre' => $fmtDate($enquiry->x8_booking_date ?? ($enquiry->booking_date ?? ''))],
                ['label' => 'Booking Cancellation Date', 'dump' => $fmtDate($enquiry->oem_cancellation_date ?? ''), 'cre' => $fmtDate($enquiry->x8_cancellation_date ?? ($enquiry->cancellation_date ?? ''))],
                ['label' => 'Segment', 'dump' => $enquiry->segment ?: '—', 'cre' => $creSegmentName],
                ['label' => 'Model', 'dump' => $enquiry->model ?: '—', 'cre' => $creModelName],
                ['label' => 'Variant', 'dump' => $enquiry->variant ?: '—', 'cre' => $creVariantName],
                ['label' => 'Color', 'dump' => $enquiry->color ?: '—', 'cre' => $creColorName],
                ['label' => 'Likely Purchase in Days', 'dump' => collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry->likely_purchase_days)['value'] ?? ($enquiry->likely_purchase_days ?: '—'), 'cre' => collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry->cre_likely_purchase_days)['value'] ?? ($enquiry->cre_likely_purchase_days ?: '—')],
                ['label' => 'Contact Number', 'dump' => $enquiry->mobile ?: '—', 'cre' => $enquiry->mobile ?: '—'],
                ['label' => 'Purchase Type', 'dump' => collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type)['value'] ?? ($enquiry->purchase_type ?: '—'), 'cre' => collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type_crm)['value'] ?? ($enquiry->purchase_type_crm ?: '—')],
                ['label' => 'SC Name', 'dump' => !empty($oemScDisplay) ? $oemScDisplay : $enquiry->sc_code ?? '—', 'cre' => $creScDisplay ?: '—'],
                ['label' => 'SC Mile ID', 'dump' => !empty($oemScMileId) ? $oemScMileId : '—', 'cre' => $creScMileId ?: '—'],
                ['label' => 'Next Fup Date', 'dump' => $fmtDateTime($scNextDate), 'cre' => $fmtDateTime($creNextDate)],
                ['label' => 'Enquiry Stage', 'dump' => $enqStageMap[$enquiry->dms_enquiry_stage ?? ''] ?? ($enquiry->dms_enquiry_stage ?? ($enquiry->stage ?: '—')), 'cre' => $enqStageMap[$lastCre?->cre_enq_stage ?? ''] ?? ($lastCre?->cre_enq_stage ?: '—')],
                ['label' => 'Booking Cancellation Reason', 'dump' => $enquiry->oem_cancel_reason ?? '—', 'cre' => $enquiry->cancel_reason ?? '—'],
                ['label' => 'Booking Cancellation Remarks', 'dump' => $enquiry->oem_cancel_remarks ?? '—', 'cre' => $enquiry->cancel_remarks ?? '—'],
                ['label' => 'Latest Followup Remarks', 'dump' => $remMap[$enquiry->recent_fup_comments ?? ''] ?? ($enquiry->recent_fup_comments ?? ($enquiry->remarks ?: '—')), 'cre' => $lastCre?->cre_fup_remarks ?: '—'],
            ];

            if (!empty($enquiry->test_drive_no)) {
                $comparisonRows[] = ['label' => 'Test Drive Verification', 'dump' => '—', 'cre' => '—'];
            }

            $comparisonRows = array_filter($comparisonRows, function ($row) {
                $d = trim(strip_tags((string) $row['dump'])); $c = trim(strip_tags((string) $row['cre']));
                return !(in_array($d, ['—', '-', '']) && in_array($c, ['—', '-', '']));
            });
        }
    @endphp

    <div class="container-fluid pb-5">

        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h2 class="mb-0 fw-bold">
                View {{ $enqTypeStr }} : XENQ-{{ $enquiry->id }}
            </h2>
        </div>
        
        {{-- =========================== NEW DUPLICATE RECORDS TABLE (COMPACT VIEW) =========================== --}}
        @php
            $duplicateData = [];
            if (!empty($enquiry->duplicate)) {
                $duplicateData = json_decode($enquiry->duplicate, true);
                if (!is_array($duplicateData)) {
                    $duplicateData = [];
                }
            }
        @endphp

        @if (count($duplicateData) > 0)
            <div class="card enquiry-card mb-4">
                <div class="card-header py-2 px-3">
                    <h3 class="mb-0 fw-bold"> Duplicate Records History</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center align-middle mb-0" style="font-size: 0.8rem; background-color: var(--tblr-bg-surface-secondary);">
                            <thead class="table-secondary text-uppercase" style="font-size: 0.7rem;">
                                <tr>
                                    <th class="px-2 py-1" style="width: 5%;">S.NO.</th>
                                    <th class="px-2 py-1" style="width: 15%;">RECORDING DATE</th>
                                    <th class="px-2 py-1" style="width: 15%;">ENQUIRY DATE</th>
                                    <th class="px-2 py-1" style="width: 15%;">ENQUIRY TYPE</th>
                                    <th class="px-2 py-1" style="width: 15%;">PRODUCT FAMILY</th>
                                    <th class="px-2 py-1" style="width: 20%;">VARIANT DESCRIPTION</th>
                                    <th class="px-2 py-1" style="width: 15%;">COLOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($duplicateData as $index => $dup)
                                    @php 
                                        $isHidden = $index >= 5; 
                                    @endphp
                                    <tr class="{{ $isHidden ? 'hidden-duplicate-row d-none' : '' }}">
                                        <td class="fw-bold table-secondary text-dark px-2 py-1">{{ $index + 1 }}</td>
                                        <td class="bg-white px-2 py-1">{{ !empty($dup['recorded_at']) ? \Carbon\Carbon::parse($dup['recorded_at'])->format('d-M-Y H:i') : '—' }}</td>
                                        <td class="bg-white px-2 py-1">{{ !empty($dup['enquiry_date']) ? \Carbon\Carbon::parse($dup['enquiry_date'])->format('d-M-Y H:i') : '—' }}</td>
                                        <td class="bg-white text-uppercase px-2 py-1">{{ $dup['enquiry_type'] ?? '—' }}</td>
                                        <td class="bg-white px-2 py-1">{{ $dup['product_family'] ?? '—' }}</td>
                                        <td class="bg-white text-wrap px-2 py-1" style="min-width: 150px;">{{ $dup['variant_description'] ?? '—' }}</td>
                                        <td class="bg-white px-2 py-1">{{ $dup['color'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                
                                {{-- Compact Toggle Button --}}
                                @if (count($duplicateData) > 5)
                                    <tr id="toggleDuplicatesRow" style="background-color: var(--tblr-bg-surface-secondary); pointer-events: auto !important;">
                                        <td colspan="7" class="text-center py-1">
                                            <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted fw-bold py-0 px-2" style="font-size: 0.75rem;" id="toggleDuplicatesBtn">
                                                <i class="la la-angle-down"></i> Show All {{ count($duplicateData) }} Records
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        {{-- ================================================================================= --}}

        {{-- GLOBAL READ ONLY WRAPPER --}}
        <div class="view-only-wrapper">

            @if ($isVirtual)
                <div class="card enquiry-card">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Virtual Call Details</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Xceler8 Enquiry Number</label>
                                <input type="text" class="form-control" value="XENQ-{{ $enquiry->id }}">
                            </div>
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Xceler8 Enquiry Date</label>
                                <input type="text" class="form-control" value="{{ $enquiry->created_at ? $enquiry->created_at->format('d-m-Y H:i:s') : '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Virtual Number</label>
                                <input type="text" class="form-control" value="{{ $enquiry->virtual_no ?? '' }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Call Date</label>
                                <input type="text" class="form-control" value="{{ !empty($enquiry->virtual_call_date) ? \Carbon\Carbon::parse($enquiry->virtual_call_date)->format('d-M-Y H:i') : '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Call Duration</label>
                                <input type="text" class="form-control" value="{{ $enquiry->call_duration ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Customer Contact Number</label>
                                <input type="text" class="form-control" value="{{ $enquiry->mobile ?? '' }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Call Nature</label>
                                <input type="text" class="form-control" value="{{ collect($call_nature_virtual ?? [])->firstWhere('code', $enquiry->call_nature)['value'] ?? ($enquiry->call_nature ?? '') }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" rows="2">{{ $enquiry->remarks ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div id="full_enquiry_form" class="{{ ($isVirtual && !$isVirtualSales) ? 'd-none' : 'd-flex flex-column' }}">

                @if (!empty($comparisonRows))
                    <div class="card enquiry-card" style="order: -1;">
                        <div class="card-header"><h3 class="mb-0 fw-bold">Data Overview: CRE vs SC</h3></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center align-middle mb-0" style="background-color: var(--tblr-bg-surface-secondary);">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center p-3" style="width: 25%;">Parameters</th>
                                            <th class="text-center p-3" style="width: 30%;">Xceler8 Data</th>
                                            <th class="text-center p-3" style="width: 30%;">OEM Data ({{ $isQuick ? 'Quick' : 'Long' }})</th>
                                            <th class="text-center p-3" style="width: 15%;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($comparisonRows as $row)
                                            @php
                                                $d = trim(strip_tags((string) $row['dump'])); $c = trim(strip_tags((string) $row['cre']));
                                                $anyEmpty = in_array($d, ['—', '-', '']) || in_array($c, ['—', '-', '']);
                                                if (in_array($d, ['—', '-', ''])) $d = '';
                                                if (in_array($c, ['—', '-', ''])) $c = '';
                                                $isAutoMismatch = $d !== $c;
                                            @endphp
                                            <tr>
                                                <td class="fw-bold align-middle table-secondary text-start px-4 py-2 text-dark">{{ $row['label'] }}</td>
                                                <td class="align-middle p-2"><div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $row['cre'] }}</div></td>
                                                <td class="align-middle p-2"><div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $row['dump'] }}</div></td>
                                                <td class="align-middle p-2">
                                                    <div class="form-control bg-white h-auto border-0 d-flex justify-content-center align-items-center" style="min-height: 38px;">
                                                        @if ($anyEmpty || (isset($row['skip_comparison']) && $row['skip_comparison']))
                                                            <span class="text-secondary fw-bold" style="font-size: 1rem;">—</span>
                                                        @else
                                                            @if ($isAutoMismatch)
                                                                <span class="text-danger fw-bold d-flex align-items-center" style="font-size: 0.9rem;"><i class="la la-times-circle me-1" style="font-size: 1.2rem;"></i> Mismatch</span>
                                                            @else
                                                                <span class="text-success fw-bold d-flex align-items-center" style="font-size: 0.9rem;"><i class="la la-check-circle me-1" style="font-size: 1.2rem;"></i> Match</span>
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

                @if ($isWhatsapp)
                    <div class="card enquiry-card" style="order: {{ $order['whatsapp'] }};">
                        <div class="card-header"><h3 class="mb-0 fw-bold">WhatsApp Campaign Details</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-6">
                                    <label class="form-label">Xceler8 Enquiry Number</label>
                                    <input type="text" class="form-control" value="XENQ-{{ $enquiry->id }}">
                                </div>
                                <div class="col-md-6 mb-6">
                                    <label class="form-label">Xceler8 Enquiry Date</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->created_at ? $enquiry->created_at->format('d-m-Y H:i:s') : '' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Lead Date & Time</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y H:i') : '—' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Campaign Name</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_name ?? '—' }}">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Date</label>
                                    <input type="text" class="form-control" value="{{ !empty($enquiry->wapp_campaign_date) ? \Carbon\Carbon::parse($enquiry->wapp_campaign_date)->format('d-M-Y') : '—' }}">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Segment</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_segment ?? '—' }}">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Campaign Model</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_model ?? '—' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card enquiry-card" style="order: {{ $order['credentials'] }};">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Enquiry Credentials</h3></div>
                    <div class="card-body">
                        <div class="row">
                            @if ($isReference)
                                <div class="col-md-6 mb-6">
                                    <label class="form-label">Xceler8 Enquiry Number</label>
                                    <input type="text" class="form-control" value="XENQ-{{ $enquiry->id }}">
                                </div>
                                <div class="col-md-6 mb-6">
                                    <label class="form-label">Xceler8 Enquiry Date</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->created_at ? $enquiry->created_at->format('d-m-Y H:i:s') : '' }}">
                                </div>
                            @endif

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Origin</label>
                                <input type="text" class="form-control" value="{{ $enqTypeStr }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Type</label>
                                <input type="text" class="form-control" value="{{ collect($enquiry_types ?? [])->firstWhere('code', $enquiry->enquiry_type)['value'] ?? ($enquiry->enquiry_type ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Source</label>
                                <input type="text" class="form-control" value="{{ collect($enquiry_sources ?? [])->firstWhere('code', $enquiry->source_code)['value'] ?? ($enquiry->source?->name ?? ($enquiry->source_code ?? '')) }}">
                            </div>
                            @if(!$isLong)
                            <div class="col-md-3 mb-3">
                                <label class="form-label">DMS Enquiry Number</label>
                                <input type="text" class="form-control" value="{{ $enquiry->dms_enq_no ?? 'EN-' }}">
                            </div>
                            @endif

                            <div class="col-md-3 mb-3 {{ empty($enquiry->sub_source) ? 'd-none' : '' }}">
                                <label class="form-label">Enquiry Sub Source</label>
                                <input type="text" class="form-control" value="{{ collect($enquiry_sub_sources ?? [])->firstWhere('code', $enquiry->sub_source)['value'] ?? ($enquiry->sub_source ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3 {{ empty($enquiry->planned_campaign) ? 'd-none' : '' }}">
                                <label class="form-label">Planned Campaign</label>
                                <input type="text" class="form-control" value="{{ $enquiry->planned_campaign ?? '' }}">
                            </div>

                            <div class="row w-100 m-0 p-0 {{ $isReference ? 'd-flex' : 'd-none' }}">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label">Referee Type</label>
                                    <input type="text" class="form-control" value="{{ collect($referred_by_types ?? [])->firstWhere('code', $enquiry->referred_by)['value'] ?? ($enquiry->referred_by ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label">Referee Phone Number</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->referee_phone ?? '' }}">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label">Referee Name</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->referee_name ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card enquiry-card" style="order: {{ $order['vehicle'] }};">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Vehicle Details</h3></div>
                    <div class="card-body">
                        <div class="row">
                            @if ($isLong || $isQuick)
                                <div class="col-md-4 mb-4"><label class="form-label">Model Family</label><input type="text" class="form-control" value="{{ $enquiry->model }}"></div>
                                <div class="col-md-4 mb-4"><label class="form-label">Variant Family</label><input type="text" class="form-control" value="{{ $enquiry->variant }}"></div>
                                <div class="col-md-4 mb-4"><label class="form-label">Color Family</label><input type="text" class="form-control" value="{{ $enquiry->color }}"></div>
                            @endif

                            <div class="col-md-3 mb-3"><label class="form-label">Segment</label><input type="text" class="form-control" value="{{ $creSegmentName }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Model</label><input type="text" class="form-control" value="{{ $creModelName }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Variant</label><input type="text" class="form-control" value="{{ $creVariantName }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Color</label><input type="text" class="form-control" value="{{ $creColorName }}"></div>

                            <div class="col-md-3 mb-3"><label class="form-label">Fuel Type</label><input type="text" class="form-control" value="{{ $variantData['fuel_type'] ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Transmission</label><input type="text" class="form-control" value="{{ $variantData['transmission'] ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Drivetrain</label><input type="text" class="form-control" value="{{ $variantData['drivetrain'] ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Seating</label><input type="text" class="form-control" value="{{ $variantData['seating'] ?? '' }}"></div>

                            <div class="row w-100 m-0 p-0" style="{{ strtoupper($creSegmentName) === 'BEV' ? '' : 'display: none;' }}">
                                <div class="col-md-4 mb-3">
                                    <label>Do you have an EV?</label>
                                    <input type="text" class="form-control" value="{{ $enquiry->has_ev ?? 'No' }}">
                                </div>
                            </div>

                            <div class="row w-100 m-0 p-0" style="{{ $isCommercial ? '' : 'display:none;' }}">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Usage Area</label>
                                    <input type="text" class="form-control" value="{{ collect($usage_areas ?? [])->firstWhere('code', $enquiry->usage_area)['value'] ?? ($enquiry->usage_area ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">KM Travelled Daily</label>
                                    <input type="text" class="form-control" value="{{ collect($km_travelled_daily ?? [])->firstWhere('code', $enquiry->km_travelled_daily)['value'] ?? ($enquiry->km_travelled_daily ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application Type</label>
                                    <input type="text" class="form-control" value="{{ collect($application_types ?? [])->firstWhere('code', $enquiry->application_type)['value'] ?? ($enquiry->application_type ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application</label>
                                    <input type="text" class="form-control" value="{{ collect($applications ?? [])->firstWhere('code', $enquiry->application)['value'] ?? ($enquiry->application ?? '') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card enquiry-card" style="order: {{ $order['customer_primary'] }};">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Customer Primary Details</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5 mb-5"><label class="form-label">Customer Name</label><input type="text" class="form-control" value="{{ $enquiry->name ?? '' }}"></div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Care Of</label>
                                <input type="text" class="form-control" value="{{ $careOfValue }}">
                            </div>
                            <div class="col-md-5 mb-5"><label class="form-label">Care Of Name</label><input type="text" class="form-control uppercase" value="{{ $enquiry->care_of ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Contact Number</label><input type="text" class="form-control" value="{{ $enquiry->mobile ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Alternate Contact Number</label><input type="text" class="form-control" value="{{ $enquiry->alternate_mobile ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Email ID</label><input type="email" class="form-control" value="{{ $enquiry->email ?? '' }}"></div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender</label>
                                <input type="text" class="form-control" value="{{ collect($genders ?? [])->firstWhere('code', $enquiry->gender)['value'] ?? ($enquiry->gender ?? '') }}">
                            </div>

                            <div class="col-md-2 mb-2"><label class="form-label">Pin Code</label><input type="text" class="form-control" value="{{ $enquiry->zipcode ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">VPO</label><input type="text" class="form-control" value="{{ $enquiry->vpo ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">Tehsil</label><input type="text" class="form-control" value="{{ $enquiry->tehsil ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">District</label><input type="text" class="form-control" value="{{ $enquiry->district ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">State</label><input type="text" class="form-control" value="{{ $enquiry->city ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">Territory</label><input type="text" class="form-control" value="{{ $enquiry->territory ?? '' }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card enquiry-card" style="order: {{ $order['exchange'] }};">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Exchange & Finance</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <label class="form-label">Purchase Type (SC Input)</label>
                                <input type="text" class="form-control" value="{{ collect($purchase_types ?? [])->firstWhere('code', $enquiry?->purchase_type)['value'] ?? ($enquiry?->purchase_type ?? '—') }}">
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="form-label">Purchase Type (CRE Input)</label>
                                <input type="text" class="form-control" value="{{ collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type_crm)['value'] ?? ($enquiry->purchase_type_crm ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="form-label">Finance Mode</label>
                                <input type="text" class="form-control" value="{{ collect($finance_modes ?? [])->firstWhere('code', $enquiry->fin_mode)['value'] ?? ($enquiry->fin_mode ?? '') }}">
                            </div>

                            <div class="row w-100 m-0 p-0" style="{{ $showAdditionalVehicle ? '' : 'display:none;' }}">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Existing Brand</label>
                                    <input type="text" class="form-control" value="{{ collect($existing_car_oems ?? [])->firstWhere('code', $enquiry->brand_make)['value'] ?? ($enquiry->brand_make ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3"><label class="form-label">Existing Model</label><input type="text" class="form-control" value="{{ $enquiry->brand_model ?? '' }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Existing Vehicle Number</label><input type="text" class="form-control" value="{{ $enquiry->vehicle_no ?? '' }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Existing Make Year</label><input type="text" class="form-control" value="{{ $enquiry->make_year ?? '' }}"></div>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $financeData = \App\Models\Module\Finance\XFinance::where('enq_no', $enquiry->enquiry_no)->orWhere('bid', $enquiry->id)->first();
                    $financierName = '';
                    if($financeData && $financeData->financier) {
                        $fin = collect($financiers ?? [])->firstWhere('id', $financeData->financier);
                        $financierName = $fin ? $fin->name : '';
                    } elseif ($enquiry->financier) {
                        $fin = collect($financiers ?? [])->firstWhere('id', $enquiry->financier);
                        $financierName = $fin ? $fin->name : '';
                    }
                    $caseStatusMap = [1 => 'In-Process', 2 => 'Finance Done / Exchange Done', 3 => 'Case Lost'];
                    $instTypeMap = [1 => 'Financier Payment', 2 => 'Delivery Order', 3 => 'Sanction Letter'];
                    $caseLostMap = [1 => 'Cash Purchase', 2 => 'Customer Self Finance'];
                    $brandMakeName = '';
                    if($enquiry->brand_make) {
                        $bm = collect($existing_car_oems ?? [])->firstWhere('code', $enquiry->brand_make);
                        $brandMakeName = $bm ? $bm['value'] : $enquiry->brand_make;
                    }

                    $rawPurcType = strtoupper(trim($enquiry->purchase_type_crm ?? $enquiry->purchase_type ?? ''));
                    $isExchangeCase = str_contains($rawPurcType, 'EXCHANGE');
                    $isScrappageCase = str_contains($rawPurcType, 'SCRAPPAGE');
                    $showExchangeCard = $isExchangeCase || $isScrappageCase;
                    $exchangeHeading = $isScrappageCase ? 'Scrappage Details' : 'Exchange Details';
                    $hasFinanceData = $financeData || !empty($enquiry->fin_mode) || (isset($financeFups) && $financeFups->count() > 0);
                @endphp

                @if($showExchangeCard)
                <div class="card enquiry-card" style="order: {{ $order['exchange'] }}; margin-top: 1.5rem;">
                    <div class="card-header"><h3 class="mb-0 fw-bold">{{ $exchangeHeading }}</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Purchase Type</label><input type="text" class="form-control" value="{{ collect($purchase_types ?? [])->firstWhere('code', $enquiry->purchase_type_crm ?? $enquiry->purchase_type)['value'] ?? ($enquiry->purchase_type_crm ?? $enquiry->purchase_type ?? '—') }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Brand Make</label><input type="text" class="form-control" value="{{ $brandMakeName }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Brand Model</label><input type="text" class="form-control" value="{{ $enquiry->brand_model }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Vehicle Registration No.</label><input type="text" class="form-control" value="{{ $enquiry->vehicle_no }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Manufacturing Year</label><input type="text" class="form-control" value="{{ $enquiry->make_year }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Odometer Reading</label><input type="text" class="form-control" value="{{ $enquiry->odo_reading }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Expected Price</label><input type="text" class="form-control" value="{{ $enquiry->expected_price ? '₹ '.number_format($enquiry->expected_price) : '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Offered Price</label><input type="text" class="form-control" value="{{ $enquiry->offered_price ? '₹ '.number_format($enquiry->offered_price) : '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Exchange Bonus</label><input type="text" class="form-control" value="{{ $enquiry->exchange_bonus ? '₹ '.number_format($enquiry->exchange_bonus) : '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Price Gap</label>@php $diff = ($enquiry->expected_price ?? 0) - ($enquiry->offered_price ?? 0) - ($enquiry->exchange_bonus ?? 0); @endphp<input type="text" class="form-control" value="{{ $diff ? '₹ '.number_format($diff) : '' }}"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Reason for Case Lost (If Dropped)</label><input type="text" class="form-control" value="{{ $enquiry->lost_reason }}"></div>

                            @if(isset($exchangeFups) && $exchangeFups->count() > 0)
                            <div class="col-md-12 mt-4 mb-2">
                                <h5 class="fw-bold mb-3">Exchange Follow-up History</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center align-middle mb-0" style="background-color: var(--tblr-bg-surface-secondary);">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr><th class="text-center px-3" style="width: 10%;">Fup #</th><th class="text-center px-3" style="width: 45%;">Remarks</th><th class="text-center px-3" style="width: 25%;">Created By</th><th class="text-center px-3" style="width: 20%;">Date & Time</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach($exchangeFups as $fup)
                                                @php $creator = \App\Models\User::find($fup->created_by); $code = $creator ? ($creator->employee_code ?? $creator->person_code) : null; $creatorName = \App\Services\OrgService::getUserNameByCode($code); @endphp
                                                <tr><td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ $fup->fup_count }}</td><td><div class="form-control bg-white h-auto border-0 text-wrap text-start" style="min-width: 150px;">{{ $fup->remarks }}</div></td><td><div class="form-control bg-white h-auto border-0 text-center">{{ $creatorName }}</div></td><td><div class="form-control bg-white h-auto border-0 text-center">{{ \Carbon\Carbon::parse($fup->created_at)->format('d-M-Y h:i A') }}</div></td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                @if($hasFinanceData)
                <div class="card enquiry-card" style="order: {{ $order['exchange'] }}; margin-top: 1.5rem;">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Finance & Loan Details</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Finance Mode</label><input type="text" class="form-control" value="{{ $financeData->fin_mode ?? $enquiry->fin_mode ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Loan Status</label><input type="text" class="form-control" value="{{ $financeData->loan_status ?? $enquiry->loan_status ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Financier</label><input type="text" class="form-control" value="{{ $financierName }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Case Status</label><input type="text" class="form-control" value="{{ $caseStatusMap[$financeData->case_status ?? 1] ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Case Lost Reason</label><input type="text" class="form-control" value="{{ $caseLostMap[$financeData->case_lost_reason ?? ''] ?? ($financeData->case_lost_reason ?? '') }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Instrument Type</label><input type="text" class="form-control" value="{{ $instTypeMap[$financeData->instrument_type ?? ''] ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Reference No.</label><input type="text" class="form-control" value="{{ $financeData->instrument_ref_no ?? '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Loan Amount</label><input type="text" class="form-control" value="{{ !empty($financeData->loan_amount) ? '₹ '.number_format($financeData->loan_amount) : '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Margin Money</label><input type="text" class="form-control" value="{{ !empty($financeData->margin) ? '₹ '.number_format($financeData->margin) : '' }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">File Charge</label><input type="text" class="form-control" value="{{ !empty($financeData->file_charge) ? '₹ '.number_format($financeData->file_charge) : '' }}"></div>

                            @if(isset($financeFups) && $financeFups->count() > 0)
                            <div class="col-md-12 mt-4 mb-2">
                                <h5 class="fw-bold mb-3">Finance Follow-up History</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center align-middle mb-0" style="background-color: var(--tblr-bg-surface-secondary);">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr><th class="text-center px-3" style="width: 10%;">Fup #</th><th class="text-center px-3" style="width: 45%;">Remarks</th><th class="text-center px-3" style="width: 25%;">Created By</th><th class="text-center px-3" style="width: 20%;">Date & Time</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach($financeFups as $fup)
                                                @php $creator = \App\Models\User::find($fup->created_by); $code = $creator ? ($creator->employee_code ?? $creator->person_code) : null; $creatorName = \App\Services\OrgService::getUserNameByCode($code); @endphp
                                                <tr><td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ $fup->fup_count }}</td><td><div class="form-control bg-white h-auto border-0 text-wrap text-start" style="min-width: 150px;">{{ $fup->remarks }}</div></td><td><div class="form-control bg-white h-auto border-0 text-center">{{ $creatorName }}</div></td><td><div class="form-control bg-white h-auto border-0 text-center">{{ \Carbon\Carbon::parse($fup->created_at)->format('d-M-Y h:i A') }}</div></td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <div class="card enquiry-card" style="order: {{ $order['sc_detail'] }};">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Sales Consultant Details</h3></div>
                    <div class="card-body">
                        @if (!$isReference && !$isVirtual && !$isWhatsapp)
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3"><label class="form-label">OEM Assigned SC</label><input type="text" class="form-control" value="{{ $oemScDisplay }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">OEM Assigned SC Mile ID</label><input type="text" class="form-control" value="{{ $oemScMileId }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">OEM Assigned SC Branch</label><input type="text" class="form-control" value="{{ $oemScBranch }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">OEM Assigned SC Location</label><input type="text" class="form-control" value="{{ $oemScLocation }}"></div>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC</label>
                                <input type="text" class="form-control" value="{{ $creScDisplay }}">
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">X8 Assigned SC Mile ID</label><input type="text" class="form-control" value="{{ $enquiry->x8_sc_mile_id ?? $creScMileId }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">X8 Assigned SC Branch</label><input type="text" class="form-control" value="{{ $creScBranch }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">X8 Assigned SC Location</label><input type="text" class="form-control" value="{{ $creScLocation }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card enquiry-card" style="order: 6;">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Customer Secondary Details</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">D.O.B.</label><input type="text" class="form-control" value="{{ $enquiry->dob ? \Carbon\Carbon::parse($enquiry->dob)->format('d-M-Y') : '' }}"></div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age Group</label>
                                <input type="text" class="form-control" value="{{ collect($age_groups ?? [])->firstWhere('code', $enquiry->age_group)['value'] ?? ($enquiry->age_group ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Marital Status</label>
                                <input type="text" class="form-control" value="{{ collect($marital_statuses ?? [])->firstWhere('code', $enquiry->marital_status)['value'] ?? ($enquiry->marital_status ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Date of Marriage</label><input type="text" class="form-control" value="{{ $enquiry->marriage_date ?? '' }}"></div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Type</label>
                                <input type="text" class="form-control" value="{{ collect($occupation_types ?? [])->firstWhere('code', $enquiry->occupation_type)['value'] ?? ($enquiry->occupation_type ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Type</label>
                                <input type="text" class="form-control" value="{{ collect($customer_types ?? [])->firstWhere('code', $enquiry->customer_type)['value'] ?? ($enquiry->customer_type ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Sub Type</label>
                                <input type="text" class="form-control" value="{{ collect($occupation_sub_types ?? [])->firstWhere('code', $enquiry->occupation_sub_type)['value'] ?? ($enquiry->occupation_sub_type ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Company Name</label><input type="text" class="form-control" value="{{ $enquiry->company_name ?? '' }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card enquiry-card" style="order: 7;">
                    <div class="card-header"><h3 class="mb-0 fw-bold">Consideration Set</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 1 - Brand</label>
                                <input type="text" class="form-control" value="{{ collect($existing_car_oems ?? [])->firstWhere('code', $enquiry->consid_brand)['value'] ?? ($enquiry->consid_brand ?? 'No Consideration') }}">
                            </div>
                            <div class="col-md-4 mb-3"><label class="form-label">Consideration Set 1 - Model</label><input type="text" class="form-control" value="{{ $enquiry->consid_model ?? '' }}"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Consideration Set 1 - Variant</label><input type="text" class="form-control" value="{{ $enquiry->consid_variant ?? '' }}"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set 2 - Brand</label>
                                <input type="text" class="form-control" value="{{ collect($existing_car_oems ?? [])->firstWhere('code', $enquiry->consid_brand2)['value'] ?? ($enquiry->consid_brand2 ?? 'No Consideration') }}">
                            </div>
                            <div class="col-md-4 mb-3"><label class="form-label">Consideration Set 2 - Model</label><input type="text" class="form-control" value="{{ $enquiry->consid_model2 ?? '' }}"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Consideration Set 2 - Variant</label><input type="text" class="form-control" value="{{ $enquiry->consid_variant2 ?? '' }}"></div>
                        </div>
                    </div>
                </div>

                @php $currentOrigin = strtoupper($enquiry->current_origin ?? ''); @endphp

                @if ($currentOrigin === 'LONG' || $currentOrigin === 'QUICK')
                    <div class="card enquiry-card" style="order: 8;">
                        <div class="card-header"><h3 class="mb-0 fw-bold">SC Follow Up Details</h3></div>
                        <div class="card-body">
                            @if ($currentOrigin === 'LONG')
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered text-center align-middle mb-0" style="background-color: var(--tblr-bg-surface-secondary);">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr><th class="text-center px-3">Fup Count</th><th class="text-center px-3">Type</th><th class="text-center px-3">Status</th><th class="text-center px-3">Planned Date</th><th class="text-center px-3">Actual Date</th><th class="text-center px-3">Duration</th><th class="text-center px-3">Deviation</th><th class="text-center px-3">Enquiry Status</th><th class="text-center px-3">Remark Type</th><th class="text-center px-3">Comments</th></tr>
                                        </thead>
                                        <tbody>
                                            @if (isset($fups) && count($fups) > 0)
                                                @php $fupCount = count($fups); @endphp
                                                @foreach ($fups as $index => $fup)
                                                    @php $isHidden = ($fupCount > 4 && $index > 0 && $index < $fupCount - 3); @endphp
                                                    <tr class="{{ $isHidden ? 'hidden-fup-row d-none' : '' }}">
                                                        <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }} Fup</td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $fupTypeMap[$fup->followup_type ?? ''] ?? ($fup->followup_type ?? '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $fup->followup_status ?? '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ !empty($fup->planned_followup_date) ? \Carbon\Carbon::parse($fup->planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ !empty($fup->actual_followup_date) ? \Carbon\Carbon::parse($fup->actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $fup->call_duration ?? '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $devMap[$fup->deviation_stage ?? ''] ?? ($fup->deviation_stage ?? '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 120px;">{{ $enqStageMap[$fup->enquiry_status ?? ''] ?? ($fup->enquiry_status ?? '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 120px;">{{ $remTypeMap[$fup->remark_type ?? ''] ?? ($fup->remark_type ?? '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $fup->comments ?? '—' }}</div></td>
                                                    </tr>
                                                    @if ($fupCount > 4 && $index == 0)
                                                        <tr id="toggleFupsRow" style="background-color: var(--tblr-bg-surface-secondary); pointer-events: auto !important;">
                                                            <td colspan="10" class="text-center py-2">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" id="toggleFupsBtn"><i class="la la-angle-down"></i> Show {{ $fupCount - 4 }} More FUPs</button>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            @else
                                                <tr><td colspan="10" class="text-muted py-3 bg-white text-center">No Follow-up Data Found</td></tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            @elseif ($currentOrigin === 'QUICK')
                                @php
                                    $tatMins = '—';
                                    if (!empty($enquiry?->first_planned_followup_date) && !empty($enquiry?->first_actual_followup_date)) {
                                        $pDate = \Carbon\Carbon::parse($enquiry->first_planned_followup_date);
                                        $aDate = \Carbon\Carbon::parse($enquiry->first_actual_followup_date);
                                        $tatMins = abs($pDate->diffInMinutes($aDate));
                                    }
                                @endphp
                                <div class="row mb-4">
                                    <div class="col-md-3 mb-3"><label class="form-label">Enquiry Status</label><input type="text" class="form-control" value="{{ $enqStageMap[$enquiry?->quick_status ?? ''] ?? ($enquiry?->quick_status ?? ($enquiry?->stage ?? '—')) }}"></div>
                                    <div class="col-md-2 mb-3"><label class="form-label">Fup Count</label><input type="text" class="form-control" value="{{ $enquiry?->fup_count ?? '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">1st Plan Fup Date</label><input type="text" class="form-control" value="{{ !empty($enquiry?->first_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->first_planned_followup_date)->format('d-M-Y H:i') : '—' }}"></div>
                                    <div class="col-md-4 mb-3"><label class="form-label">1st Act Fup Date</label><input type="text" class="form-control" value="{{ !empty($enquiry?->first_actual_followup_date) ? \Carbon\Carbon::parse($enquiry->first_actual_followup_date)->format('d-M-Y H:i') : '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">TAT (In Mins)</label><input type="text" class="form-control" value="{{ $tatMins }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Recent Plan Fup Date</label><input type="text" class="form-control" value="{{ !empty($enquiry?->recent_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->recent_planned_followup_date)->format('d-M-Y H:i') : '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Recent Act Fup Date</label><input type="text" class="form-control" value="{{ !empty($enquiry?->recent_actual_followup_date) ? \Carbon\Carbon::parse($enquiry->recent_actual_followup_date)->format('d-M-Y H:i') : '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Next Fup Date</label><input type="text" class="form-control" value="{{ !empty($enquiry?->next_fup_date) ? \Carbon\Carbon::parse($enquiry->next_fup_date)->format('d-M-Y H:i') : (!empty($enquiry?->next_planned_followup_date) ? \Carbon\Carbon::parse($enquiry->next_planned_followup_date)->format('d-M-Y H:i') : '—') }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Recent Fup Type</label><input type="text" class="form-control" value="{{ $fupTypeMap[$enquiry?->followup_type ?? ''] ?? ($enquiry?->followup_type ?: '—') }}"></div>
                                    <div class="col-md-4 mb-3"><label class="form-label">1st Fup Remarks</label><input type="text" class="form-control" value="{{ $remMap[$enquiry?->first_fup_remarks ?? ''] ?? ($enquiry?->first_fup_remarks ?: '—') }}"></div>
                                    <div class="col-md-5 mb-3"><label class="form-label">Recent Fup Remarks</label><input type="text" class="form-control" value="{{ $remTypeMap[$enquiry?->followup_remarks_type ?? ''] ?? ($enquiry?->followup_remarks_type ?: '—') }}"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if (strtoupper($enquiry->current_origin ?? '') !== 'QUICK')
                        <div class="card enquiry-card" style="order: 9;">
                            <div class="card-header"><h4 class="mb-0 fw-bold">SC Enquiry Stage</h4></div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-3 mb-3"><label class="form-label">Enquiry Stage</label><input type="text" class="form-control" value="{{ $enqStageMap[$enquiry?->dms_enquiry_stage ?? ''] ?? ($enquiry?->dms_enquiry_stage ?? ($enquiry?->stage ?? '—')) }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive Count</label><input type="text" class="form-control" value="{{ $enquiry?->td_count ?? '0' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive Number</label><input type="text" class="form-control" value="{{ $enquiry?->test_drive_no ?? '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Test Drive Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && $enquiry->td_date ? \Carbon\Carbon::parse($enquiry->td_date)->format('d-M-Y') : '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Booking Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && ($enquiry->x8_booking_date || $enquiry->booking_date) ? \Carbon\Carbon::parse($enquiry->x8_booking_date ?? $enquiry->booking_date)->format('d-M-Y') : '—' }}"></div>
                                    <div class="col-md-3 mb-3"><label class="form-label">Likely Purchase In Days </label><input type="text" class="form-control" value="{{ collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry?->likely_purchase_days)['value'] ?? ($enquiry?->likely_purchase_days ?? '—') }}"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="card enquiry-card" style="order: 10;">
                    <div class="card-header"><h3 class="mb-0 fw-bold">CRE Enquiry Stage</h3></div>
                    <div class="card-body">
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle mb-0" style="background-color: var(--tblr-bg-surface-secondary);">
                                <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                    <tr><th class="text-center px-3">Fup Count</th><th class="text-center px-3">Planned Date</th><th class="text-center px-3">Actual Date</th><th class="text-center px-3">Deviation Stage</th><th class="text-center px-3">Customer Stage</th><th class="text-center px-3">Enquiry Stage</th><th class="text-center px-3">Remarks</th></tr>
                                </thead>
                                <tbody>
                                    @if (isset($creFups) && count($creFups) > 0)
                                        @php $creFupCount = count($creFups); @endphp
                                        @foreach ($creFups as $index => $cre)
                                            @php $isHidden = ($creFupCount > 4 && $index > 0 && $index < $creFupCount - 3); @endphp
                                            <tr class="{{ $isHidden ? 'hidden-cre-fup-row d-none' : '' }}">
                                                <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }} Fup</td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_planned_fup_date ? \Carbon\Carbon::parse($cre->cre_planned_fup_date)->format('d-M-Y H:i') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_actual_fup_date ? \Carbon\Carbon::parse($cre->cre_actual_fup_date)->format('d-M-Y H:i') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $devMap[$cre?->cre_fup_deviation_stage ?? ''] ?? ($cre?->cre_fup_deviation_stage ?: '—') }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $custStageMap[$cre?->cre_customer_stage ?? ''] ?? ($cre?->cre_customer_stage ?: '—') }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $enqStageMap[$cre?->cre_enq_stage ?? ''] ?? ($cre?->cre_enq_stage ?: '—') }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $cre?->cre_fup_remarks ?: '—' }}</div></td>
                                            </tr>
                                            @if ($creFupCount > 4 && $index == 0)
                                                <tr id="toggleCreFupsRow" style="background-color: var(--tblr-bg-surface-secondary); pointer-events: auto !important;">
                                                    <td colspan="7" class="text-center py-2">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" id="toggleCreFupsBtn"><i class="la la-angle-down"></i> Show {{ $creFupCount - 4 }} More FUPs</button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        @php $lastCreFup = is_array($creFups) ? end($creFups) : $creFups->last(); @endphp
                                        @if ($lastCreFup && $lastCreFup->cre_next_fup_date && !in_array(strtoupper($lastCreFup->cre_enq_stage), ['LOST', 'DROPPED']))
                                            <tr>
                                                <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$creFupCount] ?? $creFupCount + 1 . 'th' }} Fup</td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ \Carbon\Carbon::parse($lastCreFup->cre_next_fup_date)->format('d-M-Y H:i') }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">—</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center text-dark">Open Follow Up</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">—</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">—</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">—</div></td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr><td colspan="7" class="text-muted py-3 bg-white text-center">No CRE Follow-up Data Found</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-2 mb-2"><label class="form-label">Likely Purchase Date</label><input type="text" class="form-control" value="{{ !empty($enquiry->cre_likely_purchase_date) ? \Carbon\Carbon::parse($enquiry->cre_likely_purchase_date)->format('d-M-Y') : '' }}"></div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Likely Purchase In Days</label>
                                <input type="text" class="form-control" value="{{ collect($likely_purchase_dates ?? [])->firstWhere('code', $enquiry->cre_likely_purchase_days)['value'] ?? ($enquiry->cre_likely_purchase_days ?? '') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Customer Stage</label>
                                <input type="text" class="form-control" value="{{ collect($customer_stages ?? [])->firstWhere('code', $enquiry->cre_customer_stage)['value'] ?? ($enquiry->cre_customer_stage ?? '') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Enquiry Stage</label>
                                <input type="text" class="form-control" value="{{ collect($enquiry_stages ?? [])->firstWhere('code', $enquiry->cre_enq_stage)['value'] ?? ($enquiry->cre_enq_stage ?? '') }}">
                            </div>
                            <div class="col-md-2 mb-2"><label class="form-label">Next Fup Date</label><input type="text" class="form-control" value="{{ $enquiry->cre_next_fup_date ?? '' }}"></div>
                            <div class="col-md-2 mb-2"><label class="form-label">CRE Followup Remarks</label><textarea class="form-control" rows="1">{{ $enquiry->cre_fup_remarks ?? '' }}</textarea></div>
                            {{-- NEW: CONDITIONAL LOST REASONS --}}
                            <div class="col-md-2 mb-2 cre-lost-fields" style="{{ strtoupper(trim($enquiry->cre_customer_stage ?? '')) === 'LOST' ? '' : 'display: none;' }}">
                                <label class="form-label">Lost Reason</label>
                                <input type="text" class="form-control" value="{{ collect($lost_reasons ?? [])->firstWhere('code', $enquiry->lost_reason)['value'] ?? ($enquiry->lost_reason ?? '') }}">
                            </div>
                            <div class="col-md-2 mb-2 cre-lost-fields" style="{{ strtoupper(trim($enquiry->cre_customer_stage ?? '')) === 'LOST' ? '' : 'display: none;' }}">
                                <label class="form-label">Lost Sub Reason</label>
                                <input type="text" class="form-control" value="{{ collect($lost_sub_reasons ?? [])->firstWhere('code', $enquiry->lost_sub_reason)['value'] ?? ($enquiry->lost_sub_reason ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                @if (!$isVirtual)
                    <div class="card enquiry-card" style="order: 12;">
                        <div class="card-header"><h3 class="mb-0 fw-bold">Remarks</h3></div>
                        <div class="card-body">
                            <div class="row"><div class="col-md-12 mb-3"><textarea rows="3" class="form-control">{{ $enquiry?->remarks ?? '' }}</textarea></div></div>
                        </div>
                    </div>
                @endif
            </div>

        </div> 
        {{-- END VIEW ONLY WRAPPER --}}

        <div class="d-flex justify-content-center mt-3 mb-5">
            <a href="{{ url()->previous() }}" class="btn btn-secondary btn-lg px-5 py-2 shadow-sm fw-bold">
                <i class="la la-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        
        $(function() {
            $(document).on('click', '#toggleDuplicatesBtn', function(e) {
                e.preventDefault();
                const $hiddenRows = $('.hidden-duplicate-row');
                if ($hiddenRows.hasClass('d-none')) { 
                    $hiddenRows.removeClass('d-none'); 
                    $(this).html('<i class="la la-angle-up"></i> Hide Records'); 
                } else { 
                    $hiddenRows.addClass('d-none'); 
                    $(this).html('<i class="la la-angle-down"></i> Show All ' + ($hiddenRows.length + 5) + ' Records'); 
                }
            });

            // Only keep the pure interactive buttons alive
            $('#toggleFupsBtn').on('click', function() {
                const $hiddenRows = $('.hidden-fup-row');
                if ($hiddenRows.hasClass('d-none')) { $hiddenRows.removeClass('d-none'); $(this).html('<i class="la la-angle-up"></i> Hide FUPs'); } 
                else { $hiddenRows.addClass('d-none'); $(this).html('<i class="la la-angle-down"></i> Show ' + $hiddenRows.length + ' More FUPs'); }
            });

            $('#toggleCreFupsBtn').on('click', function() {
                const $hiddenRows = $('.hidden-cre-fup-row');
                if ($hiddenRows.hasClass('d-none')) { $hiddenRows.removeClass('d-none'); $(this).html('<i class="la la-angle-up"></i> Hide FUPs'); } 
                else { $hiddenRows.addClass('d-none'); $(this).html('<i class="la la-angle-down"></i> Show ' + $hiddenRows.length + ' More FUPs'); }
            });

            // Force all inputs to be un-tabbable completely
            $('.view-only-wrapper').find('input, select, textarea').attr('tabindex', '-1').prop('readonly', true);
        });
    </script>
@endpush