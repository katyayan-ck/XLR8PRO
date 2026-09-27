@extends('admin.dev.ui._layout')

@section('kit')
<div class="row row-cards">

    {{-- Profile header --}}
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-auto"><span class="avatar avatar-xl bg-primary-lt xl-avatar">PM<span class="badge bg-success"></span></span></div>
                    <div class="col">
                        <h2 class="mb-1">Priya Mehta</h2>
                        <div class="text-secondary"><i class="la la-id-badge me-1"></i>Senior Sales Consultant · <i class="la la-map-marker ms-1 me-1"></i>Jaipur · <i class="la la-calendar ms-1 me-1"></i>Joined @sitedate('2022-04-11')</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge bg-blue-lt">Nexon specialist</span><span class="badge bg-green-lt">Top performer Q2</span><span class="badge bg-purple-lt">Finance certified</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <a href="#" class="btn"><i class="la la-envelope me-1"></i>Message</a>
                        <a href="#" class="btn btn-primary"><i class="la la-pen me-1"></i>Edit profile</a>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row text-center g-2">
                    @foreach (['Enquiries' => 312, 'Bookings' => 58, 'Deliveries' => 51, 'CSI' => '94%'] as $label => $value)
                        <div class="col-6 col-md-3"><div class="h2 mb-0">{{ $value }}</div><div class="text-secondary small">{{ $label }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Settings layout --}}
    <div class="col-12">
        <div class="card">
            <div class="row g-0">
                <div class="col-12 col-md-3 border-end">
                    <div class="card-body">
                        <h4 class="subheader">Settings</h4>
                        <div class="list-group list-group-transparent">
                            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center active">My account</a>
                            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">Notifications</a>
                            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">Security</a>
                            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">Devices</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-9 d-flex flex-column">
                    <div class="card-body">
                        <h2 class="mb-4">My account</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="kit-s-name">Display name</label><input class="form-control" id="kit-s-name" value="Priya Mehta"></div>
                            <div class="col-md-6"><label class="form-label" for="kit-s-email">Email</label><input type="email" class="form-control" id="kit-s-email" value="priya@example.com"></div>
                        </div>
                        <h3 class="card-title mt-4">Notifications</h3>
                        <div class="divide-y">
                            @foreach (['New enquiry assigned' => true, 'Approval needed' => true, 'Daily summary email' => false] as $label => $on)
                                <label class="row"><span class="col">{{ $label }}</span><span class="col-auto"><label class="form-check form-check-single form-switch"><input class="form-check-input" type="checkbox" @checked($on) aria-label="{{ $label }}"></label></span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer bg-transparent mt-auto">
                        <div class="btn-list justify-content-end"><a href="#" class="btn">Cancel</a><a href="#" class="btn btn-primary">Save</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kanban --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Kanban — delivery pipeline</h3></div>
            <div class="card-body">
                <div class="xl-kanban">
                    @foreach (['Booked' => [['Rahul Sharma', 'Nexon', 'bg-blue-lt'], ['Kavita Rao', 'Tiago', 'bg-blue-lt']], 'Finance' => [['Sneha Joshi', 'Tiago', 'bg-orange-lt']], 'RTO' => [['Arjun Gupta', 'Harrier', 'bg-purple-lt'], ['Pooja Nair', 'Punch', 'bg-purple-lt']], 'Ready' => [['Anita Verma', 'Punch', 'bg-green-lt']]] as $col => $cards)
                        <div class="xl-kanban-col">
                            <div class="d-flex align-items-center mb-2"><span class="fw-medium">{{ $col }}</span><span class="badge bg-secondary-lt ms-2">{{ count($cards) }}</span></div>
                            @foreach ($cards as [$who, $model, $bg])
                                <div class="card card-sm mb-2">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-1"><span class="fw-medium">{{ $who }}</span><span class="badge {{ $bg }} ms-auto">{{ $model }}</span></div>
                                        <div class="text-secondary small">Due @sitedate(now()->addDays(3))</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Invoice --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Invoice</h3>
                <div class="card-actions"><button type="button" class="btn" onclick="window.print()"><i class="la la-print me-1"></i>Print</button></div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-6"><p class="h3">BMPL Motors</p><address class="text-secondary mb-0">Tonk Road<br>Jaipur 302018<br>GSTIN 08AAACB1234F1Z5</address></div>
                    <div class="col-6 text-end"><p class="h3">Rahul Sharma</p><address class="text-secondary mb-0">Malviya Nagar<br>Jaipur 302017<br>+91 98XXXXXX12</address></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-transparent">
                        <thead><tr><th class="text-center w-1">#</th><th>Item</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach (['Ex-showroom — Nexon Creative +' => 845000, 'RTO & registration' => 62300, 'Insurance (1+3)' => 31400, 'Accessories pack' => 18500] as $item => $amount)
                                <tr><td class="text-center">{{ $loop->iteration }}</td><td>{{ $item }}</td><td class="text-end">₹ {{ number_format($amount) }}</td></tr>
                            @endforeach
                            <tr><td colspan="2" class="strong text-end">Subtotal</td><td class="text-end">₹ 9,57,200</td></tr>
                            <tr><td colspan="2" class="strong text-end">Discount</td><td class="text-end text-green">− ₹ 15,000</td></tr>
                            <tr><td colspan="2" class="fw-bold text-uppercase text-end">Total</td><td class="fw-bold text-end">₹ 9,42,200</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-secondary text-center mt-4 mb-0">Thank you for choosing BMPL Motors.</p>
            </div>
        </div>
    </div>

    {{-- Error / empty --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="empty py-3">
                    <div class="empty-header">404</div>
                    <p class="empty-title">Oops… this page is not here</p>
                    <p class="empty-subtitle text-secondary">The record may have been deleted or you may not have access.</p>
                    <div class="empty-action"><a href="#" class="btn btn-primary"><i class="la la-arrow-left me-1"></i>Take me home</a></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="empty py-3">
                    <div class="empty-icon"><i class="la la-lock display-6 text-secondary"></i></div>
                    <p class="empty-title">You don't have access</p>
                    <p class="empty-subtitle text-secondary">Ask your manager for the <code>SLS_BKNG_VIEW</code> permission.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Pricing --}}
    @foreach ([['Essential', '₹ 21,000', ['Standard warranty', 'Free first service', 'Road-side assistance 1 yr'], false], ['Plus', '₹ 34,500', ['Extended warranty 5 yr', '3 free services', 'Road-side assistance 3 yr', 'Ceramic coating'], true], ['Max', '₹ 49,900', ['Extended warranty 7 yr', 'All services free 3 yr', 'Road-side assistance 5 yr', 'Ceramic coating', 'Accessories voucher'], false]] as [$plan, $price, $features, $featured])
        <div class="col-md-4">
            <div class="card card-md h-100">
                @if ($featured) <div class="ribbon ribbon-top ribbon-bookmark bg-green"><i class="la la-star"></i></div> @endif
                <div class="card-body text-center d-flex flex-column">
                    <div class="text-uppercase text-secondary fw-medium">{{ $plan }}</div>
                    <div class="display-6 fw-bold my-3">{{ $price }}</div>
                    <ul class="list-unstyled lh-lg flex-fill">
                        @foreach ($features as $feature) <li><i class="la la-check text-green me-1"></i>{{ $feature }}</li> @endforeach
                    </ul>
                    <div class="mt-3"><a href="#" class="btn {{ $featured ? 'btn-green' : '' }} w-100">Choose {{ $plan }}</a></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
