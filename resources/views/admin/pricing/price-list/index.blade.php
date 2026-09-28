@extends(backpack_view('blank'))

@section('title', $title)

{{-- Price List landing (DEC-081): one card per list with the vehicles priced on the date. Open to every logged-in user. --}}
@section('content')
@php
    $icons = ['pv' => 'la-car', 'taxi' => 'la-taxi', 'cv' => 'la-truck', 'bev' => 'la-charging-station', 'lmm' => 'la-truck-pickup', 'tzu' => 'la-shuttle-van', 'csd' => 'la-shield-alt'];
    $heldAll = in_array('ALL', $held, true);
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Published prices · NV · default selections</div>
            <h2 class="mb-0">{{ $title }}</h2>
        </div>
        <form method="GET" action="{{ route('pricing.price-list.index') }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label mb-1 small" for="pl-date">Prices as on</label>
                <x-ui.date name="date" id="pl-date" :value="$date" />
            </div>
            <button type="submit" class="btn btn-outline-primary">Show</button>
        </form>
    </div>

    <div class="row g-3">
        @foreach ($lists as $key => $def)
            @php $isHeld = $heldAll || in_array($def['hold'], $held, true); @endphp
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <a href="{{ route('pricing.price-list.show', ['list' => $key, 'date' => $date]) }}" class="card card-link card-link-pop h-100 text-reset text-decoration-none">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar avatar-md bg-primary-lt"><i class="la {{ $icons[$key] ?? 'la-list' }} fs-2" aria-hidden="true"></i></span>
                        <div class="me-auto">
                            <div class="fw-medium">{{ $def['label'] }}</div>
                            <div class="text-body-secondary small">{{ number_format($counts[$key] ?? 0) }} vehicle(s)</div>
                        </div>
                        @if ($isHeld)
                            <span class="badge bg-yellow-lt">On hold</span>
                        @endif
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
