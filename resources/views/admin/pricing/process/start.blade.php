@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Start a pricing process (DEC-073, step 1): the Pricing workbook, the price lists to read (CSD optional — it never
    creates vehicles), the WEF date, and optional holds applied at once (undone if the process is discarded).
--}}
@section('content')
@php
    $checkedLists = old('lists', ['PV', 'CV', 'BEV', 'LMM', 'LMM_TZU']);
    $checkedHolds = old('hold_lists', []);
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="mb-0 me-auto">{{ $title }}</h2>
        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link px-0"><i class="la la-arrow-left me-1"></i>Back</a>
    </div>

    @if ($legacyCodes > 0)
        <div class="alert alert-warning" role="alert">
            <strong>{{ number_format($legacyCodes) }} vehicle(s) use the old code format</strong> (OEM code without the colour suffix).
            Detect will treat them as new and create duplicates. Purge and re-import the vehicle masters first (DEC-074, BUG-199).
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('pricing.workflow.start') }}" method="POST" enctype="multipart/form-data" id="pp-start">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Workbook &amp; price lists</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required" for="xl-file">{{ __('pricing.fields.file') }} (.xlsx)</label>
                            <x-ui.upload name="file" accept=".xlsx" id="xl-file" :max-kb="20480" required />
                            <div class="form-hint">Only the <em>Price List</em> sheets are read; new model codes become incomplete vehicles.</div>
                        </div>

                        <fieldset class="mb-3">
                            <legend class="form-label required">{{ __('pricing.fields.lists') }}</legend>
                            <div class="row g-2">
                                @foreach ($lists as $list)
                                    <div class="col-12 col-sm-6">
                                        <label class="form-check" for="pp-list-{{ $list }}">
                                            <input class="form-check-input" type="checkbox" name="lists[]" value="{{ $list }}" id="pp-list-{{ $list }}"
                                                   @checked(in_array($list, $checkedLists, true))>
                                            <span class="form-check-label">{{ __("pricing.lists.$list") }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-hint">CSD only updates CSD prices of vehicles already in the master; leave it out when it has not changed.</div>
                        </fieldset>

                        <div class="mb-0">
                            <label class="form-label required" for="xl-wef_date">{{ __('pricing.fields.wef_date') }}</label>
                            <x-ui.date name="wef_date" :value="old('wef_date', now()->toDateString())" required />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Hold (optional)</h3></div>
                    <div class="card-body">
                        <p class="text-body-secondary small">A held list is not calculated, and quotations and bookings for its vehicles are frozen until it is reopened at the end of the process.</p>
                        @if ($heldLists !== [])
                            <div class="alert alert-warning py-2 small" role="status">Already on hold: {{ implode(', ', $heldLists) }}</div>
                        @endif
                        <div class="row g-2">
                            @foreach ($holdLists as $code => $label)
                                <div class="col-12 col-sm-6">
                                    <label class="form-check" for="pp-hold-{{ $code }}">
                                        <input class="form-check-input" type="checkbox" name="hold_lists[]" value="{{ $code }}" id="pp-hold-{{ $code }}"
                                               @checked(in_array($code, $checkedHolds, true)) @if ($code === 'ALL') data-hold-all @endif>
                                        <span class="form-check-label">{{ $code === 'ALL' ? 'Hold all' : $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" id="pp-submit"><i class="la la-play me-1"></i>Start &amp; detect</button>
                <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    const form = document.getElementById('pp-start');
    const all = form.querySelector('[data-hold-all]');
    const others = Array.from(form.querySelectorAll('input[name="hold_lists[]"]:not([data-hold-all])'));
    function sync() {
        others.forEach(function (el) { el.disabled = all.checked; if (all.checked) { el.checked = false; } });
    }
    all.addEventListener('change', sync);
    sync();
    form.addEventListener('submit', function (e) {
        if (!form.querySelector('input[name="lists[]"]:checked')) {
            e.preventDefault();
            form.querySelector('input[name="lists[]"]').focus();
            form.querySelector('input[name="lists[]"]').setCustomValidity('Choose at least one price list.');
            form.querySelector('input[name="lists[]"]').reportValidity();
            return;
        }
        document.getElementById('pp-submit').disabled = true;
    });
    form.querySelectorAll('input[name="lists[]"]').forEach(function (el) {
        el.addEventListener('change', function () { form.querySelector('input[name="lists[]"]').setCustomValidity(''); });
    });
})();
</script>
@endpush
