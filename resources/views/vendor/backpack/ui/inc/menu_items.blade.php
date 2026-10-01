<style>
    .sidebar .dropdown-toggle::after {
        margin-left: auto !important;
    }
</style>
{{-- MAIN ADMIN MENU - ALL ITEMS NESTED --}}
<x-backpack::menu-dropdown title="Admin" icon="la la-th">

    {{-- Dashboard --}}
    <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('dashboard') }}">
        <span><i class="la la-home me-2"></i>Dashboard</span>
    </a>

    {{-- Separator --}}
    <x-backpack::menu-separator title="Configuration" />

    {{-- Utilities Section --}}
    @if (backpack_user() && backpack_user()->can('UTL_SETTINGS_VIEW'))
        <x-backpack::menu-dropdown title="Utilities" icon="la la-wrench" nested="true">
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('utils/keyword-master') }}">
                <span><i class="la la-tag me-2"></i>Keyword Master</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('utils/key-value') }}">
                <span><i class="la la-key me-2"></i>Key Values</span>
            </a>
        </x-backpack::menu-dropdown>
    @endif

    {{-- Foundation Section --}}
    <x-backpack::menu-dropdown title="Foundation" icon="la la-building" nested="true">
        @if (backpack_user() && backpack_user()->can('ORG_ENTITY_MANAGE'))
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/branch') }}">
                <span><i class="la la-code-branch me-2"></i>Branch</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/location') }}">
                <span><i class="la la-map-marker me-2"></i>Location</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/department') }}">
                <span><i class="la la-layer-group me-2"></i>Department</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/division') }}">
                <span><i class="la la-layer-group me-2"></i>Division</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/designation') }}">
                <span><i class="la la-id-badge me-2"></i>Designation</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('org/vertical') }}">
                <span><i class="la la-bars me-2"></i>Vertical</span>
            </a>
        @endif

        {{-- IAM: Post & Org Structure --}}
        <!--
        <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('post') }}">
            <span><i class="la la-briefcase me-2"></i>Posts</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('desig-dept-tree') }}">
            <span><i class="la la-sitemap me-2"></i>Designation Dept Tree</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('post-reporting') }}">
            <span><i class="la la-project-diagram me-2"></i>Post Reporting Lines</span>
        </a>
        --!>
    </x-backpack::menu-dropdown>

    {{-- Vehicles Info Section --}}
    @if (backpack_user() &&
            (backpack_user()->can('VEH_BRND_VIEW') ||
                backpack_user()->can('VEH_SEG_VIEW') ||
                backpack_user()->can('VEH_MDL_VIEW') ||
                backpack_user()->can('VEH_VAR_VIEW') ||
                backpack_user()->can('VEH_CLR_VIEW')))
<x-backpack::menu-dropdown title="Vehicles Info" icon="la la-car" nested="true">
        <!--
        @if (backpack_user() && backpack_user()->can('VEH_BRND_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('vehicle/brand') }}">
                <span><i class="la la-trademark me-2"></i>Brand</span>
            </a>
@endif
        --!>
        @if (backpack_user() && backpack_user()->can('VEH_SEG_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('vehicle/segment') }}">
                <span><i class="la la-th-large me-2"></i>Segment</span>
            </a>

            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('vehicle/sub-segment') }}">
                <span><i class="la la-list me-2"></i>Sub Segment</span>
            </a>
@endif
        @if (backpack_user() && backpack_user()->can('VEH_MDL_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('vehicle/model') }}">
                <span><i class="la la-cube me-2"></i>Vehicle Model</span>
            </a>
@endif
        @if (backpack_user() && backpack_user()->can('VEH_VAR_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('vehicle/variant') }}">
                <span><i class="la la-clone me-2"></i>Variant</span>
            </a>
@endif
        <!--
        @if (backpack_user() && backpack_user()->can('VEH_CLR_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('vehicle/color') }}">
                <span><i class="la la-palette me-2"></i>Color</span>
            </a>
@endif
        --!>
    </x-backpack::menu-dropdown>
@endif

    {{-- Separator --}}
    <x-backpack::menu-separator title="Users & Organization" />

    {{-- Users Info Section --}}
    <x-backpack::menu-dropdown title="Users Info" icon="la la-users" nested="true">
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('user-type') }}">
            <span><i class="la la-user-tag me-2"></i>User Type</span>
        </a>
        @if (backpack_user() && backpack_user()->can('ORG_PRSN_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('org/person') }}">
                <span><i class="la la-user-circle me-2"></i>Person</span>
            </a>
@endif
        <!--
        <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('garage') }}">
            <span><i class="la la-warehouse me-2"></i>Garage</span>
        </a>
        --!>
        @if (backpack_user() && backpack_user()->can('ORG_USER_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('org/user') }}">
                <span><i class="la la-user me-2"></i>User</span>
            </a>
@endif

        {{-- 4-LEVEL NESTED: Employee Info --}}
        <!--
        <x-backpack::menu-dropdown title="Employee Info" icon="la la-sitemap" nested="true">
            @if (backpack_user() && backpack_user()->can('ORG_EMPL_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('org/employee') }}">
                    <span><i class="la la-user-tie me-2"></i>Employee</span>
                </a>
@endif
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('employee-department-assignment') }}">
                <span><i class="la la-link me-2"></i>Employee Dept Assignment</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('employee-branch-assignment') }}">
                <span><i class="la la-link me-2"></i>Employee Branch Assignment</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('employee-location-assignment') }}">
                <span><i class="la la-link me-2"></i>Employee Location Assignment</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('employee-vertical-assignment') }}">
                <span><i class="la la-link me-2"></i>Employee Vertical Assignment</span>
            </a>
        </x-backpack::menu-dropdown>
        --!>
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <!--
    <x-backpack::menu-separator title="HR Journey" />

    {{-- HR Section --}}
    <x-backpack::menu-dropdown title="HR Operations" icon="la la-user-clock" nested="true">
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('emp-post-assignment') }}">
            <span><i class="la la-briefcase me-2"></i>Post Assignments</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('hr/transfer') }}">
            <span><i class="la la-exchange-alt me-2"></i>Transfer Employee</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('hr/relieve') }}">
            <span><i class="la la-sign-out-alt me-2"></i>Relieve Employee</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('hr/journey') }}">
            <span><i class="la la-history me-2"></i>Employee Journey</span>
        </a>
    </x-backpack::menu-dropdown>
    --!>


    {{-- Separator --}}
    <x-backpack::menu-separator title="Access Control" />

    {{-- RBAC Section --}}
    <x-backpack::menu-dropdown title="RBAC" icon="la la-lock" nested="true">
        @if (backpack_user() && backpack_user()->can('IAM_RBAC_VIEW'))
<a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('iam/module') }}">
                <span><i class="la la-cube me-2"></i>Modules</span>
            </a>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('iam/process') }}">
                <span><i class="la la-cogs me-2"></i>Process</span>
            </a>
            <!--
            <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ backpack_url('iam/role') }}">
                <span><i class="la la-users me-2"></i>Role</span>
            </a>
            --!>
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('iam/permission') }}">
                <span><i class="la la-key me-2"></i>Permission</span>
            </a>
@endif
        {{-- "Post Permission" and "Approval Hierarchy" below are unrelated to the 4 IAM entities
            gated above (PostPermissionCrudController is confirmed dead/unreachable per BUG-024;
            ApprovalHierarchyCrudController is permanently locked, never touched by this rollout). --}}
        <!--
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('post-permission') }}">
            <span><i class="la la-check-circle me-2"></i>Post Permission</span>
        </a>
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('approval-hierarchy') }}">
            <span><i class="la la-shield-alt me-2"></i>Approval Hierarchy</span>
        </a>
        --!>
    </x-backpack::menu-dropdown>

</x-backpack::menu-dropdown>


<!-- @if (backpack_user() && backpack_user()->can('ORG_USER_VIEW'))
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('org/user')" />
@endif -->




        {{-- ========================================================================= --}}
        {{-- FETCH ENQUIRY COUNTS (Cached for 60 seconds to prevent slow page loads) --}}
        {{-- ========================================================================= --}}
        @php
            $enqCounts = \Illuminate\Support\Facades\Cache::remember('menu_enquiry_counts', 60, function () {
                return [
                    'all' => \App\Models\CRM\Enquiry::mainListing()->count(),
                    'xceler8' => \App\Models\CRM\Enquiry::xceler8()->count(),
                    'reference' => \App\Models\CRM\Enquiry::reference()->count(),
                    'virtual' => \App\Models\CRM\Enquiry::virtual()->count(),
                    'whatsapp' => \App\Models\CRM\Enquiry::whatsapp()->count(),
                    'hyperlocal' => \App\Models\CRM\Enquiry::hyperlocal()->count(),
                    'unassigned_quick' => \App\Models\CRM\Enquiry::unassignedQuick()->count(),
                    'assigned_quick' => \App\Models\CRM\Enquiry::assignedQuick()->count(),
                    'unassigned_long' => \App\Models\CRM\Enquiry::unassignedLong()->count(),
                    'assigned_long' => \App\Models\CRM\Enquiry::assignedLong()->count(),
                    'duplicate' => \App\Models\CRM\Enquiry::whereNotNull('duplicate')->where('is_active', 1)->count(),

                    // 1. CRM Sales > Verification > Lost Enquiries
                    'lost' => \App\Models\CRM\Enquiry::where('is_active', 3)->count(),

                    // 2. Exchange Enquiries (Fixed Bracket Logic)
                    'exch_interest' => \App\Models\CRM\Enquiry::where('is_active', 1)
                        ->where(function ($q) {
                            $q->whereIn('purchase_type_crm', ['Exchange Buy', 'EXCHANGE_BUY'])->orWhere(function ($s) {
                                $s->where(function ($s2) {
                                    $s2->whereNull('purchase_type_crm')->orWhere('purchase_type_crm', '');
                                })->whereIn('purchase_type', ['Exchange Buy', 'EXCHANGE_BUY']);
                            });
                        })
                        ->count(),

                    'exch_scrappage' => \App\Models\CRM\Enquiry::where('is_active', 1)
                        ->where(function ($q) {
                            $q->whereIn('purchase_type_crm', ['Scrappage', 'SCRAPPAGE'])->orWhere(function ($s) {
                                $s->where(function ($s2) {
                                    $s2->whereNull('purchase_type_crm')->orWhere('purchase_type_crm', '');
                                })->whereIn('purchase_type', ['Scrappage', 'SCRAPPAGE']);
                            });
                        })
                        ->count(),

                    'exch_not_interested' => \App\Models\CRM\Enquiry::where('is_active', 1)
                        ->where(function ($q) {
                            $q->whereIn('purchase_type_crm', [
                                'First Time Buy',
                                'FIRST_TIME_BUY',
                                'Additional Buy',
                                'ADDITIONAL_BUY',
                                'No Consideration',
                                'NO_CONSIDERATION',
                            ])->orWhere(function ($s) {
                                $s->where(function ($s2) {
                                    $s2->whereNull('purchase_type_crm')->orWhere('purchase_type_crm', '');
                                })->whereIn('purchase_type', [
                                    'First Time Buy',
                                    'FIRST_TIME_BUY',
                                    'Additional Buy',
                                    'ADDITIONAL_BUY',
                                    'No Consideration',
                                    'NO_CONSIDERATION',
                                ]);
                            });
                        })
                        ->count(),

                    // 3. Finance Enquiries
                    'fin_interest' => \App\Models\CRM\Enquiry::where('is_active', 1)
                        ->whereIn('fin_mode', ['IN_HOUSE', 'In-house'])
                        ->count(),
                    'fin_not_interested' => \App\Models\CRM\Enquiry::where('is_active', 1)
                        ->where(function ($q) {
                            $q->whereNull('fin_mode')
                                ->orWhere('fin_mode', '')
                                ->orWhereNotIn('fin_mode', ['IN_HOUSE', 'In-house']);
                        })
                        ->count(),

                    // 4. Quotations (Excluding 'booked' status per QuotationCrudController)
                    'quotation_all' => \App\Models\CRM\Quotation::whereNotIn('status', ['booked'])->count(),
                    'quotation_approved' => \App\Models\CRM\Quotation::where('status', 'Approved')->count(),

                    // 5. Bookings (Using exact status filters from BookingCrudController)
                    'booking_xceler8' => \App\Models\Module\Booking\Booking::whereIn('status', [1, 8])->count(),

                    'booking_pending_pay' => \App\Models\Module\Booking\Booking::whereIn('status', [1, 8])
                        ->where('b_type', 'Active')
                        ->whereIn('col_type', [2, 3])
                        ->where(function ($q) {
                            $bookingAmountTable = 'xlr8_booking_amount';
                            $q->whereRaw(
                                "booking_amount > COALESCE((SELECT SUM(amount) FROM {$bookingAmountTable} WHERE {$bookingAmountTable}.bid = xlr8_booking_master.id AND {$bookingAmountTable}.deleted_at IS NULL), 0)",
                            )
                                ->orWhereNull('receipt_no')
                                ->orWhere('receipt_no', '');
                        })
                        ->count(),

                    'booking_otf' => \DB::table('xlr8_crm_booking')->where('is_active', 1)->count(),

                    // 6. Transactions
                    'transaction_otf' => \DB::table('xlr8_crm_booking')->where('is_active', 1)->count(),
                    // EXACT MODIFIED CODE

                    // 7. Booking Stage -> Exchange
                    'bkng_exch_interest' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->join('xlr8_crm_enquiries as enq', function ($join) {
                            $join
                                ->on('enq.id', '=', 'bookings.enq_no')
                                ->orOn(
                                    \DB::raw("BINARY CONCAT('XENQ-', enq.id)"),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                )
                                ->orOn(\DB::raw('BINARY enq.enquiry_no'), '=', \DB::raw('BINARY bookings.enq_no'))
                                ->orOn(
                                    \DB::raw('BINARY enq.quick_enquiry_no'),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                );
                        })
                        ->where('enq.purchase_type', 'Exchange Buy')
                        ->count(),

                    'bkng_exch_scrappage' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->join('xlr8_crm_enquiries as enq', function ($join) {
                            $join
                                ->on('enq.id', '=', 'bookings.enq_no')
                                ->orOn(
                                    \DB::raw("BINARY CONCAT('XENQ-', enq.id)"),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                )
                                ->orOn(\DB::raw('BINARY enq.enquiry_no'), '=', \DB::raw('BINARY bookings.enq_no'))
                                ->orOn(
                                    \DB::raw('BINARY enq.quick_enquiry_no'),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                );
                        })
                        ->where('enq.purchase_type', 'Scrappage')
                        ->count(),

                    'bkng_exch_not_interested' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->join('xlr8_crm_enquiries as enq', function ($join) {
                            $join
                                ->on('enq.id', '=', 'bookings.enq_no')
                                ->orOn(
                                    \DB::raw("BINARY CONCAT('XENQ-', enq.id)"),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                )
                                ->orOn(\DB::raw('BINARY enq.enquiry_no'), '=', \DB::raw('BINARY bookings.enq_no'))
                                ->orOn(
                                    \DB::raw('BINARY enq.quick_enquiry_no'),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                );
                        })
                        ->whereIn('enq.purchase_type', ['First Time Buy', 'Additional Buy'])
                        ->count(),

                    // 8. Booking Stage -> Finance
                    'bkng_fin_interest' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->leftJoin('xlr8_booking_finance as xf', 'bookings.id', '=', 'xf.bid')
                        ->where('bookings.status', '!=', 2)
                        ->where(function ($q) {
                            $q->whereNull('xf.fin_mode')->orWhere('xf.fin_mode', 'In-house');
                        })
                        ->count(),

                    'bkng_fin_not_interested' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->leftJoin('xlr8_booking_finance as f', 'bookings.id', '=', 'f.bid')
                        ->leftJoin('xlr8_crm_enquiries as enq', function ($join) {
                            $join
                                ->on('enq.id', '=', 'bookings.enq_no')
                                ->orOn(
                                    \DB::raw("BINARY CONCAT('XENQ-', enq.id)"),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                )
                                ->orOn(\DB::raw('BINARY enq.enquiry_no'), '=', \DB::raw('BINARY bookings.enq_no'))
                                ->orOn(
                                    \DB::raw('BINARY enq.quick_enquiry_no'),
                                    '=',
                                    \DB::raw('BINARY bookings.enq_no'),
                                );
                        })
                        ->where(function ($q) {
                            $q->whereIn('f.fin_mode', ['Customer Self', 'Cash', 'Yet To Decide'])->orWhere(function (
                                $q2,
                            ) {
                                $q2->whereNull('f.fin_mode')->whereIn('enq.fin_mode', [
                                    'Customer Self',
                                    'Cash',
                                    'Yet To Decide',
                                ]);
                            });
                        })
                        ->count(),

                    'bkng_fin_retail' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->where('bookings.status', 2)
                        ->where('bookings.retail', 0)
                        ->count(),

                    'bkng_fin_payout' => \DB::table('xlr8_booking_master as bookings')
                        ->whereNull('bookings.deleted_at')
                        ->leftJoin('xlr8_booking_finance as f', 'bookings.id', '=', 'f.bid')
                        ->where('bookings.payout', 1)
                        ->where('bookings.retail', 1)
                        ->where('bookings.status', 2)
                        ->where('f.fin_mode', 'In-house')
                        ->where('f.case_status', 2)
                        ->count(),

                    // 9. Campaigns
                    'campaign' => \App\Models\CRM\Campaign::count(),
                ];
            });
        @endphp

        {{-- SALES MAIN DROPDOWN --}}
        <x-backpack::menu-dropdown title="Sales" icon="la la-chart-line">

            {{-- Separator --}}
            <x-backpack::menu-separator title="Sales Configuration" />

            {{-- Price List hidden: admin/pricing has no route or screen (BUG-069, DEC-038). --}}

            {{-- Enquiries --}}
            <x-backpack::menu-dropdown title="Enquiries" icon="la la-question-circle" nested="true">

                @if (backpack_user() && backpack_user()->can('SLS_ENQR_CREATE'))
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/create') }}">
                        <span><i class="la la-plus-circle"></i>Add New Enquiry</span>
                    </a>
                @endif

                @if (backpack_user() && backpack_user()->can('SLS_ENQR_VIEW'))
                    {{-- Custom HTML items with Light Grey Badges and 0 defaults --}}
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry') }}">
                        <span><i class="nav-icon la la-list me-2"></i>Master Enquiry List</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['all'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/xceler8') }}">
                        <span>
                            <i class="nav-icon la la-list me-2"></i>
                            Xceler8 Fresh Enquiries
                        </span>
                        <span class="badge rounded-pill text-dark" style="background-color: #e9ecef;">
                            {{ $enqCounts['xceler8'] ?? 0 }}
                        </span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/hyperlocal') }}">
                        <span><i class="nav-icon la la-map-marker me-2"></i>Hyperlocal Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['hyperlocal'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/reference') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Reference Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['reference'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/virtual-number') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Virtual Number Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['virtual'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/whatsapp-campaign') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>WhatsApp Campaign Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['whatsapp'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/unassigned-quick') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Unassigned Quick Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['unassigned_quick'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/assigned-quick') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Assigned Quick Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['assigned_quick'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/unassigned-long') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Unassigned Long Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['unassigned_long'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/assigned-long') }}">
                        <span><i class="nav-icon la la-user-times me-2"></i>Assigned Long Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['assigned_long'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/duplicate') }}">
                        <span><i class="nav-icon la la-copy me-2"></i>Duplicate Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['duplicate'] ?? 0 }}</span>
                    </a>
                @endif
                @if (backpack_user() && backpack_user()->can('SLS_CMPN_VIEW'))
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/campaign') }}">
                        <span><i class="la la-list me-2"></i>Campaign</span>
                        <span class="badge rounded-pill text-dark" style="background-color: #e9ecef;">{{ $enqCounts['campaign'] ?? 0 }}</span>
                    </a>
                @endif
                {{-- Erroneous Entries hidden: no sales/enquiry/erroneous route (DEC-023). --}}



            </x-backpack::menu-dropdown>

            {{-- Quotation --}}
            @if (backpack_user() && backpack_user()->can('SLS_QUOT_VIEW'))
                <x-backpack::menu-dropdown title="Quotation" icon="la la-file-alt" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/quotation') }}">
                        <span><i class="la la-file-signature me-2"></i>Quotation List</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['quotation_all'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/quotation/approved') }}">
                        <span><i class="la la-check-circle me-2"></i>Approved Quotations</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['quotation_approved'] ?? 0 }}</span>
                    </a>
                </x-backpack::menu-dropdown>
            @endif

            {{-- Test Drive --}}
            <!-- <x-backpack::menu-dropdown-item title="Test Drive" icon="la la-car-side" :link="backpack_url('testdrive')" /> -->

            {{-- Booking --}}
            @if (backpack_user() && backpack_user()->can('SLS_BKNG_VIEW'))
                <x-backpack::menu-dropdown title="Booking" icon="la la-book-open" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/booking') }}">
                        <span><i class="la la-list me-2"></i>Xceler8 Booking List</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['booking_xceler8'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/booking/pending-payment') }}">
                        <span><i class="la la-rupee-sign me-2"></i>Pending Payment Bookings</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['booking_pending_pay'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                        <span><i class="la la-receipt me-2"></i>Nil Payment Bookings</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('booking/dummy') }}">
                        <span><i class="la la-flask me-2"></i>Dummy Bookings</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ route('sales.enquiry.otf-bookings') }}">
                        <span><i class="la la-database me-2"></i>DMS OTF Dump</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['booking_otf'] ?? 0 }}</span>
                    </a>
                    <x-backpack::menu-dropdown title="Pending" icon="la la-clock" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-dms') }}">
                            <span><i class="la la-database me-2"></i>DMS Booking Data</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-order') }}">
                            <span><i class="la la-file-alt me-2"></i>Sales Order (SO Number)</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-kyc') }}">
                            <span><i class="la la-id-card me-2"></i>KYC</span>
                        </a>
                    </x-backpack::menu-dropdown>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/booking/erroneous-bookings') }}">
                        <span><i class="la la-exclamation-circle me-2"></i>Erroneous Entries</span>
                    </a>
                </x-backpack::menu-dropdown>
            @endif

            {{-- Transactions (nested inside Booking) --}}
            @if (backpack_user() && backpack_user()->can('SLS_BKNG_VIEW'))
                <x-backpack::menu-dropdown title="Transactions" icon="la la-handshake" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/booking/otf-form') }}">
                        <span><i class="la la-list me-2"></i>Transaction List</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['transaction_otf'] ?? 0 }}</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('booking/ready-to-invoice') }}">
                        <span><i class="la la-file-invoice-dollar me-2"></i>Ready To Invoice</span>
                    </a>

                    <x-backpack::menu-dropdown title="Pending" icon="la la-clock" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('booking/pending-incomplete-votfs') }}">
                            <span><i class="la la-exclamation-triangle me-2"></i>Incomplete VOTFs (@sales)</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-payment') }}">
                            <span><i class="la la-rupee-sign me-2"></i>Payment</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-invoices') }}">
                            <span><i class="la la-file-invoice me-2"></i>Invoices</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-insurance') }}">
                            <span><i class="la la-shield-alt me-2"></i>Insurance</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-rto') }}">
                            <span><i class="la la-truck me-2"></i>RTO</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-registration') }}">
                            <span><i class="la la-hashtag me-2"></i>Registration Number</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-deliveries') }}">
                            <span><i class="la la-truck me-2"></i>Deliveries</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/pending-do') }}">
                            <span><i class="la la-file-signature me-2"></i>Financier Delivery Order</span>
                        </a>
                    </x-backpack::menu-dropdown>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('booking/rto-agent-tracker') }}">
                        <span><i class="la la-user-tie me-2"></i>RTO Agent Tracker</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('booking/brokerage') }}">
                        <span><i class="la la-hand-holding-usd me-2"></i>Brokerage</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('co-dealer/erroneous') }}">
                        <span><i class="la la-exclamation-circle me-2"></i>Erroneous Entries</span>
                    </a>
                </x-backpack::menu-dropdown>
            @endif

            {{-- CRM Sales --}}
            <x-backpack::menu-dropdown title="CRM Sales" icon="la la-headset" nested="true">

                {{-- Feedback --}}
                <x-backpack::menu-dropdown title="Feedback" icon="la la-comment-alt" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/feedback/new-enquiry') }}">
                        <span><i class="la la-question-circle me-2"></i>New Enquiry</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/feedback/new-test-drive') }}">
                        <span><i class="la la-car me-2"></i>New Test Drive</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/feedback/new-booking') }}">
                        <span><i class="la la-book me-2"></i>New Booking</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/feedback/cancelled-booking') }}">
                        <span><i class="la la-times-circle me-2"></i>Cancelled Booking</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/feedback/new-delivery') }}">
                        <span><i class="la la-key me-2"></i>New Vehicle Delivery</span>
                    </a>
                </x-backpack::menu-dropdown>

                {{-- <x-backpack::menu-dropdown title="Followup" icon="la la-phone" nested="true">
            <x-backpack::menu-dropdown title="Enquiry" icon="la la-clipboard-list" nested="true">
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/enquiry/live') }}">
                <span><i class="la la-stream me-2"></i>Live Enquiries</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/enquiry/int-exchange') }}">
                    <span><i class="la la-sync me-2"></i>Int in Exchange</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/enquiry/int-finance') }}">
                    <span><i class="la la-file-invoice-dollar me-2"></i>Int in Finance</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/enquiry/int-ceramic-ppf') }}">
                    <span><i class="la la-spray-can me-2"></i>Int in Ceramic/PPF</span>
                </a>
            </x-backpack::menu-dropdown>

            <x-backpack::menu-dropdown title="Booking" icon="la la-bookmark" nested="true">
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/booking/live') }}">
                    <span><i class="la la-stream me-2"></i>Live Bookings</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/booking/int-exchange') }}">
                    <span><i class="la la-sync me-2"></i>Int in Exchange</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/booking/int-finance') }}">
                    <span><i class="la la-file-invoice-dollar me-2"></i>Int in Finance</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/followup/booking/int-ceramic-ppf') }}">
                    <span><i class="la la-spray-can me-2"></i>Int in Ceramic/PPF</span>
                </a>
            </x-backpack::menu-dropdown>
        </x-backpack::menu-dropdown> --}}

                {{-- Verifications --}}
                <x-backpack::menu-dropdown title="Verifications" icon="la la-check-double" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/verifications/lost-enquiries') }}">
                        <span><i class="la la-user-slash me-2"></i>Lost Enquiries</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['lost'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/verifications/receipt-confirmation') }}">
                        <span><i class="la la-receipt me-2"></i>Receipt Confirmation</span>
                    </a>
                </x-backpack::menu-dropdown>

                {{-- Activations --}}
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('crm-sales/activations/list') }}">
                    <span><i class="la la-toggle-on me-2"></i>Activations</span>
                </a>

                {{-- Alerts --}}
                <x-backpack::menu-dropdown title="Alerts" icon="la la-bell" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/alerts/fup-comments-mismatch') }}">
                        <span><i class="la la-exclamation-circle me-2"></i>Fup Comments Mismatch</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/alerts/fup-date-mismatch') }}">
                        <span><i class="la la-calendar-times me-2"></i>Fup Date Mismatch</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/alerts/wrongfully-lost-enquiries') }}">
                        <span><i class="la la-user-times me-2"></i>Wrongfully Lost Enquiries</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/alerts/fake-test-drive') }}">
                        <span><i class="la la-ban me-2"></i>Fake Test Drive</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('crm-sales/alerts/purchase-type-mismatch') }}">
                        <span><i class="la la-exchange-alt me-2"></i>Purchase Type Mismatch (Exchange)</span>
                    </a>
                </x-backpack::menu-dropdown>

                {{-- Concerns (merged Internal + Registered Concerns) --}}
                <x-backpack::menu-dropdown title="Concerns" icon="la la-exclamation-triangle" nested="true">

                    {{-- Internal --}}
                    <x-backpack::menu-dropdown title="Internal" icon="la la-building" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-question-circle me-2"></i>Enquiry</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-car me-2"></i>Test Drive</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-bookmark me-2"></i>Live Bookings</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-ban me-2"></i>Cancelled Bookings</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-truck-loading me-2"></i>Delivered Vehicles</span>
                        </a>
                    </x-backpack::menu-dropdown>

                    {{-- Intello --}}
                    {{-- TODO: confirm the actual items/routes this sub menu should contain --}}
                    <x-backpack::menu-dropdown title="Intello" icon="la la-robot" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-question-circle me-2"></i>Enquiry</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-car me-2"></i>Test Drive</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-bookmark me-2"></i>Live Bookings</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-ban me-2"></i>Cancelled Bookings</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                            <span><i class="la la-truck-loading me-2"></i>Delivered Vehicles</span>
                        </a>
                    </x-backpack::menu-dropdown>

                    {{-- Escalated (direct list, previously "Registered Concerns") --}}
                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                        <span><i class="la la-arrow-circle-up me-2"></i>Escalated</span>
                    </a>

                </x-backpack::menu-dropdown>

                {{-- Outstanding Management --}}
                <x-backpack::menu-dropdown title="Outstanding Management" icon="la la-sliders-h" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                        <span><i class="la la-book-open me-2"></i>Bookings</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                        <span><i class="la la-truck-loading me-2"></i>Delivered Vehicles</span>
                    </a>
                </x-backpack::menu-dropdown>

            </x-backpack::menu-dropdown>

            {{-- Exchange --}}
            <x-backpack::menu-dropdown title="Exchange" icon="la la-exchange-alt" nested="true">
                <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/exchange/int-in-exchange') }}">
                        <span><i class="la la-check me-2"></i>Int in Exchange</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['exch_interest'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/exchange/int-in-scrappage') }}">
                        <span><i class="la la-recycle me-2"></i>Int in Scrappage</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['exch_scrappage'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/exchange/not-interested') }}">
                        <span><i class="la la-thumbs-down me-2"></i>Not Interested</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['exch_not_interested'] ?? 0 }}</span>
                    </a>
                </x-backpack::menu-dropdown>
                @if (backpack_user() && backpack_user()->can('SLS_BKNG_EXCHANGE'))
                    <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/exchange') }}">
                            <span>Int in Exchange</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_exch_interest'] ?? 0 }}</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/scrappage') }}">
                            <span>Int in Scrappage</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_exch_scrappage'] ?? 0 }}</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/exchange/not-interested') }}">
                            <span>Not Interested</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_exch_not_interested'] ?? 0 }}</span>
                        </a>
                    </x-backpack::menu-dropdown>
                @endif
            </x-backpack::menu-dropdown>

            {{-- Finance --}}
            <x-backpack::menu-dropdown title="Finance" icon="la la-money-bill" nested="true">
                <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/finance/int-in-finance') }}">
                        <span><i class="la la-check me-2"></i>Int in Finance</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['fin_interest'] ?? 0 }}</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('sales/enquiry/finance/not-interested') }}">
                        <span><i class="la la-thumbs-down me-2"></i>Not Interested</span>
                        <span class="badge rounded-pill text-dark"
                            style="background-color: #e9ecef;">{{ $enqCounts['fin_not_interested'] ?? 0 }}</span>
                    </a>
                </x-backpack::menu-dropdown>
                @if (backpack_user() && backpack_user()->can('SLS_BKNG_FINANCE'))
                    <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/finance') }}">
                            <span>Int in Finance</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_fin_interest'] ?? 0 }}</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/finance/not-interested') }}">
                            <span>Not Interested</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_fin_not_interested'] ?? 0 }}</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/finance/retail') }}">
                            <span>Retail</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_fin_retail'] ?? 0 }}</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/finance/payout') }}">
                            <span>Payout</span>
                            <span class="badge rounded-pill text-dark"
                                style="background-color: #e9ecef;">{{ $enqCounts['bkng_fin_payout'] ?? 0 }}</span>
                        </a>
                    </x-backpack::menu-dropdown>
                @endif
            </x-backpack::menu-dropdown>

            {{-- Refund --}}
            <x-backpack::menu-dropdown title="Refund" icon="la la-undo" nested="true">
                @if (backpack_user() && backpack_user()->can('SLS_BKNG_REFUND'))
                    <x-backpack::menu-dropdown title="Booking Cancellation" icon="la la-book-open" nested="true">
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/refund/requested') }}">
                            <span>Requested</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/refunded') }}">
                            <span>Refunded</span>
                        </a>
                        <a class="dropdown-item d-flex align-items-center justify-content-between"
                            href="{{ backpack_url('sales/booking/rejected') }}">
                            <span>Rejected</span>
                        </a>
                    </x-backpack::menu-dropdown>
                @endif
                <x-backpack::menu-dropdown title="Customer Reconciliation" icon="la la-users-cog" nested="true">
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('refund/sales/requested') }}">
                        <span>Requested</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('refund/sales/refunded') }}">
                        <span>Refunded</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('refund/sales/rejected') }}">
                        <span>Rejected</span>
                    </a>
                </x-backpack::menu-dropdown>
            </x-backpack::menu-dropdown>

            {{-- Claims --}}
            <x-backpack::menu-dropdown title="Claims" icon="la la-file-medical" nested="true">
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-exchange-alt me-2"></i>Exchange</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-hand-holding-heart me-2"></i>Welcome</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-award me-2"></i>Loyalty</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-shield-alt me-2"></i>CSD</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-building me-2"></i>Corporate</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-handshake me-2"></i>Oem Retail Support</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-boxes me-2"></i>Oem Liquidation Support</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-exclamation-circle me-2"></i>Erroneous Entries</span>
                </a>

            </x-backpack::menu-dropdown>

            {{-- Sales Cashier --}}
            <x-backpack::menu-dropdown title="Sales Cashier" icon="la la-cash-register" nested="true">
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('receipt') }}">
                    <span>
                        <i class="la la-receipt me-2"></i>Issue Receipt
                    </span>
                </a>
                {{-- Special Discount --}}
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('special-discount') }}">
                    <span>
                        <i class="la la-percent me-2"></i>Special Discount
                    </span>
                </a>

                {{-- RTO Charges --}}
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('rto-charges') }}">
                    <span>
                        <i class="la la-registered me-2"></i>RTO Charges
                    </span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="#">
                    <span><i class="la la-exclamation-circle me-2"></i>Erroneous Entries</span>
                </a>
            </x-backpack::menu-dropdown>

            {{-- OTF Form --}}
            <!-- <x-backpack::menu-dropdown-item title="OTF Form" icon="la la-file-alt" :link="backpack_url('sales/booking/otf-form')" /> -->




            {{-- Other (Fees) --}}
            @can(['create_fee_collection', 'verify_fee_collection'])
                <x-backpack::menu-dropdown title="Other" icon="la la-file-invoice-dollar" nested="true">
                    <x-backpack::menu-dropdown title="Fee Collection" icon="la la-dollar-sign" nested="true">
                        <x-backpack::menu-dropdown title="Registration" icon="la la-registered" nested="true">
                            <a class="dropdown-item d-flex align-items-center justify-content-between"
                                href="{{ backpack_url('fee-collection/add') }}">
                                <span><i class="la la-plus-circle me-2"></i>Add Fee</span>
                            </a>
                            <a class="dropdown-item d-flex align-items-center justify-content-between"
                                href="{{ backpack_url('fee-collection') }}">
                                <span><i class="la la-list-ul me-2"></i>View List</span>
                            </a>
                        </x-backpack::menu-dropdown>
                    </x-backpack::menu-dropdown>
                </x-backpack::menu-dropdown>
            @endcan

            {{-- Booking Reports hidden for UAT: report screens 500 (BUG-122); Sales-team area (DEC-037). --}}

        </x-backpack::menu-dropdown>
        {{-- ====================== ACCOUNTS MODULE ====================== --}}
        <x-backpack::menu-dropdown title="Accounts" icon="la la-calculator">

            {{-- Manager --}}
            <x-backpack::menu-dropdown title="Manager" icon="la la-user-tie" nested="true">

                @if (backpack_user() && backpack_user()->can('ACC_RCPT_VIEW'))
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/receipt') }}">
                        <span><i class="la la-receipt me-2"></i>Issue Receipt</span>
                    </a>
                @endif

                @if (backpack_user() && backpack_user()->can('ACC_JRVCH_VIEW'))
                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/journal-voucher') }}">
                        <span><i class="la la-file-invoice me-2"></i> Journal Voucher</span>
                    </a>
                @endif

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/manager/cash-collection-reconciliation') }}">
                    <span><i class="la la-hand-holding-usd me-2"></i>Cash Collection Reconciliation</span>
                </a>

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/manager/cash-deposit-reconciliation') }}">
                    <span><i class="la la-university me-2"></i>Cash Deposit Reconciliation</span>
                </a>

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/manager/cash-deposit-approval') }}">
                    <span><i class="la la-check-circle me-2"></i>Cash Deposit Approval</span>
                </a>

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/manager/sdr') }}">
                    <span><i class="la la-file-alt me-2"></i>SDR</span>
                </a>

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/manager/rcr') }}">
                    <span><i class="la la-file-invoice-dollar me-2"></i>RCR</span>
                </a>

            </x-backpack::menu-dropdown>


            {{-- Cashier --}}
            <x-backpack::menu-dropdown title="Cashier" icon="la la-cash-register" nested="true">

                {{-- Sales --}}
                <x-backpack::menu-dropdown title="Sales" icon="la la-shopping-cart" nested="true">

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/cashier/sales/issue-receipt') }}">
                        <span><i class="la la-receipt me-2"></i>Issue Receipt</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/cashier/sales/sdr') }}">
                        <span><i class="la la-file-alt me-2"></i>SDR</span>
                    </a>

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/cashier/sales/rcr') }}">
                        <span><i class="la la-file-invoice-dollar me-2"></i>RCR</span>
                    </a>

                </x-backpack::menu-dropdown>


                {{-- Service --}}
                <x-backpack::menu-dropdown title="Service" icon="la la-wrench" nested="true">

                    <a class="dropdown-item d-flex align-items-center justify-content-between"
                        href="{{ backpack_url('accounts/cashier/service/issue-receipt') }}">
                        <span><i class="la la-receipt me-2"></i>Issue Receipt</span>
                    </a>

                </x-backpack::menu-dropdown>

            </x-backpack::menu-dropdown>


            {{-- Executive --}}
            <x-backpack::menu-dropdown title="Executive" icon="la la-user" nested="true">

                <a class="dropdown-item d-flex align-items-center justify-content-between"
                    href="{{ backpack_url('accounts/executive/cash-deposit-entry') }}">
                    <span><i class="la la-money-bill me-2"></i>Cash Deposit Entry</span>
                </a>

            </x-backpack::menu-dropdown>

        </x-backpack::menu-dropdown>

        {{-- Spares module hidden for UAT: all operations fail (BUG-030/031/032/116); rebuilt in Track B (DEC-037). --}}

        {{-- ====================== IMPORTS MODULE ====================== --}}
        <x-backpack::menu-dropdown title="Imports" icon="la la-download">

            <x-backpack::menu-separator title="Import Operations" />
            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('imports/admin') }}">
                <span><i class="la la-user-tag me-2"></i>Admin</span>
            </a>

            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('imports/sales') }}">
                <span><i class="la la-shopping-cart me-2"></i>Sales</span>
            </a>

            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('imports/service') }}">
                <span><i class="la la-wrench me-2"></i>Service</span>
            </a>

            <a class="dropdown-item d-flex align-items-center justify-content-between"
                href="{{ backpack_url('imports/spares') }}">
                <span><i class="la la-tools me-2"></i>Spares</span>
            </a>

        </x-backpack::menu-dropdown>
