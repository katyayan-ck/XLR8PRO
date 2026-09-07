{{-- MAIN ADMIN MENU - ALL ITEMS NESTED --}}
<x-backpack::menu-dropdown title="Admin" icon="la la-th">

    {{-- Dashboard --}}
    <x-backpack::menu-dropdown-item title="Dashboard" icon="la la-home" :link="backpack_url('dashboard')" />

    {{-- Separator --}}
    <x-backpack::menu-separator title="Configuration" />

    {{-- Utilities Section --}}
    <x-backpack::menu-dropdown title="Utilities" icon="la la-wrench" nested="true">
        <x-backpack::menu-dropdown-item title="Keyword Master" icon="la la-tag"
            :link="backpack_url('keyword-master')" />
        <x-backpack::menu-dropdown-item title="Key Values" icon="la la-key" :link="backpack_url('keyvalue')" />
    </x-backpack::menu-dropdown>

    {{-- Foundation Section --}}
    <x-backpack::menu-dropdown title="Foundation" icon="la la-building" nested="true">
        <x-backpack::menu-dropdown-item title="Branch" icon="la la-code-branch" :link="backpack_url('branch')" />
        <x-backpack::menu-dropdown-item title="Location" icon="la la-map-marker" :link="backpack_url('location')" />
        <x-backpack::menu-dropdown-item title="Department" icon="la la-layer-group"
            :link="backpack_url('department')" />
        <x-backpack::menu-dropdown-item title="Division" icon="la la-layer-group" :link="backpack_url('division')" />
        <x-backpack::menu-dropdown-item title="Designation" icon="la la-id-badge" :link="backpack_url('designation')" />
        <x-backpack::menu-dropdown-item title="Vertical" icon="la la-bars" :link="backpack_url('vertical')" />

        {{-- IAM: Post & Org Structure --}}
        <x-backpack::menu-dropdown-item title="Posts" icon="la la-briefcase" :link="backpack_url('post')" />
        <x-backpack::menu-dropdown-item title="Designation Dept Tree" icon="la la-sitemap"
            :link="backpack_url('desig-dept-tree')" />
        <x-backpack::menu-dropdown-item title="Post Reporting Lines" icon="la la-project-diagram"
            :link="backpack_url('post-reporting')" />
    </x-backpack::menu-dropdown>

    {{-- Vehicles Info Section --}}
    <x-backpack::menu-dropdown title="Vehicles Info" icon="la la-car" nested="true">
        <x-backpack::menu-dropdown-item title="Brand" icon="la la-trademark" :link="backpack_url('brand')" />
        <x-backpack::menu-dropdown-item title="Segment" icon="la la-rectangle-wide" :link="backpack_url('segment')" />
        <x-backpack::menu-dropdown-item title="Sub Segment" icon="la la-rectangle-narrow"
            :link="backpack_url('sub-segment')" />
        <x-backpack::menu-dropdown-item title="Vehicle Model" icon="la la-cube" :link="backpack_url('vehicle-model')" />
        <x-backpack::menu-dropdown-item title="Variant" icon="la la-clone" :link="backpack_url('variant')" />
        <x-backpack::menu-dropdown-item title="Color" icon="la la-palette" :link="backpack_url('color')" />
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <x-backpack::menu-separator title="Users & Organization" />

    {{-- Users Info Section --}}
    <x-backpack::menu-dropdown title="Users Info" icon="la la-users" nested="true">
        <x-backpack::menu-dropdown-item title="User Type" icon="la la-user-tag" :link="backpack_url('user-type')" />
        <x-backpack::menu-dropdown-item title="Person" icon="la la-user-circle" :link="backpack_url('person')" />
        <x-backpack::menu-dropdown-item title="Person Contact" icon="la la-phone"
            :link="backpack_url('person-contact')" />
        <x-backpack::menu-dropdown-item title="Person Address" icon="la la-map-pin"
            :link="backpack_url('person-address')" />
        <x-backpack::menu-dropdown-item title="Person Banking Detail" icon="la la-university"
            :link="backpack_url('person-banking-detail')" />
        <x-backpack::menu-dropdown-item title="Garage" icon="la la-warehouse" :link="backpack_url('garage')" />
        <x-backpack::menu-dropdown-item title="User" icon="la la-user" :link="backpack_url('user')" />

        {{-- 4-LEVEL NESTED: Employee Info --}}
        <x-backpack::menu-dropdown title="Employee Info" icon="la la-sitemap" nested="true">
            <x-backpack::menu-dropdown-item title="Employee" icon="la la-user-tie" :link="backpack_url('employee')" />
            <x-backpack::menu-dropdown-item title="Employee Dept Assignment" icon="la la-link"
                :link="backpack_url('employee-department-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Branch Assignment" icon="la la-link"
                :link="backpack_url('employee-branch-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Location Assignment" icon="la la-link"
                :link="backpack_url('employee-location-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Vertical Assignment" icon="la la-link"
                :link="backpack_url('employee-vertical-assignment')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <x-backpack::menu-separator title="HR Journey" />

    {{-- HR Section --}}
    <x-backpack::menu-dropdown title="HR Operations" icon="la la-user-clock" nested="true">
        <x-backpack::menu-dropdown-item title="Post Assignments" icon="la la-briefcase"
            :link="backpack_url('emp-post-assignment')" />
        <x-backpack::menu-dropdown-item title="Transfer Employee" icon="la la-exchange-alt"
            :link="backpack_url('hr/transfer')" />
        <x-backpack::menu-dropdown-item title="Relieve Employee" icon="la la-sign-out-alt"
            :link="backpack_url('hr/relieve')" />
        <x-backpack::menu-dropdown-item title="Employee Journey" icon="la la-history"
            :link="backpack_url('hr/journey')" />
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <x-backpack::menu-separator title="Access Control" />

    {{-- RBAC Section --}}
    <x-backpack::menu-dropdown title="RBAC" icon="la la-lock" nested="true">
        <x-backpack::menu-dropdown-item title="Modules" icon="la la-cube" :link="backpack_url('modules')" />
        <x-backpack::menu-dropdown-item title="Process" icon="la la-cogs" :link="backpack_url('process')" />
        <x-backpack::menu-dropdown-item title="Role" icon="la la-users" :link="backpack_url('role')" />
        <x-backpack::menu-dropdown-item title="Permission" icon="la la-key" :link="backpack_url('permission')" />
        <x-backpack::menu-dropdown-item title="Post Permission" icon="la la-check-circle"
            :link="backpack_url('post-permission')" />
        <x-backpack::menu-dropdown-item title="Approval Hierarchy" icon="la la-shield-alt"
            :link="backpack_url('approval-hierarchy')" />
    </x-backpack::menu-dropdown>

</x-backpack::menu-dropdown>

{{-- USERS --}}
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('user')" />


{{-- ========================================================================= --}}
{{-- FETCH ENQUIRY COUNTS (Cached for 60 seconds to prevent slow page loads) --}}
{{-- ========================================================================= --}}
@php
    $enqCounts = \Illuminate\Support\Facades\Cache::remember('menu_enquiry_counts', 60, function () {
        return [
            'all' => \App\Models\CRM\Enquiry::count(),
            'reference' => \App\Models\CRM\Enquiry::reference()->count(),
            'virtual' => \App\Models\CRM\Enquiry::virtual()->count(),
            'whatsapp' => \App\Models\CRM\Enquiry::whatsapp()->count(),
            'hyperlocal' => \App\Models\CRM\Enquiry::hyperlocal()->count(), // Added line
            'unassigned_quick' => \App\Models\CRM\Enquiry::unassignedQuick()->count(),
            'assigned_quick' => \App\Models\CRM\Enquiry::assignedQuick()->count(),
            'unassigned_long' => \App\Models\CRM\Enquiry::unassignedLong()->count(),
            'assigned_long' => \App\Models\CRM\Enquiry::assignedLong()->count(),
        ];
    });
@endphp

{{-- SALES MAIN DROPDOWN --}}
<x-backpack::menu-dropdown title="Sales" icon="la la-chart-line">

    {{-- Separator --}}
    <x-backpack::menu-separator title="Sales Configuration" />

    {{-- Price List --}}
    @if (auth()->check() && (auth()->user()->hasPermissionTo('can_view_documents') || auth()->user()->hasRole('super
    admin')))
    <x-backpack::menu-dropdown-item title="Price List" icon="la la-tag" :link="backpack_url('pricing')" />
    @endif

    {{-- Enquiries --}}
    <x-backpack::menu-dropdown title="Enquiries" icon="la la-question-circle" nested="true">

        <x-backpack::menu-dropdown-item title="Add New Enquiry" icon="la la-plus-circle" :link="backpack_url('enquiries/add')" />

        {{-- Custom HTML items with Light Grey Badges and 0 defaults --}}
        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries-list') }}">
            <span><i class="nav-icon la la-list me-2"></i> Enquiry List</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['all'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/hyperlocal') }}">
            <span><i class="nav-icon la la-map-marker me-2"></i> Hyperlocal Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['hyperlocal'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/reference') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Reference Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['reference'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/virtual-number') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Virtual Number Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['virtual'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/whatsapp-campaign') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> WhatsApp Campaign Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['whatsapp'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/unassigned-quick') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Unassigned Quick Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['unassigned_quick'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/assigned-quick') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Assigned Quick Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['assigned_quick'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/unassigned-long') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Unassigned Long Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['unassigned_long'] ?? 0 }}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center justify-content-between"
            href="{{ backpack_url('enquiries/assigned-long') }}">
            <span><i class="nav-icon la la-user-times me-2"></i> Assigned Long Enquiries</span>
            <span class="badge rounded-pill text-dark"
                style="background-color: #e9ecef;">{{ $enqCounts['assigned_long'] ?? 0 }}</span>
        </a>

       <!-- <x-backpack::menu-dropdown-item title="OTF Bookings" icon="la la-file-invoice" :link="backpack_url('enquiries/otf-bookings')" /> -->

        <x-backpack::menu-dropdown-item title="Campaigns" icon="la la-list" :link="backpack_url('campaign')" />

        <x-backpack::menu-dropdown-item title="Pending Enquiries" icon="la la-exclamation-triangle" :link="backpack_url('enquiries/pending')" />
        <x-backpack::menu-dropdown-item title="Erroneous Entries" icon="la la-bug" :link="backpack_url('enquiries/erroneous')" />

    </x-backpack::menu-dropdown>

    {{-- Quotation --}}
    <x-backpack::menu-dropdown title="Quotation" icon="la la-file-alt" nested="true">
        <x-backpack::menu-dropdown-item title="Quotation List" icon="la la-file-signature" :link="backpack_url('quotation-form')" />
        <x-backpack::menu-dropdown-item title="Pending Quotations" icon="la la-clock" :link="backpack_url('quotation-form/pending')" />
    </x-backpack::menu-dropdown>    

    {{-- Test Drive --}}
    <!-- <x-backpack::menu-dropdown-item title="Test Drive" icon="la la-car-side" :link="backpack_url('testdrive')" /> -->

    {{-- Booking --}}
    <x-backpack::menu-dropdown title="Booking" icon="la la-book-open" nested="true">
        <!-- <x-backpack::menu-dropdown-item title="Add New Booking" icon="la la-plus-circle"
            :link="backpack_url('booking/create')" /> -->
        <x-backpack::menu-dropdown-item title="Booking List" icon="la la-list" :link="backpack_url('booking')" />

        <x-backpack::menu-dropdown-item title="DMS Booking List" icon="la la-database" :link="backpack_url('booking/pending-dms')" />

        <x-backpack::menu-dropdown-item title="Dummy Bookings" icon="la la-flask"
            :link="backpack_url('booking/dummy')" />

        <!-- <x-backpack::menu-separator title="Pending Stages" />
        <x-backpack::menu-dropdown-item title="Pending DMS Booking" icon="la la-database"
            :link="backpack_url('booking/pending-dms')" />
        <x-backpack::menu-dropdown-item title="Pending Sales Order" icon="la la-file-alt"
            :link="backpack_url('booking/pending/sales-order')" />
        <x-backpack::menu-dropdown-item title="Pending KYC" icon="la la-id-card"
            :link="backpack_url('booking/pending-kyc')" />
        <x-backpack::menu-dropdown-item title="Pending Payment" icon="la la-rupee-sign"
            :link="backpack_url('booking/pending-payment')" />
        <x-backpack::menu-dropdown-item title="Pending Invoices" icon="la la-file-invoice"
            :link="backpack_url('booking/pending-invoices')" />
        <x-backpack::menu-dropdown-item title="Pending Insurance" icon="la la-shield-alt"
            :link="backpack_url('booking/pending-insurance')" />
        <x-backpack::menu-dropdown-item title="Pending RTO" icon="la la-car"
            :link="backpack_url('booking/pending-rto')" />
        <x-backpack::menu-dropdown-item title="Pending Deliveries" icon="la la-truck"
            :link="backpack_url('booking/pending-deliveries')" />
        <x-backpack::menu-dropdown-item title="Pending Reg. No." icon="la la-hashtag"
            :link="backpack_url('booking/pending-registration')" />
        <x-backpack::menu-dropdown-item title="Pending DO" icon="la la-file-signature"
            :link="backpack_url('booking/pending-do')" /> -->
        <x-backpack::menu-dropdown title="Pending" icon="la la-clock" nested="true">
            <x-backpack::menu-dropdown-item title="DMS" icon="la la-database" :link="backpack_url('booking/pending-dms')" />
            <x-backpack::menu-dropdown-item title="SO" icon="la la-file-alt" :link="backpack_url('booking/pending/sales-order')" />
            <x-backpack::menu-dropdown-item title="KYC" icon="la la-id-card" :link="backpack_url('booking/pending-kyc')" />
            <x-backpack::menu-dropdown-item title="Payment" icon="la la-rupee-sign" :link="backpack_url('booking/pending-payment')" />
        </x-backpack::menu-dropdown>
        

        <x-backpack::menu-dropdown title="Erroneous Entries" icon="la la-exclamation-circle" nested="true">
            <x-backpack::menu-dropdown-item title="Booking" icon="la la-book"
                :link="backpack_url('booking/erroneous-bookings')" />
            <x-backpack::menu-dropdown-item title="Finance" icon="la la-money-bill"
                :link="backpack_url('finance/erroneous')" />
            <x-backpack::menu-dropdown-item title="Insurance" icon="la la-shield"
                :link="backpack_url('insurance/erroneous')" />
            <x-backpack::menu-dropdown-item title="RTO" icon="la la-id-card" :link="backpack_url('rto/erroneous')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{--  Transactions --}}
    <x-backpack::menu-dropdown title="Transactions" icon="la la-handshake" nested="true">
        <x-backpack::menu-dropdown-item title="Transaction List" icon="la la-list" :link="backpack_url('booking/otf-form')" />

        <x-backpack::menu-dropdown title="Pending" icon="la la-clock" nested="true">
            <x-backpack::menu-dropdown-item title="Payment" icon="la la-rupee-sign" :link="backpack_url('booking/pending-payment')" />
            <x-backpack::menu-dropdown-item title="Invoices" icon="la la-file-invoice" :link="backpack_url('booking/pending-invoices')" />
            <x-backpack::menu-dropdown-item title="Insurance" icon="la la-shield-alt" :link="backpack_url('booking/pending-insurance')" />
            <x-backpack::menu-dropdown-item title="RTO Delivery" icon="la la-truck" :link="backpack_url('booking/pending-rto')" />
            <x-backpack::menu-dropdown-item title="Registration No." icon="la la-hashtag" :link="backpack_url('booking/pending-registration')" />
            <x-backpack::menu-dropdown-item title="DO" icon="la la-file-signature" :link="backpack_url('booking/pending-do')" />
        </x-backpack::menu-dropdown>

        <x-backpack::menu-dropdown-item title="Erroneous Entries" icon="la la-exclamation-circle" :link="backpack_url('co-dealer/erroneous')" />
    </x-backpack::menu-dropdown>

    {{-- CRM Sales --}}
    <x-backpack::menu-dropdown title="CRM Sales" icon="la la-headset" nested="true">
        
        {{-- Feedback --}}
        <x-backpack::menu-dropdown title="Feedback" icon="la la-comment-alt" nested="true">
            <x-backpack::menu-dropdown-item title="New Enquiry" icon="la la-question-circle" :link="backpack_url('crm-sales/feedback/new-enquiry')" />
            <x-backpack::menu-dropdown-item title="New Test Drive" icon="la la-car" :link="backpack_url('crm-sales/feedback/new-test-drive')" />
            <x-backpack::menu-dropdown-item title="New Booking" icon="la la-book" :link="backpack_url('crm-sales/feedback/new-booking')" />
            <x-backpack::menu-dropdown-item title="Cancelled Booking" icon="la la-times-circle" :link="backpack_url('crm-sales/feedback/cancelled-booking')" />
            <x-backpack::menu-dropdown-item title="New Vehicle Delivery" icon="la la-key" :link="backpack_url('crm-sales/feedback/new-delivery')" />
        </x-backpack::menu-dropdown>

        {{-- Followup --}}
        <x-backpack::menu-dropdown title="Followup" icon="la la-phone" nested="true">
            {{-- Enquiry Sub-items --}}
            <x-backpack::menu-dropdown title="Enquiry" icon="la la-clipboard-list" nested="true">
                <x-backpack::menu-dropdown-item title="Live Enquiries" icon="la la-stream" :link="backpack_url('crm-sales/followup/enquiry/live')" />
                <x-backpack::menu-dropdown-item title="Int in Exchange" icon="la la-sync" :link="backpack_url('crm-sales/followup/enquiry/int-exchange')" />
                <x-backpack::menu-dropdown-item title="Int in Finance" icon="la la-file-invoice-dollar" :link="backpack_url('crm-sales/followup/enquiry/int-finance')" />
                <x-backpack::menu-dropdown-item title="Int in Ceramic/PPF" icon="la la-spray-can" :link="backpack_url('crm-sales/followup/enquiry/int-ceramic-ppf')" />
            </x-backpack::menu-dropdown>

            {{-- Booking Sub-items --}}
            <x-backpack::menu-dropdown title="Booking" icon="la la-bookmark" nested="true">
                <x-backpack::menu-dropdown-item title="Live Bookings" icon="la la-stream" :link="backpack_url('crm-sales/followup/booking/live')" />
                <x-backpack::menu-dropdown-item title="Int in Exchange" icon="la la-sync" :link="backpack_url('crm-sales/followup/booking/int-exchange')" />
                <x-backpack::menu-dropdown-item title="Int in Finance" icon="la la-file-invoice-dollar" :link="backpack_url('crm-sales/followup/booking/int-finance')" />
                <x-backpack::menu-dropdown-item title="Int in Ceramic/PPF" icon="la la-spray-can" :link="backpack_url('crm-sales/followup/booking/int-ceramic-ppf')" />
            </x-backpack::menu-dropdown>
        </x-backpack::menu-dropdown>

        {{-- Verifications --}}
        <x-backpack::menu-dropdown title="Verifications" icon="la la-check-double" nested="true">
            <x-backpack::menu-dropdown-item title="Lost Enquiries" icon="la la-user-slash" :link="backpack_url('crm-sales/verifications/lost-enquiries')" />
            <x-backpack::menu-dropdown-item title="Receipt Confirmation" icon="la la-receipt" :link="backpack_url('crm-sales/verifications/receipt-confirmation')" />
            <x-backpack::menu-dropdown-item title="Outstanding Controls" icon="la la-sliders-h" :link="backpack_url('crm-sales/verifications/outstanding-controls')" />
        </x-backpack::menu-dropdown>

        {{-- Activations --}}
        <x-backpack::menu-dropdown title="Activations" icon="la la-toggle-on" nested="true">
            <x-backpack::menu-dropdown-item title="List" icon="la la-list" :link="backpack_url('crm-sales/activations/list')" />
        </x-backpack::menu-dropdown>

        {{-- Alerts --}}
        <x-backpack::menu-dropdown title="Alerts" icon="la la-bell" nested="true">
            <x-backpack::menu-dropdown-item title="Fup Comments Mismatch" icon="la la-exclamation-circle" :link="backpack_url('crm-sales/alerts/fup-comments-mismatch')" />
            <x-backpack::menu-dropdown-item title="Fup Date Mismatch" icon="la la-calendar-times" :link="backpack_url('crm-sales/alerts/fup-date-mismatch')" />
            <x-backpack::menu-dropdown-item title="Wrongfully Lost Enquiries" icon="la la-user-times" :link="backpack_url('crm-sales/alerts/wrongfully-lost-enquiries')" />
            <x-backpack::menu-dropdown-item title="Fake Test Drive" icon="la la-ban" :link="backpack_url('crm-sales/alerts/fake-test-drive')" />
            <x-backpack::menu-dropdown-item title="Purchase Type Mismatch (Exchange)" icon="la la-exchange-alt" :link="backpack_url('crm-sales/alerts/purchase-type-mismatch')" />
        </x-backpack::menu-dropdown>

        {{-- Internal Concerns --}}
        <x-backpack::menu-dropdown title="Internal Concerns" icon="la la-exclamation-triangle" nested="true">
            <x-backpack::menu-dropdown-item title="Enquiry" icon="la la-question-circle" :link="backpack_url('crm-sales/internal-concerns/enquiry')" />
            <x-backpack::menu-dropdown-item title="Test Drive" icon="la la-car" :link="backpack_url('crm-sales/internal-concerns/test-drive')" />
            <x-backpack::menu-dropdown-item title="Live Bookings" icon="la la-bookmark" :link="backpack_url('crm-sales/internal-concerns/live-bookings')" />
            <x-backpack::menu-dropdown-item title="Cancelled Bookings" icon="la la-ban" :link="backpack_url('crm-sales/internal-concerns/cancelled-bookings')" />
            <x-backpack::menu-dropdown-item title="Delivered Vehicles" icon="la la-truck-loading" :link="backpack_url('crm-sales/internal-concerns/delivered-vehicles')" />
        </x-backpack::menu-dropdown>

        {{-- Registered Concerns --}}
        <x-backpack::menu-dropdown title="Registered Concerns" icon="la la-folder-open" nested="true">
            <x-backpack::menu-dropdown-item title="Enquiry" icon="la la-question-circle" :link="backpack_url('crm-sales/registered-concerns/enquiry')" />
            <x-backpack::menu-dropdown-item title="Test Drive" icon="la la-car" :link="backpack_url('crm-sales/registered-concerns/test-drive')" />
            <x-backpack::menu-dropdown-item title="Live Bookings" icon="la la-bookmark" :link="backpack_url('crm-sales/registered-concerns/live-bookings')" />
            <x-backpack::menu-dropdown-item title="Cancelled Bookings" icon="la la-ban" :link="backpack_url('crm-sales/registered-concerns/cancelled-bookings')" />
            <x-backpack::menu-dropdown-item title="Delivered Vehicles" icon="la la-truck-loading" :link="backpack_url('crm-sales/registered-concerns/delivered-vehicles')" />
        </x-backpack::menu-dropdown>

    </x-backpack::menu-dropdown>

    {{-- Exchange --}}
    <x-backpack::menu-dropdown title="Exchange" icon="la la-exchange-alt" nested="true">
        <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Exchange" icon="la la-check"
                :link="backpack_url('exchange/enquiry/int-in-exchange')" />
            <x-backpack::menu-dropdown-item title="Int in Scrappage" icon="la la-recycle"
                :link="backpack_url('exchange/enquiry/int-in-scrappage')" />
            <x-backpack::menu-dropdown-item title="Not Interested" icon="la la-thumbs-down"
                :link="backpack_url('exchange/enquiry/not-interested')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Exchange" :link="backpack_url('booking/exchange')" />
            <x-backpack::menu-dropdown-item title="Int in Scrappage" :link="backpack_url('booking/scrappage')" />
            <x-backpack::menu-dropdown-item title="Not Interested"
                :link="backpack_url('booking/exchange/not-interested')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Finance --}}
    <x-backpack::menu-dropdown title="Finance" icon="la la-money-bill" nested="true">
        <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Finance" icon="la la-check"
                :link="backpack_url('finance/enquiry/int-in-finance')" />
            <x-backpack::menu-dropdown-item title="Not Interested" icon="la la-thumbs-down"
                :link="backpack_url('finance/enquiry/not-interested')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Finance" :link="backpack_url('booking/finance')" />
            <x-backpack::menu-dropdown-item title="Not Interested"
                :link="backpack_url('booking/finance/not-interested')" />
            <x-backpack::menu-dropdown-item title="Retail" :link="backpack_url('booking/finance/retail')" />
            <x-backpack::menu-dropdown-item title="Payout" :link="backpack_url('finance/payout')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Refund --}}
    <x-backpack::menu-dropdown title="Refund" icon="la la-undo" nested="true">
        <x-backpack::menu-dropdown title="Bookings" icon="la la-book-open" nested="true">
            <x-backpack::menu-dropdown-item title="Requested" :link="backpack_url('booking/refund/requested')" />
            <x-backpack::menu-dropdown-item title="Refunded" :link="backpack_url('booking/refunded')" />
            <x-backpack::menu-dropdown-item title="Rejected" :link="backpack_url('booking/rejected')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Customer Recon" icon="la la-users-cog" nested="true">
            <x-backpack::menu-dropdown title="Sales" icon="la la-chart-line" nested="true">
                <x-backpack::menu-dropdown-item title="Requested" :link="backpack_url('refund/sales/requested')" />
                <x-backpack::menu-dropdown-item title="Refunded" :link="backpack_url('refund/sales/refunded')" />
                <x-backpack::menu-dropdown-item title="Rejected" :link="backpack_url('refund/sales/rejected')" />
            </x-backpack::menu-dropdown>
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- OTF Form --}}
    <!-- <x-backpack::menu-dropdown-item title="OTF Form" icon="la la-file-alt" :link="backpack_url('booking/otf-form')" /> -->

    
    

    {{-- Other (Fees) --}}
    @can(['create_fee_collection', 'verify_fee_collection'])
    <x-backpack::menu-dropdown title="Other" icon="la la-file-invoice-dollar" nested="true">
        <x-backpack::menu-dropdown title="Fee Collection" icon="la la-dollar-sign" nested="true">
            <x-backpack::menu-dropdown title="Registration" icon="la la-registered" nested="true">
                <x-backpack::menu-dropdown-item title="Add Fee" icon="la la-plus-circle"
                    :link="backpack_url('fee-collection/add')" />
                <x-backpack::menu-dropdown-item title="View List" icon="la la-list-ul"
                    :link="backpack_url('fee-collection')" />
            </x-backpack::menu-dropdown>
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>
    @endcan

    {{-- Reports --}}
    <x-backpack::menu-dropdown title="Reports" icon="la la-file-alt" nested="true">
        <x-backpack::menu-dropdown title="Stock" icon="la la-boxes" nested="true">
            <x-backpack::menu-dropdown-item title="Current Stock" :link="backpack_url('reports/stock')" />
            <x-backpack::menu-dropdown-item title="Live Order" :link="backpack_url('reports/live-order')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Booking" icon="la la-book" nested="true">
            <x-backpack::menu-dropdown-item title="Consolidated Booking"
                :link="backpack_url('reports/consolidated-booking')" />
            <x-backpack::menu-dropdown-item title="Branch Booking" :link="backpack_url('reports/branch-booking')" />
            <x-backpack::menu-dropdown-item title="Pending Actions" :link="backpack_url('reports/pending-actions')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

</x-backpack::menu-dropdown>

{{-- ====================== SPARES MODULE ====================== --}}
<x-backpack::menu-dropdown title="Spares" icon="la la-tools">
    <x-backpack::menu-separator title="Spare Operations" />
    <x-backpack::menu-dropdown-item title="Add New" icon="la la-plus-circle"
        :link="backpack_url('spare-request/create')" />
    <x-backpack::menu-dropdown-item title="RO Wise List" icon="la la-list" :link="backpack_url('spare-request')" />
    <x-backpack::menu-dropdown-item title="Partwise Requirement" icon="la la-list-alt"
        :link="backpack_url('spare/partwise-requirement')" />
    <x-backpack::menu-separator title="Reports" />
    <x-backpack::menu-dropdown-item title="Parts Ordering Report" icon="la la-chart-bar"
        :link="backpack_url('spare/orderingreport')" />
</x-backpack::menu-dropdown>