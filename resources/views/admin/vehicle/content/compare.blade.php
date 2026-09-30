@extends(backpack_view('blank'))

@section('title', $title)

@php
    $chosenV = array_map('strtoupper', $input['v'] ?? []);
    $chosenM = array_map('strtoupper', $input['m'] ?? []);
    $segment = strtoupper($input['segment'] ?? ($models->firstWhere('code', $chosenM[0] ?? null)?->segment_code ?? ''));
@endphp

@section('content')
{{-- Compare vehicles (DEC-092): trims of one model by features, or models of one segment by specifications. --}}
<div class="xl-page-head d-print-none">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Vehicles</div>
        <h2 class="mb-0 fw-bold">{{ $title }}</h2>
    </div>
    @if ($result?->ok)
        <div class="xl-toolbar"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="la la-print me-1"></i> Print</button></div>
    @endif
</div>

<form method="GET" action="{{ route('vehicle.compare') }}" class="card mb-3 d-print-none" id="compareForm">
    <div class="card-body">
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="What to compare">
            <input type="radio" class="btn-check" name="mode" id="mode-v" value="variants" @checked($mode === 'variants') onchange="this.form.submit()">
            <label class="btn btn-outline-primary" for="mode-v">Variants of one model (features)</label>
            <input type="radio" class="btn-check" name="mode" id="mode-m" value="models" @checked($mode === 'models') onchange="this.form.submit()">
            <label class="btn btn-outline-primary" for="mode-m">Models of one segment (specifications)</label>
        </div>

        @if ($mode === 'variants')
            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <label for="cmp-model" class="form-label">Model</label>
                    <select id="cmp-model" name="model" class="form-select form-select-sm" data-xl="select2" onchange="this.form.querySelectorAll('input[name=&quot;v[]&quot;]').forEach(c => c.checked = false); this.form.submit()">
                        <option value="">Choose a model…</option>
                        @foreach ($models->groupBy('segment_code') as $seg => $group)
                            <optgroup label="{{ $seg }}">
                                @foreach ($group as $m)
                                    <option value="{{ $m->code }}" @selected(strtoupper($input['model'] ?? '') === strtoupper($m->code))>{{ $m->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-8">
                    <div class="form-label">Trims (choose 2–{{ $max }})</div>
                    @forelse ($trims as $t)
                        <label class="form-check form-check-inline">
                            <input type="checkbox" class="form-check-input xl-cmp-pick" name="v[]" value="{{ $t->code }}" @checked(in_array(strtoupper($t->code), $chosenV, true))>
                            <span class="form-check-label">{{ $t->getAttribute('label') }}</span>
                        </label>
                    @empty
                        <div class="small text-body-secondary">Choose a model first.</div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="row g-2">
                <div class="col-12 col-md-3">
                    <label for="cmp-segment" class="form-label">Segment</label>
                    <select id="cmp-segment" name="segment" class="form-select form-select-sm" onchange="this.form.querySelectorAll('input[name=&quot;m[]&quot;]').forEach(c => c.checked = false); this.form.submit()">
                        <option value="">Choose…</option>
                        @foreach ($models->pluck('segment_code')->unique() as $seg)
                            <option value="{{ $seg }}" @selected($segment === strtoupper((string) $seg))>{{ $seg }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-9">
                    <div class="form-label">Models (choose 2–{{ $max }})</div>
                    @php($segModels = $models->filter(fn ($m) => strtoupper((string) $m->segment_code) === $segment))
                    @forelse ($segModels as $m)
                        <label class="form-check form-check-inline">
                            <input type="checkbox" class="form-check-input xl-cmp-pick" name="m[]" value="{{ $m->code }}" @checked(in_array(strtoupper($m->code), $chosenM, true))>
                            <span class="form-check-label">{{ $m->name }}</span>
                        </label>
                    @empty
                        <div class="small text-body-secondary">Choose a segment first.</div>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <label class="form-check form-switch mb-0">
                <input type="checkbox" class="form-check-input" name="diff" value="1" @checked($input['diff'] ?? false)>
                <span class="form-check-label">Only differences</span>
            </label>
            <button class="btn btn-primary btn-sm"><i class="la la-columns me-1"></i> Compare</button>
            <span class="small text-body-secondary" id="cmpCount"></span>
        </div>
    </div>
</form>

@if ($result && ! $result->ok)
    <div class="alert alert-warning" role="alert">{{ $result->message }}</div>
@elseif ($result)
    @php($data = $result->data)
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">
                {{ $data['kind'] === 'variants' ? $data['model']['name'].' — trims by features' : 'Segment '.$data['segment'].' — models by specifications' }}
            </h3>
        </div>
        @if ($data['groups'] === [])
            <div class="xl-empty"><i class="la la-columns" aria-hidden="true"></i>No {{ $data['kind'] === 'variants' ? 'features' : 'specifications' }} recorded for these vehicles yet{{ ($input['diff'] ?? false) ? ' (or no differences)' : '' }}.</div>
        @else
            <div class="table-responsive xl-compare">
                <table class="table table-sm table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th class="xl-compare-sticky">{{ $data['kind'] === 'variants' ? 'Feature' : 'Specification' }}</th>
                            @foreach ($data['columns'] as $col)
                                <th class="text-center">{{ $col['name'] }}<div class="small text-body-secondary fw-normal"><code>{{ $col['code'] }}</code></div></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['groups'] as $group => $rows)
                            <tr class="table-active"><th colspan="{{ count($data['columns']) + 1 }}" class="xl-compare-sticky">{{ $group }}</th></tr>
                            @foreach ($rows as $row)
                                <tr @class(['xl-compare-differs' => $row['differs']])>
                                    <td class="xl-compare-sticky">{{ $row['label'] }}</td>
                                    @foreach ($data['columns'] as $col)
                                        @php($v = $row['values'][$col['code']] ?? null)
                                        <td class="text-center">
                                            @if ($v === 'Yes')<i class="la la-check text-success" aria-label="Yes"></i>
                                            @elseif ($v === 'No')<i class="la la-times text-body-secondary" aria-label="No"></i>
                                            @elseif ($v === null)<span class="text-body-secondary">—</span>
                                            @else{{ $v }}@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-body-secondary d-print-none">Highlighted rows differ between the vehicles.</div>
        @endif
    </div>
@endif
@endsection

@push('after_scripts')
<script>
    (function () {
        // At most {{ $max }} vehicles: further boxes are disabled once the limit is reached.
        const boxes = document.querySelectorAll('.xl-cmp-pick');
        const count = document.getElementById('cmpCount');
        const sync = () => {
            const n = [...boxes].filter((b) => b.checked).length;
            boxes.forEach((b) => { b.disabled = !b.checked && n >= {{ $max }}; });
            count.textContent = n ? n + ' selected' : '';
        };
        boxes.forEach((b) => b.addEventListener('change', sync));
        sync();
    }());
</script>
@endpush
