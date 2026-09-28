@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Price lookup (DEC-073 step 11): getPricing for one OEM code with the API's selections — breakdown + raw contract JSON.
--}}
@section('content')
@php
    $p = $pricing;
    $inr = fn ($v) => '₹'.number_format((float) $v, 0);
    $ins = $input['insurance'] ?? [];
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Pricing</div>
            <h2 class="mb-0">{{ $title }}</h2>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('pricing.lookup') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label required" for="pl-code">OEM code (with colour)</label>
                    <input type="text" class="form-control" name="oem_code" id="pl-code" value="{{ $input['oem_code'] ?? '' }}" required autocomplete="off">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="pl-permit">Permit</label>
                    <input type="text" class="form-control" name="permit" id="pl-permit" value="{{ $input['permit'] ?? '' }}" placeholder="Vehicle's own">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="pl-vin">VIN type</label>
                    <select class="form-select" name="vin_type" id="pl-vin">
                        @foreach (['NV', 'OV'] as $o)<option value="{{ $o }}" @selected(($input['vin_type'] ?? 'NV') === $o)>{{ $o }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="pl-channel">Channel</label>
                    <select class="form-select" name="channel" id="pl-channel">
                        @foreach (['normal' => 'Normal', 'csd' => 'CSD'] as $o => $l)<option value="{{ $o }}" @selected(($input['channel'] ?? 'normal') === $o)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="pl-date">Price date</label>
                    <x-ui.date name="wef_date" id="pl-date" :value="$input['wef_date'] ?? null" />
                </div>
            </div>
            @if ($p)
                <div class="row g-3 mt-1">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-rsa">RSA</label>
                        <select class="form-select" name="rsa_years" id="pl-rsa">
                            <option value="">Default</option>
                            <option value="0" @selected(($input['rsa_years'] ?? null) === '0')>None</option>
                            @foreach ($p['rsa']['options'] as $o)<option value="{{ $o['years'] }}" @selected((string) ($input['rsa_years'] ?? '') === (string) $o['years'])>{{ $o['years'] }} yr · {{ $inr($o['amount']) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-shield">Shield</label>
                        <select class="form-select" name="shield_scheme" id="pl-shield">
                            <option value="">Default</option>
                            <option value="0" @selected(($input['shield_scheme'] ?? null) === '0')>None</option>
                            @foreach ($p['shield']['options'] as $o)<option value="{{ $o['scheme'] }}" @selected((string) ($input['shield_scheme'] ?? '') === (string) $o['scheme'])>{{ $o['name'] }} · {{ $inr($o['amount']) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-ins-co">Insurer</label>
                        <select class="form-select" name="insurance[company]" id="pl-ins-co">
                            <option value="">Default</option>
                            @foreach ($p['insurance']['companies'] as $c)<option value="{{ $c['company'] }}" @selected(($ins['company'] ?? '') === $c['company'])>{{ $c['company'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-ins-plan">Plan</label>
                        <select class="form-select" name="insurance[plan]" id="pl-ins-plan">
                            <option value="">Default</option>
                            @foreach (collect($p['insurance']['companies'])->pluck('plans')->flatten(1)->pluck('plan')->unique() as $plan)<option value="{{ $plan }}" @selected(($ins['plan'] ?? '') === (string) $plan)>{{ $plan }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-exch">Exchange</label>
                        <select class="form-select" name="exchange" id="pl-exch">
                            <option value="">None</option>
                            @foreach ($p['discounts']['exchange']['options'] as $o)<option value="{{ $o['scheme'] }}" @selected(($input['exchange'] ?? '') === $o['scheme'])>{{ $o['scheme'] }} · {{ $inr($o['total']) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-corp">Corporate</label>
                        <select class="form-select" name="corporate" id="pl-corp">
                            <option value="">None</option>
                            @foreach ($p['discounts']['corporate']['options'] as $o)<option value="{{ $o['category'] }}" @selected(($input['corporate'] ?? '') === $o['category'])>{{ $o['category'] }} · {{ $inr($o['total']) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="pl-reg">Registration</label>
                        <select class="form-select" name="reg_type" id="pl-reg">
                            @foreach (['Regular', 'BH'] as $o)<option value="{{ $o }}" @selected(($input['reg_type'] ?? 'Regular') === $o)>{{ $o }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end gap-3">
                        <label class="form-check mb-2" for="pl-outside">
                            <input class="form-check-input" type="checkbox" name="outside_state" value="1" id="pl-outside" @checked(! empty($input['outside_state']))>
                            <span class="form-check-label">Outside state</span>
                        </label>
                        <label class="form-check mb-2" for="pl-cod">
                            <input class="form-check-input" type="checkbox" name="include_cod" value="1" id="pl-cod" @checked(! empty($input['include_cod']))>
                            <span class="form-check-label">Include COD</span>
                        </label>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="la la-search me-1"></i>{{ $p ? 'Recalculate' : 'Look up' }}</button>
            @if ($p)
                <a href="{{ route('pricing.lookup', array_filter(['oem_code' => $input['oem_code'] ?? null])) }}" class="btn btn-link">Reset selections</a>
            @endif
        </div>
    </form>

    @if ($result && ! $p)
        <div class="alert alert-warning" role="alert">{{ $result->message }}</div>
    @endif

    @if ($p)
        @if ($p['hold'])
            <div class="alert alert-warning" role="alert"><i class="la la-pause-circle me-1"></i>{{ $result->message }}</div>
        @endif
        @foreach ($p['errors'] as $error)
            <div class="alert alert-danger py-2" role="alert">{{ $error }}</div>
        @endforeach

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-md">
                        <div class="text-body-secondary small">{{ $p['segment'] }} · {{ $p['price_list'] }} · {{ $p['permit'] }} · {{ $p['vin_type'] }} · {{ strtoupper($p['channel']) }} · WEF {{ site_date($p['wef_date']) }}</div>
                        <div class="h3 mb-0">{{ $p['custom_model'] }} {{ $p['display_name'] }}</div>
                        <div class="text-body-secondary">{{ $p['oem_code'] }} · {{ $p['colour'] }} · {{ $p['fuel'] }}</div>
                    </div>
                    <div class="col-auto text-end">
                        <div class="text-body-secondary small">On-road</div>
                        <div class="h1 mb-0 text-primary">{{ $inr($p['on_road']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Build-up</h3></div>
                    <table class="table table-sm table-vcenter card-table">
                        <tbody>
                            @foreach ([
                                ['Ex-showroom', $p['ex_showroom']],
                                ['Dealer charges'.($p['dealer_charges']['cod_in_total'] ? ' (incl. COD)' : ''), $p['dealer_charges']['total']],
                                ['RSA'.($p['rsa']['selected_years'] ? ' · '.$p['rsa']['selected_years'].' yr' : ''), $p['rsa']['selected_amount']],
                                ['Shield'.($p['shield']['selected_scheme'] ? ' · scheme '.$p['shield']['selected_scheme'] : ''), $p['shield']['selected_amount']],
                                ['Accessories', $p['accessories']['amount']],
                                ['Insurance · '.$p['insurance']['default']['company'].' '.$p['insurance']['default']['plan'], $p['insurance']['default']['total']],
                                ['RTO · '.$p['rto']['rto_permit'].' '.$p['rto']['reg_type'], $p['rto']['total']],
                                ['TCS'.($p['tcs']['applicable'] ? ' · '.$p['tcs']['rate'].'%' : ''), $p['tcs']['amount']],
                            ] as [$label, $value])
                                <tr><td>{{ $label }}</td><td class="text-end">{{ $inr($value) }}</td></tr>
                            @endforeach
                            <tr class="text-danger"><td>Less discounts</td><td class="text-end">−{{ $inr($p['discounts']['total']) }}</td></tr>
                            <tr class="fw-bold"><td>On-road</td><td class="text-end">{{ $inr($p['on_road']) }}</td></tr>
                            <tr class="text-body-secondary"><td>Invoice value</td><td class="text-end">{{ $inr($p['invoice_value']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Discounts</h3></div>
                    <table class="table table-sm table-vcenter card-table">
                        <tbody>
                            @foreach (['consumer_scheme' => 'Consumer scheme', 'cash' => 'Cash', 'accessory' => 'Accessory', 'shield' => 'Shield', 'rsa' => 'RSA'] as $key => $label)
                                <tr><td>{{ $label }}</td><td class="text-end">{{ $inr($p['discounts'][$key]) }}</td></tr>
                            @endforeach
                            <tr><td>Exchange{{ $p['discounts']['exchange']['selected'] ? ' · '.$p['discounts']['exchange']['selected'] : '' }}</td><td class="text-end">{{ $inr($p['discounts']['exchange']['amount']) }}</td></tr>
                            <tr><td>Corporate{{ $p['discounts']['corporate']['selected'] ? ' · '.$p['discounts']['corporate']['selected'] : '' }}</td><td class="text-end">{{ $inr($p['discounts']['corporate']['amount']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">RTO &amp; insurance</h3></div>
                    <table class="table table-sm table-vcenter card-table">
                        <tbody>
                            @foreach (['tax' => 'Road tax', 'surcharge' => 'Surcharge', 'hypothecation' => 'Hypothecation', 'green_tax' => 'Green tax', 'registration_fee' => 'Registration', 'duplicate_tax_card' => 'Tax card', 'fitness' => 'Fitness'] as $key => $label)
                                @if ((float) $p['rto'][$key] !== 0.0)
                                    <tr><td>RTO · {{ $label }}</td><td class="text-end">{{ $inr($p['rto'][$key]) }}</td></tr>
                                @endif
                            @endforeach
                            @foreach (['od' => 'OD', 'tp' => 'TP', 'addons_total' => 'Add-ons ('.implode(', ', $p['insurance']['default']['addons']).')', 'gst' => 'GST'] as $key => $label)
                                <tr><td>Insurance · {{ $label }}</td><td class="text-end">{{ $inr($p['insurance']['default'][$key]) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <details class="card mt-3">
            <summary class="card-header"><span class="card-title">Raw contract JSON (v{{ $p['contract_version'] }})</span></summary>
            <pre class="card-body mb-0 small" style="max-height: 32rem">{{ json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @endif
</div>
@endsection
