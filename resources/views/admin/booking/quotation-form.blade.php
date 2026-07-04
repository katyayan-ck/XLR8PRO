@extends(backpack_view('blank'))

@php
use App\Services\OrgService;
@endphp

@section('title', 'Quotation Form')

@push('after_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

@endpush

@section('content')

<div="quotation-form">
    <div class="container-fluid">
        <div class="page-header">
            <div class="row align-items-center">

                <div class="col-md-6">

                    <h1 class="fw-bold mb-0">
                        Quotation Form
                    </h1>

                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('quotation.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card">

                <div class="card-body">

                    <div class="form-section">
                        {{-- ================= Vehicle Details ================= --}}

                        <div class="row">

                            {{-- Segment --}}
                            <div class="col-md-3 mb-3">
                                <label>Segment <span class="text-danger">*</span></label>

                                <select name="segment_code" id="segment_code" class="form-control form-select">
                                    <option value="">Select Segment</option>

                                    @foreach($segments as $code => $name)
                                    <option value="{{ $code }}">
                                        {{ $name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Model --}}
                            <div class="col-md-3 mb-3">
                                <label>Model <span class="text-danger">*</span></label>

                                <select name="model_code" id="model_code" class="form-control form-select">
                                    <option value="">Select Model</option>
                                </select>
                            </div>

                            {{-- Variant --}}
                            <div class="col-md-3 mb-3">
                                <label>Variant <span class="text-danger">*</span></label>

                                <select name="variant_code" id="variant_code" class="form-control form-select">
                                    <option value="">Select Variant</option>
                                </select>
                            </div>

                            {{-- Color --}}
                            <div class="col-md-3 mb-3">
                                <label>Color <span class="text-danger">*</span></label>

                                <select name="color_code" id="color_code" class="form-control form-select">
                                    <option value="">Select Color</option>
                                </select>
                            </div>

                        </div>

                        <div class="row">

                            {{-- Ex-Showroom Price --}}
                            <div class="col-md-4 mb-3">
                                <label>Ex-Showroom Price</label>

                                <input name="ex_showroom_price" id="ex_showroom_price" class="form-control"
                                    placeholder="Enter Ex-Showroom Price">
                            </div>

                            {{-- Insurance --}}
                            <div class="col-md-4 mb-3">
                                <label>Insurance</label>

                                <select name="policy_type" id="policy_type" class="form-control form-select">
                                    <option value="">Select Insurance</option>

                                    @foreach($insurance_type_map as $key => $value)
                                    <option value="{{ $key }}">
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Registration --}}
                            <div class="col-md-4 mb-3">
                                <label>Registration</label>

                                <select name="registration_type" id="registration_type"
                                    class="form-control form-select">

                                    <option value="">Select Registration</option>

                                    @foreach($registration_type_map as $key => $value)
                                    <option value="{{ $key }}">
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">

                                <label>Accessories</label>

                                <select name="accessories[]" id="accessories" class="form-control form-select" multiple>

                                    @foreach($accessoryList as $accessory)

                                    <option value="{{ $accessory->part_no }}" data-price="{{ $accessory->ndp }}">
                                        {{ $accessory->item }}
                                        (₹{{ number_format($accessory->ndp,2) }})
                                    </option>

                                    @endforeach

                                </select>

                            </div>


                            <div class="col-md-3 mb-3">
                                <label>Accessories Amount</label>

                                <input type="text" id="accessories_amount" class="form-control" readonly value="0.00">
                            </div>

                            {{-- Maxicare --}}
                            <div class="col-md-3 mb-3">
                                <label>Maxicare</label>

                                <input name="maxicare" id="maxicare" class="form-control"
                                    placeholder="Enter Maxicare Amount">
                            </div>

                            {{-- VLTD Device (GPS) --}}
                            <div class="col-md-3 mb-3">
                                <label>VLTD Device (GPS)</label>

                                <input name="vltd_device" id="vltd_device" class="form-control"
                                    placeholder="Enter VLTD Device Amount">
                            </div>

                            {{-- Coating --}}
                            <div class="col-md-3 mb-3">

                                <label>Coating</label>

                                <select id="coating" name="coating" class="form-control form-select">

                                    <option value="">Select Coating</option>
                                    <option value="Ceramic">Ceramic</option>
                                    <option value="Graphene">Graphene</option>
                                    <option value="No Coating">No Coating</option>

                                </select>

                            </div>

                            {{-- Coating Price --}}
                            <div class="col-md-3 mb-3">

                                <label>Coating Price</label>

                                <input id="coating_price" name="coating_price" class="form-control"
                                    placeholder="Enter Coating Price" min="0" step="0.01" disabled>

                            </div>
                            {{-- PPF --}}
                            <div class="col-md-3 mb-3">

                                <label>PPF</label>

                                <input name="ppf" id="ppf" class="form-control" placeholder="Enter PPF Amount" min="0"
                                    step="0.01">

                            </div>

                            {{-- RTO Yellow Tape --}}
                            <div class="col-md-3 mb-3">

                                <label>RTO Yellow Tape</label>

                                <input name="rto_yellow_tape" id="rto_yellow_tape" class="form-control"
                                    placeholder="Enter RTO Yellow Tape Amount" min="0" step="0.01">

                            </div>

                            {{-- Kazam Charging Kit (LMM) --}}
                            <div class="col-md-3 mb-3">

                                <label>Kazam Charging Kit (LMM)</label>

                                <input name="kazam_charging_kit" id="kazam_charging_kit" class="form-control"
                                    placeholder="Enter Kazam Charging Kit Amount" min="0" step="0.01">

                            </div>

                            {{-- Incidental Charges (LMM) --}}
                            <div class="col-md-3 mb-3">

                                <label>Incidental Charges (LMM)</label>

                                <input name="incidental_charges" id="incidental_charges" class="form-control"
                                    placeholder="Enter Incidental Charges" min="0" step="0.01">

                            </div>

                            {{-- Shield --}}
                            <div class="col-md-3 mb-3">

                                <label>Shield</label>

                                <select id="shield" name="shield" class="form-control form-select">

                                    <option value="">Select Shield</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="4th + 5th Year">4th + 5th Year</option>
                                    <option value="No Shield">No Shield</option>

                                </select>

                            </div>

                            {{-- Shield Price --}}
                            <div class="col-md-3 mb-3">

                                <label>Shield Price</label>

                                <input id="shield_price" name="shield_price" class="form-control"
                                    placeholder="Enter Shield Price" min="0" step="0.01" disabled>

                            </div>

                            {{-- RSA --}}
                            <div class="col-md-3 mb-3">

                                <label>RSA</label>

                                <select id="rsa" name="rsa" class="form-control form-select">

                                    <option value="">Select RSA</option>
                                    <option value="1 Year">1 Year</option>
                                    <option value="2 Year">2 Year</option>
                                    <option value="3 Year">3 Year</option>
                                    <option value="4 Year">4 Year</option>
                                    <option value="5 Year">5 Year</option>
                                    <option value="No RSA">No RSA</option>

                                </select>

                            </div>

                            {{-- RSA Amount --}}
                            <div class="col-md-3 mb-3">

                                <label>RSA Amount</label>

                                <input id="rsa_amount" name="rsa_amount" class="form-control"
                                    placeholder="Enter RSA Amount" min="0" step="0.01" disabled>

                            </div>

                            {{-- Fastag --}}
                            <div class="col-md-3 mb-3">

                                <label>Fastag</label>

                                <input name="fastag" id="fastag" class="form-control" placeholder="Enter Fastag Amount"
                                    min="0" step="0.01">

                            </div>

                            {{-- COD Charges --}}
                            <div class="col-md-3 mb-3">

                                <label>COD Charges</label>

                                <input name="cod_charges" id="cod_charges" class="form-control"
                                    placeholder="Enter COD Charges" min="0" step="0.01">

                            </div>

                            {{-- Charger Swapping --}}
                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping</label>

                                <select id="charger_swapping" name="charger_swapping" class="form-control form-select">

                                    <option value="">Select Charger Swapping</option>
                                    <option value="Not Applicable">Not Applicable</option>
                                    <option value="NCH to 7.2 kW">NCH to 7.2 kW</option>
                                    <option value="NCH to 11.2 kW">NCH to 11.2 kW</option>
                                    <option value="7.2 kW to 11.2 kW">7.2 kW to 11.2 kW</option>

                                </select>

                            </div>

                            {{-- Charger Swapping Amount --}}
                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping Amount</label>

                                <input id="charger_swapping_amount" name="charger_swapping_amount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01" disabled>

                            </div>

                            {{-- TCS @ 1% --}}
                            <div class="col-md-3 mb-3">

                                <label>TCS @ 1%</label>

                                <input name="tcs" id="tcs" class="form-control" placeholder="Enter TCS Amount" min="0"
                                    step="0.01">

                            </div>

                            {{-- Total Receivable --}}
                            <div class="col-md-3 mb-3">

                                <label>Total Receivable</label>

                                <input name="total_receivable" id="total_receivable" class="form-control bg-light"
                                    readonly>

                            </div>

                            {{-- OEM Scheme / CSD Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>OEM Scheme / CSD Discount</label>

                                <input name="oem_scheme_discount" id="oem_scheme_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Fame Subsidy (LMM) --}}
                            <div class="col-md-3 mb-3">

                                <label>Fame Subsidy (LMM)</label>

                                <input name="fame_subsidy" id="fame_subsidy" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Exchange Bonus / Green Bonus / Loyalty Bonus --}}
                            <div class="col-md-3 mb-3">

                                <label>Exchange Bonus / Green Bonus / Loyalty Bonus</label>

                                <input name="exchange_bonus" id="exchange_bonus" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Corporate Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Corporate Discount</label>

                                <input name="corporate_discount" id="corporate_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Accessories Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Accessories Discount</label>

                                <input name="accessories_discount" id="accessories_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Ceramic Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Ceramic Discount</label>

                                <input name="ceramic_discount" id="ceramic_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- PPF Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>PPF Discount</label>

                                <input name="ppf_discount" id="ppf_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Other Discount - Dealer --}}
                            <div class="col-md-3 mb-3">

                                <label>Other Discount - Dealer</label>

                                <input name="dealer_discount" id="dealer_discount" class="form-control"
                                    placeholder="Enter Amount" min="0" step="0.01">

                            </div>

                            {{-- Charger Swapping Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping Discount</label>

                                <input id="charger_swapping_discount" name="charger_swapping_discount"
                                    class="form-control" placeholder="Enter Amount" min="0" step="0.01" disabled>

                            </div>

                            {{-- Total Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Total Discount</label>

                                <input id="total_discount" name="total_discount" class="form-control bg-light" readonly>

                            </div>

                            {{-- Net Receivable --}}
                            <div class="col-md-3 mb-3">

                                <label>Net Receivable</label>

                                <input id="net_receivable_summary" name="net_receivable_summary"
                                    class="form-control bg-light" readonly>

                            </div>




                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    </div=>

    @endsection

    @push('after_scripts')
    {{--
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"> --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function () {

    $('#accessories').select2({
        placeholder: 'Select Accessories',
        width: '100%',
        closeOnSelect: false
    });

});
    </script>
    <script>
        function updateAccessoriesAmount() {

    let total = 0;

    $('#accessories option:selected').each(function () {

        total += parseFloat($(this).data('price')) || 0;

    });

    $('#accessories_amount').val(total.toFixed(2));

    calculateQuotation();

}

$('#accessories').on(
    'change select2:select select2:unselect',
    updateAccessoriesAmount
);

$(document).ready(function () {

    updateAccessoriesAmount();

});

$('#coating').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No Coating') {

        $('#coating_price')
            .val('')
            .prop('disabled', true);

    } else {

        $('#coating_price')
            .prop('disabled', false)
            .focus();

    }

});

// Page load par bhi apply ho jaye
$('#coating').trigger('change');

$('#shield').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No Shield') {

        $('#shield_price')
            .val('')
            .prop('disabled', true);

    } else {

        $('#shield_price')
            .prop('disabled', false)
            .focus();

    }

});

// Initial state
$('#shield').trigger('change');

$('#rsa').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No RSA') {

        $('#rsa_amount')
            .val('')
            .prop('disabled', true);

    } else {

        $('#rsa_amount')
            .prop('disabled', false)
            .focus();

    }

});

// Initial state
$('#rsa').trigger('change');

$('#charger_swapping').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'Not Applicable') {

        $('#charger_swapping_amount')
            .val('')
            .prop('disabled', true);

    } else {

        $('#charger_swapping_amount')
            .prop('disabled', false)
            .focus();

    }

});

// Initial state
$('#charger_swapping').trigger('change');
$('#charger_swapping').on('change', function () {

    const enabled = $(this).val() !== '' &&
                    $(this).val() !== 'Not Applicable';

    $('#charger_swapping_amount')
        .prop('disabled', !enabled);

    $('#charger_swapping_discount')
        .prop('disabled', !enabled);

    if (!enabled) {
        $('#charger_swapping_amount').val('');
        $('#charger_swapping_discount').val('');
    }

});

$('#charger_swapping').trigger('change');

function num(id) {
    return parseFloat($('#' + id).val()) || 0;
}

function calculateQuotation() {

    // Total Receivable
    let totalReceivable =
        num('ex_showroom_price') +
        num('accessories_amount') +
        num('maxicare') +
        num('vltd_device') +
        num('coating_price') +
        num('ppf') +
        num('rto_yellow_tape') +
        num('kazam_charging_kit') +
        num('incidental_charges') +
        num('shield_price') +
        num('rsa_amount') +
        num('fastag') +
        num('cod_charges') +
        num('charger_swapping_amount') +
        num('tcs');

    $('#total_receivable').val(totalReceivable.toFixed(2));

    // Total Discount
    let totalDiscount =
        num('oem_scheme_discount') +
        num('fame_subsidy') +
        num('exchange_bonus') +
        num('corporate_discount') +
        num('accessories_discount') +
        num('ceramic_discount') +
        num('ppf_discount') +
        num('dealer_discount') +
        num('charger_swapping_discount');

    $('#total_discount').val(totalDiscount.toFixed(2));

    // Net Receivable
    let netReceivable = totalReceivable - totalDiscount;

    $('#net_receivable_summary').val(netReceivable.toFixed(2));
}

// Recalculate whenever any input changes
$(document).on(
    'keyup change',
    '#ex_showroom_price,' +
    '#accessories_amount,' +
    '#maxicare,' +
    '#vltd_device,' +
    '#coating_price,' +
    '#ppf,' +
    '#rto_yellow_tape,' +
    '#kazam_charging_kit,' +
    '#incidental_charges,' +
    '#shield_price,' +
    '#rsa_amount,' +
    '#fastag,' +
    '#cod_charges,' +
    '#charger_swapping_amount,' +
    '#tcs,' +
    '#oem_scheme_discount,' +
    '#fame_subsidy,' +
    '#exchange_bonus,' +
    '#corporate_discount,' +
    '#accessories_discount,' +
    '#ceramic_discount,' +
    '#ppf_discount,' +
    '#dealer_discount,' +
    '#charger_swapping_discount',
    calculateQuotation
);

// Accessories selection change
$('#accessories').on('change', function () {
    updateAccessoriesAmount();
    calculateQuotation();
});

// Initial calculation
$(document).ready(function () {
    calculateQuotation();
});
$(document).ready(function () {

    $('#accessories').select2({

        placeholder: 'Select Accessories',

        width: '100%',

        closeOnSelect: false

    });

});

// Segment -> Model
$('#segment_code').on('change', function () {

    let segment = $(this).val();

    $('#model_code').html('<option value="">Loading...</option>');
    $('#variant_code').html('<option value="">Select Variant</option>');
    $('#color_code').html('<option value="">Select Color</option>');

    if(segment==''){
        $('#model_code').html('<option value="">Select Model</option>');
        return;
    }

    $.get("{{ backpack_url('get-models') }}/" + segment, function(res){

    let option = '<option value="">Select Model</option>';

    $.each(res, function(index, model){

        option += `<option value="${model.code}">
                        ${model.name}
                    </option>`;
    });

    $('#model_code').html(option);

});

});


// Model -> Variant
$('#model_code').on('change', function () {

    let model=$(this).val();

    $('#variant_code').html('<option value="">Loading...</option>');
    $('#color_code').html('<option value="">Select Color</option>');

    if(model==''){
        $('#variant_code').html('<option value="">Select Variant</option>');
        return;
    }

    $.get("{{ backpack_url('get-variants') }}/" + model, function(res){

    let option = '<option value="">Select Variant</option>';

    $.each(res, function(index, variant){

        option += `<option value="${variant.code}">
                        ${variant.name}
                    </option>`;
    });

    $('#variant_code').html(option);

});

});


// Variant -> Color
$('#variant_code').on('change', function () {

    let variant=$(this).val();

    $('#color_code').html('<option value="">Loading...</option>');

    if(variant==''){
        $('#color_code').html('<option value="">Select Color</option>');
        return;
    }

    $.get("{{ backpack_url('get-colors') }}/" + variant, function(res){

    let option = '<option value="">Select Color</option>';

    $.each(res, function(index, color){

        option += `<option value="${color.code}">
                        ${color.name}
                    </option>`;
    });

    $('#color_code').html(option);

});

});
    </script>
    @endpush