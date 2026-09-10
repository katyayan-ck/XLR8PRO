{{-- MAIN ADMIN MENU - ALL ITEMS NESTED --}}
<x-backpack::menu-dropdown title="Admin" icon="la la-th">

    {{-- Dashboard --}}
    <x-backpack::menu-dropdown-item title="Dashboard" icon="la la-home" :link="backpack_url('dashboard')" />

    {{-- Separator --}}
    <x-backpack::menu-separator title="Configuration" />

    {{-- Utilities Section --}}
    <x-backpack::menu-dropdown title="Utilities" icon="la la-wrench" nested="true">
        <x-backpack::menu-dropdown-item title="Keyword Master" icon="la la-tag" :link="backpack_url('keyword-master')" />
        <x-backpack::menu-dropdown-item title="Key Values" icon="la la-key" :link="backpack_url('keyvalue')" />
    </x-backpack::menu-dropdown>

    {{-- Foundation Section --}}
    <x-backpack::menu-dropdown title="Foundation" icon="la la-building" nested="true">
        <x-backpack::menu-dropdown-item title="Branch" icon="la la-code-branch" :link="backpack_url('branch')" />
        <x-backpack::menu-dropdown-item title="Location" icon="la la-map-marker" :link="backpack_url('location')" />
        <x-backpack::menu-dropdown-item title="Department" icon="la la-layer-group" :link="backpack_url('department')" />
        <x-backpack::menu-dropdown-item title="Division" icon="la la-layer-group" :link="backpack_url('division')" />
        <x-backpack::menu-dropdown-item title="Designation" icon="la la-id-badge" :link="backpack_url('designation')" />
        <x-backpack::menu-dropdown-item title="Vertical" icon="la la-bars" :link="backpack_url('vertical')" />

        {{-- IAM: Post & Org Structure --}}
        {{--
        <x-backpack::menu-separator title="Posts & Org Structure" /> --}}
        <x-backpack::menu-dropdown-item title="Posts" icon="la la-briefcase" :link="backpack_url('post')" />
        <x-backpack::menu-dropdown-item title="Designation Dept Tree" icon="la la-sitemap" :link="backpack_url('desig-dept-tree')" />
        <x-backpack::menu-dropdown-item title="Post Reporting Lines" icon="la la-project-diagram" :link="backpack_url('post-reporting')" />
    </x-backpack::menu-dropdown>

    {{-- Vehicles Info Section --}}
    <x-backpack::menu-dropdown title="Vehicles Info" icon="la la-car" nested="true">
        <x-backpack::menu-dropdown-item title="Brand" icon="la la-trademark" :link="backpack_url('brand')" />
        <x-backpack::menu-dropdown-item title="Segment" icon="la la-rectangle-wide" :link="backpack_url('segment')" />
        <x-backpack::menu-dropdown-item title="Sub Segment" icon="la la-rectangle-narrow" :link="backpack_url('sub-segment')" />
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
        <x-backpack::menu-dropdown-item title="Person Contact" icon="la la-phone" :link="backpack_url('person-contact')" />
        <x-backpack::menu-dropdown-item title="Person Address" icon="la la-map-pin" :link="backpack_url('person-address')" />
        <x-backpack::menu-dropdown-item title="Person Banking Detail" icon="la la-university" :link="backpack_url('person-banking-detail')" />
        <x-backpack::menu-dropdown-item title="Garage" icon="la la-warehouse" :link="backpack_url('garage')" />
        <x-backpack::menu-dropdown-item title="User" icon="la la-user" :link="backpack_url('user')" />

        {{-- 4-LEVEL NESTED: Employee Info --}}
        <x-backpack::menu-dropdown title="Employee Info" icon="la la-sitemap" nested="true">
            <x-backpack::menu-dropdown-item title="Employee" icon="la la-user-tie" :link="backpack_url('employee')" />
            <x-backpack::menu-dropdown-item title="Employee Dept Assignment" icon="la la-link" :link="backpack_url('employee-department-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Branch Assignment" icon="la la-link" :link="backpack_url('employee-branch-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Location Assignment" icon="la la-link" :link="backpack_url('employee-location-assignment')" />
            <x-backpack::menu-dropdown-item title="Employee Vertical Assignment" icon="la la-link" :link="backpack_url('employee-vertical-assignment')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <x-backpack::menu-separator title="HR Journey" />

    {{-- HR Section — NEW Sprint 3 --}}
    <x-backpack::menu-dropdown title="HR Operations" icon="la la-user-clock" nested="true">
        <x-backpack::menu-dropdown-item title="Post Assignments" icon="la la-briefcase" :link="backpack_url('emp-post-assignment')" />
        <x-backpack::menu-dropdown-item title="Transfer Employee" icon="la la-exchange-alt" :link="backpack_url('hr/transfer')" />
        <x-backpack::menu-dropdown-item title="Relieve Employee" icon="la la-sign-out-alt" :link="backpack_url('hr/relieve')" />
        <x-backpack::menu-dropdown-item title="Employee Journey" icon="la la-history" :link="backpack_url('hr/journey')" />
    </x-backpack::menu-dropdown>

    {{-- Separator --}}
    <x-backpack::menu-separator title="Access Control" />

    {{-- RBAC Section — Graph/Reporting Hierarchy items REMOVED --}}
    <x-backpack::menu-dropdown title="RBAC" icon="la la-lock" nested="true">
        <x-backpack::menu-dropdown-item title="Modules" icon="la la-cube" :link="backpack_url('modules')" />
        <x-backpack::menu-dropdown-item title="Process" icon="la la-cogs" :link="backpack_url('process')" />
        <x-backpack::menu-dropdown-item title="Role" icon="la la-users" :link="backpack_url('role')" />
        <x-backpack::menu-dropdown-item title="Permission" icon="la la-key" :link="backpack_url('permission')" />
        <x-backpack::menu-dropdown-item title="Post Permission" icon="la la-check-circle" :link="backpack_url('post-permission')" />
        <x-backpack::menu-dropdown-item title="Approval Hierarchy" icon="la la-shield-alt" :link="backpack_url('approval-hierarchy')" />
    </x-backpack::menu-dropdown>

</x-backpack::menu-dropdown>

{{-- USERS --}}
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('user')" />

{{-- SALES MAIN DROPDOWN --}}
<x-backpack::menu-dropdown title="Sales" icon="la la-chart-line">

    {{-- Separator --}}
    <x-backpack::menu-separator title="Sales Configuration" />

    {{-- Price List --}}
    @if (auth()->check() &&
            (auth()->user()->hasPermissionTo('can_view_documents') ||
                auth()->user()->hasRole('super
                                                                                                    admin')))
        <x-backpack::menu-dropdown-item title="Price List" icon="la la-tag" :link="backpack_url('pricing')" />
    @endif



    {{-- Enquiries --}}
    <x-backpack::menu-dropdown title="Enquiries" icon="la la-question-circle" nested="true">
    
        <x-backpack::menu-dropdown-item title="Add New Enquiry" 
        icon="la la-plus-circle" 
        :link="backpack_url('enquiries/add')" />

        <x-backpack::menu-dropdown-item title="Enquiry List" 
        icon="la la-list" 
        :link="backpack_url('enquiries-list')" />

        <x-backpack::menu-dropdown-item title="Reference Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/reference')" />

        <x-backpack::menu-dropdown-item title="Virtual Number Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/virtual-number')" />

        <x-backpack::menu-dropdown-item title="WhatsApp Campaign Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/whatsapp-campaign')" />

        <x-backpack::menu-dropdown-item title="Unassigned Quick Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/unassigned-quick')" />

        <x-backpack::menu-dropdown-item title="Assigned Quick Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/assigned-quick')" />

        <x-backpack::menu-dropdown-item title="Unassigned Long Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/unassigned-long')" />

        <x-backpack::menu-dropdown-item title="Assigned Long Enquiries" 
        icon="la la-user-times" 
        :link="backpack_url('enquiries/assigned-long')" />

        <x-backpack::menu-dropdown-item title="Campaigns" 
        icon="la la-list" 
        :link="backpack_url('campaign')" />

    </x-backpack::menu-dropdown>

    {{-- Quotation --}}

    <x-backpack::menu-dropdown-item title="Quotation" icon="la la-file-alt" :link="backpack_url('quotation-form')" />

    {{-- Booking --}}
    <x-backpack::menu-dropdown title="Booking" icon="la la-book-open" nested="true">
        <x-backpack::menu-dropdown-item title="Add New Booking" icon="la la-plus-circle" :link="backpack_url('booking/create')" />
        <x-backpack::menu-dropdown-item title="Booking List" icon="la la-list" :link="backpack_url('booking')" />

        <x-backpack::menu-separator title="Pending Stages" />
        <x-backpack::menu-dropdown-item title="Pending DMS Booking" icon="la la-database" :link="backpack_url('booking/pending-dms')" />
        <x-backpack::menu-dropdown-item title="Pending Sales Order" icon="la la-file-alt" :link="backpack_url('booking/pending/sales-order')" />
        <x-backpack::menu-dropdown-item title="Pending KYC" icon="la la-id-card" :link="backpack_url('booking/pending-kyc')" />
        <x-backpack::menu-dropdown-item title="Pending Payment" icon="la la-rupee-sign" :link="backpack_url('booking/pending-payment')" />
        <x-backpack::menu-dropdown-item title="Pending Invoices" icon="la la-file-invoice" :link="backpack_url('booking/pending-invoices')" />
        <x-backpack::menu-dropdown-item title="Pending Insurance" icon="la la-shield-alt" :link="backpack_url('booking/pending-insurance')" />
        <x-backpack::menu-dropdown-item title="Pending RTO" icon="la la-car" :link="backpack_url('booking/pending-rto')" />
        <x-backpack::menu-dropdown-item title="Pending Deliveries" icon="la la-truck" :link="backpack_url('booking/pending-deliveries')" />
        <x-backpack::menu-dropdown-item title="Pending Reg. No." icon="la la-hashtag" :link="backpack_url('booking/pending-registration')" />
        <x-backpack::menu-dropdown-item title="Pending DO" icon="la la-file-signature" :link="backpack_url('booking/pending-do')" />
        <x-backpack::menu-dropdown-item title="Dummy Bookings" icon="la la-flask" :link="backpack_url('booking/dummy')" />
        <x-backpack::menu-dropdown title="Erroneous Entries" icon="la la-exclamation-circle" nested="true">

            <x-backpack::menu-dropdown-item title="Booking" icon="la la-book" :link="backpack_url('booking/erroneous-bookings')" />

            <x-backpack::menu-dropdown-item title="Finance" icon="la la-money-bill" :link="backpack_url('finance/erroneous')" />

            <x-backpack::menu-dropdown-item title="Insurance" icon="la la-shield" :link="backpack_url('insurance/erroneous')" />

            <x-backpack::menu-dropdown-item title="RTO" icon="la la-id-card" :link="backpack_url('rto/erroneous')" />

        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Exchange --}}
    <x-backpack::menu-dropdown title="Exchange" icon="la la-exchange-alt" nested="true">
        <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Exchange" icon="la la-check" :link="backpack_url('exchange/enquiry/int-in-exchange')" />
            <x-backpack::menu-dropdown-item title="Int in Scrappage" icon="la la-recycle" :link="backpack_url('exchange/enquiry/int-in-scrappage')" />
            <x-backpack::menu-dropdown-item title="Not Interested" icon="la la-thumbs-down" :link="backpack_url('exchange/enquiry/not-interested')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Exchange" :link="backpack_url('booking/exchange')" />
            <x-backpack::menu-dropdown-item title="Int in Scrappage" :link="backpack_url('booking/scrappage')" />
            <x-backpack::menu-dropdown-item title="Not Interested" :link="backpack_url('booking/exchange/not-interested')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

    {{-- Finance --}}
    <x-backpack::menu-dropdown title="Finance" icon="la la-money-bill" nested="true">
        <x-backpack::menu-dropdown title="Enquiry Stage" icon="la la-question-circle" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Finance" icon="la la-check" :link="backpack_url('finance/enquiry/int-in-finance')" />
            <x-backpack::menu-dropdown-item title="Not Interested" icon="la la-thumbs-down" :link="backpack_url('finance/enquiry/not-interested')" />
        </x-backpack::menu-dropdown>
        <x-backpack::menu-dropdown title="Booking Stage" icon="la la-book-open" nested="true">
            <x-backpack::menu-dropdown-item title="Int in Finance" :link="backpack_url('booking/finance')" />
            <x-backpack::menu-dropdown-item title="Not Interested" :link="backpack_url('booking/finance/not-interested')" />
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


    <x-backpack::menu-dropdown-item title="OTF Form" icon="la la-file-alt" :link="backpack_url('booking/otf-form')" />

    {{-- Co Dealer Transactions --}}
    <x-backpack::menu-dropdown title="Co Dealer Transactions" icon="la la-handshake" nested="true">
        <x-backpack::menu-dropdown-item title="Add Co Dealer" icon="la la-user-plus" :link="backpack_url('co-dealer/add')" />
        <x-backpack::menu-dropdown-item title="Add New Request" icon="la la-plus-circle" :link="backpack_url('co-dealer/new-request')" />
        <x-backpack::menu-dropdown-item title="Pending Requests" icon="la la-clock" :link="backpack_url('co-dealer/pending-requests')" />
        <x-backpack::menu-dropdown-item title="Approved Requests" icon="la la-check-circle" :link="backpack_url('co-dealer/approved')" />
        <x-backpack::menu-dropdown-item title="Pending Transactions" icon="la la-exchange-alt" :link="backpack_url('co-dealer/pending-transactions')" />
        <x-backpack::menu-dropdown-item title="Pending Payment" icon="la la-money-bill-wave" :link="backpack_url('co-dealer/pending-payment')" />
        <x-backpack::menu-dropdown-item title="Dealer Ledger" icon="la la-book" :link="backpack_url('co-dealer/ledger')" />
    </x-backpack::menu-dropdown>

    {{-- Other (Fees) --}}
    @can(['create_fee_collection', 'verify_fee_collection'])
        <x-backpack::menu-dropdown title="Other" icon="la la-file-invoice-dollar" nested="true">
            <x-backpack::menu-dropdown title="Fee Collection" icon="la la-dollar-sign" nested="true">
                <x-backpack::menu-dropdown title="Registration" icon="la la-registered" nested="true">
                    <x-backpack::menu-dropdown-item title="Add Fee" icon="la la-plus-circle" :link="backpack_url('fee-collection/add')" />
                    <x-backpack::menu-dropdown-item title="View List" icon="la la-list-ul" :link="backpack_url('fee-collection')" />
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
            <x-backpack::menu-dropdown-item title="Consolidated Booking" :link="backpack_url('reports/consolidated-booking')" />
            <x-backpack::menu-dropdown-item title="Branch Booking" :link="backpack_url('reports/branch-booking')" />
            <x-backpack::menu-dropdown-item title="Pending Actions" :link="backpack_url('reports/pending-actions')" />
        </x-backpack::menu-dropdown>
    </x-backpack::menu-dropdown>

</x-backpack::menu-dropdown>

{{-- ====================== SPARES MODULE ====================== --}}
<x-backpack::menu-dropdown title="Spares" icon="la la-tools">
    <x-backpack::menu-separator title="Spare Operations" />
    <x-backpack::menu-dropdown-item title="Add New" icon="la la-plus-circle" :link="backpack_url('spare-request/create')" />
    <x-backpack::menu-dropdown-item title="RO Wise List" icon="la la-list" :link="backpack_url('spare-request')" />
    <x-backpack::menu-dropdown-item title="Partwise Requirement" icon="la la-list-alt" :link="backpack_url('spare/partwise-requirement')" />
    <x-backpack::menu-separator title="Reports" />
    <x-backpack::menu-dropdown-item title="Parts Ordering Report" icon="la la-chart-bar" :link="backpack_url('spare/orderingreport')" />
</x-backpack::menu-dropdown>
