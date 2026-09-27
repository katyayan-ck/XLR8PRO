@extends('admin.dev.ui._layout')

@section('kit')
@php
    $branches = ['JPR' => 'Jaipur', 'AJM' => 'Ajmer', 'KOT' => 'Kota', 'UDR' => 'Udaipur', 'JDH' => 'Jodhpur'];
    $models = ['NEXON' => 'Nexon', 'PUNCH' => 'Punch', 'HARRIER' => 'Harrier', 'SAFARI' => 'Safari', 'TIAGO' => 'Tiago', 'ALTROZ' => 'Altroz'];
@endphp
<form onsubmit="return false" novalidate>
<div class="row row-cards">

    {{-- ============ Text inputs ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Text inputs</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required" for="kit-name">Customer name</label>
                    <input type="text" class="form-control" id="kit-name" placeholder="e.g. Rahul Sharma">
                    <small class="form-hint">As printed on the PAN card.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-mobile">Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text">+91</span>
                        <input type="tel" class="form-control" id="kit-mobile" placeholder="98XXXXXX12" inputmode="numeric">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-search">Input with icon</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="la la-search"></i></span>
                        <input type="search" class="form-control" id="kit-search" placeholder="Search enquiries…">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-amount">Flat input group</label>
                    <div class="input-group input-group-flat">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="kit-amount" value="845000" step="1000">
                        <span class="input-group-text"><kbd>INR</kbd></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-password">Password</label>
                    <div class="input-group input-group-flat">
                        <input type="password" class="form-control" id="kit-password" value="secret-value" autocomplete="off">
                        <span class="input-group-text">
                            <a href="#" class="link-secondary" aria-label="Show password" onclick="var i=document.getElementById('kit-password');i.type=i.type==='password'?'text':'password';return false"><i class="la la-eye"></i></a>
                        </span>
                    </div>
                </div>
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="kit-floating" placeholder="name@example.com">
                    <label for="kit-floating">Floating label (email)</label>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-4"><label class="form-label" for="kit-sm">Small</label><input class="form-control form-control-sm" id="kit-sm" value="sm"></div>
                    <div class="col-sm-4"><label class="form-label" for="kit-md">Default</label><input class="form-control" id="kit-md" value="md"></div>
                    <div class="col-sm-4"><label class="form-label" for="kit-lg">Large</label><input class="form-control form-control-lg" id="kit-lg" value="lg"></div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label" for="kit-ro">Read-only</label><input class="form-control" id="kit-ro" value="BK/JPR/2026/0142" readonly></div>
                    <div class="col-sm-6"><label class="form-label" for="kit-dis">Disabled</label><input class="form-control" id="kit-dis" value="Locked after delivery" disabled></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Validation + textarea ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Validation states &amp; text areas</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="kit-valid">Valid</label>
                    <input type="text" class="form-control is-valid" id="kit-valid" value="ABCDE1234F">
                    <div class="valid-feedback">PAN format looks right.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-invalid">Invalid</label>
                    <input type="text" class="form-control is-invalid" id="kit-invalid" value="12345">
                    <div class="invalid-feedback">Enter a 10-digit mobile number.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-invalid-select">Invalid select</label>
                    <select class="form-select is-invalid" id="kit-invalid-select"><option value="">Choose a branch</option></select>
                    <div class="invalid-feedback">Branch is required.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-remark">Remark <span class="form-label-description">0/500</span></label>
                    <textarea class="form-control" id="kit-remark" rows="3" placeholder="Customer asked for a Saturday test drive…"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kit-auto">Auto-growing text area</label>
                    <textarea class="form-control" id="kit-auto" rows="1" data-bs-toggle="autosize" placeholder="Type several lines…"></textarea>
                </div>
                <div class="alert alert-danger mb-0" role="alert">
                    <div class="d-flex gap-2">
                        <i class="la la-exclamation-circle fs-2"></i>
                        <div>
                            <h4 class="alert-title">Please fix 2 fields</h4>
                            <div class="text-secondary">Summary block at the top of a long form, linked to the failing inputs.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Selects & pickers ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Selects &amp; date pickers <span class="badge bg-green-lt ms-2">shared layer</span></h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="kit-native">Short native select</label>
                    <select class="form-select" id="kit-native">
                        <option>Walk-in</option><option>Website</option><option>Referral</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="xl-kit_branch">Select2 — single, searchable, clearable</label>
                    <x-ui.select name="kit_branch" :options="$branches" selected="JPR" placeholder="All branches" />
                </div>
                <div class="mb-3">
                    <label class="form-label" for="xl-kit_models-">Select2 — multiple (never a list box)</label>
                    <x-ui.select name="kit_models[]" :options="$models" :selected="['NEXON', 'PUNCH']" multiple placeholder="Pick models" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="xl-kit_date">Date (site format, submits ISO)</label>
                        <x-ui.date name="kit_date" :value="now()" />
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="xl-kit_at">Date &amp; time</label>
                        <x-ui.date name="kit_at" :value="now()" time />
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="xl-kit_from">From</label>
                        <x-ui.date name="kit_from" class="form-control-sm" placeholder="From" />
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="xl-kit_to">To</label>
                        <x-ui.date name="kit_to" class="form-control-sm" placeholder="To" />
                    </div>
                </div>
                <pre class="xl-pre xl-code mt-3 mb-0">&lt;x-ui.select name="models[]" :options="$models" multiple placeholder="Pick models" /&gt;
&lt;x-ui.date name="delivery_at" :value="$booking->delivery_at" time /&gt;</pre>
            </div>
        </div>
    </div>

    {{-- ============ Choices ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Checks, radios, switches, select groups</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="form-label">Checkboxes</div>
                        <label class="form-check"><input class="form-check-input" type="checkbox" checked><span class="form-check-label">Finance required</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox"><span class="form-check-label">Exchange vehicle</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" disabled><span class="form-check-label">Disabled</span></label>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="form-label">Radios</div>
                        <label class="form-check"><input class="form-check-input" type="radio" name="kit-pay" checked><span class="form-check-label">Cash</span></label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="kit-pay"><span class="form-check-label">Finance</span></label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="kit-pay"><span class="form-check-label">Lease</span></label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-label">Switches</div>
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" checked><span class="form-check-label">Send WhatsApp updates</span></label>
                    <label class="form-check form-switch form-switch-lg"><input class="form-check-input" type="checkbox"><span class="form-check-label">Large switch</span></label>
                </div>
                <div class="mb-3">
                    <div class="form-label">Select group — pills</div>
                    <div class="form-selectgroup form-selectgroup-pills">
                        @foreach (['Hot', 'Warm', 'Cold', 'Lost'] as $i => $temp)
                            <label class="form-selectgroup-item">
                                <input type="radio" name="kit-temp" value="{{ $temp }}" class="form-selectgroup-input" @checked($i === 0)>
                                <span class="form-selectgroup-label">{{ $temp }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-label">Select group — boxes</div>
                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-sm-row">
                        @foreach (['Petrol' => '₹ 8.45 L', 'Diesel' => '₹ 9.95 L', 'EV' => '₹ 14.49 L'] as $fuel => $price)
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="kit-fuel" value="{{ $fuel }}" class="form-selectgroup-input" @checked($fuel === 'Petrol')>
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <div class="me-3"><span class="form-selectgroup-check"></span></div>
                                    <div><div class="fw-medium">{{ $fuel }}</div><div class="text-secondary small">from {{ $price }}</div></div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-label">Colour input</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['red', 'orange', 'yellow', 'green', 'blue', 'dark'] as $colour)
                            <label class="form-colorinput">
                                <input name="kit-colour" type="radio" value="{{ $colour }}" class="form-colorinput-input" @checked($colour === 'blue') aria-label="{{ $colour }}">
                                <span class="form-colorinput-color bg-{{ $colour }}"></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="form-label" for="kit-range">Discount (%)</label>
                    <input type="range" class="form-range" id="kit-range" min="0" max="10" step="0.5" value="3">
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Uploads ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Uploads <span class="badge bg-green-lt ms-2">drop-zone</span></h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="xl-kit_file">Single file</label>
                    <x-ui.upload name="kit_file" accept=".pdf,image/*" />
                </div>
                <div class="mb-3">
                    <label class="form-label" for="xl-kit_files-">Multiple files — preview, remove before upload</label>
                    <x-ui.upload name="kit_files[]" multiple accept=".pdf,image/*" />
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-xl bg-primary-lt">RS</span>
                    <div>
                        <div class="fw-medium">Profile photo</div>
                        <div class="text-secondary small mb-2">Square, at least 256 px.</div>
                        <button type="button" class="btn btn-sm">Change</button>
                        <button type="button" class="btn btn-sm btn-ghost-danger">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Wizard ============ --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Wizard / steps</h3></div>
            <div class="card-body">
                <ul class="steps steps-counter steps-primary mb-4">
                    <li class="step-item">Customer</li>
                    <li class="step-item active">Vehicle</li>
                    <li class="step-item">Pricing</li>
                    <li class="step-item">Review</li>
                </ul>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="kit-model">Model</label>
                        <select class="form-select" id="kit-model">@foreach ($models as $label) <option>{{ $label }}</option> @endforeach</select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="kit-variant">Variant</label>
                        <select class="form-select" id="kit-variant"><option>Creative + S</option><option>Fearless</option></select>
                    </div>
                </div>
                <div class="hr-text">colour</div>
                <div class="form-selectgroup">
                    @foreach (['Pure Grey', 'Daytona Grey', 'Pristine White', 'Flame Red'] as $i => $shade)
                        <label class="form-selectgroup-item">
                            <input type="radio" name="kit-shade" class="form-selectgroup-input" @checked($i === 2)>
                            <span class="form-selectgroup-label">{{ $shade }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="button" class="btn"><i class="la la-arrow-left me-1"></i>Back</button>
                <button type="button" class="btn btn-primary">Next<i class="la la-arrow-right ms-1"></i></button>
            </div>
        </div>
    </div>

    {{-- ============ Full form ============ --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Full form — new enquiry</h3>
                    <p class="card-subtitle">Two columns on desktop, one on phones; required marks; sections; sticky actions in the footer.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12"><div class="subheader">Customer</div></div>
                    <div class="col-md-6">
                        <label class="form-label required" for="kit-f-name">Full name</label>
                        <input class="form-control" id="kit-f-name" placeholder="As per PAN">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="kit-f-mobile">Mobile</label>
                        <input class="form-control" id="kit-f-mobile" inputmode="numeric" placeholder="10 digits">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="kit-f-email">Email</label>
                        <input type="email" class="form-control" id="kit-f-email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="xl-kit_f_branch">Branch</label>
                        <x-ui.select name="kit_f_branch" :options="$branches" placeholder="Choose a branch" />
                    </div>
                    <div class="col-12"><div class="subheader mt-2">Interest</div></div>
                    <div class="col-md-6">
                        <label class="form-label" for="xl-kit_f_models-">Models</label>
                        <x-ui.select name="kit_f_models[]" :options="$models" multiple placeholder="One or more" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="xl-kit_f_followup">Follow-up</label>
                        <x-ui.date name="kit_f_followup" time />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="kit-f-budget">Budget</label>
                        <div class="input-group"><span class="input-group-text">₹</span><input class="form-control" id="kit-f-budget" inputmode="numeric"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="kit-f-notes">Notes</label>
                        <textarea class="form-control" id="kit-f-notes" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" checked><span class="form-check-label">Customer consents to WhatsApp / SMS updates</span></label>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                <button type="button" class="btn btn-link link-secondary">Cancel</button>
                <button type="button" class="btn">Save draft</button>
                <button type="submit" class="btn btn-primary"><i class="la la-check me-1"></i>Create enquiry</button>
            </div>
        </div>
    </div>
</div>
</form>
@endsection
