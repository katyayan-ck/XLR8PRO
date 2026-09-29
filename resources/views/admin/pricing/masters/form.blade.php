@extends(backpack_view('blank'))

@section('title', $title)

{{-- Pricing master create / edit (DEC-083): fields, labels and options come from the entity service via the definition. --}}
@section('content')
@php
    $key = $master->key();
    $value = function (string $field) use ($row) {
        $v = old($field, $row ? $row->getAttribute($field) : null);

        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
    };
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Pricing · {{ $master->label() }}</div>
            <h2 class="mb-0">{{ $title }}</h2>
        </div>
        <a href="{{ route('pricing.masters.index', $key) }}" class="btn btn-link px-0"><i class="la la-arrow-left me-1"></i>{{ $master->label() }}</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $row ? route('pricing.masters.update', [$key, $row->getKey()]) : route('pricing.masters.store', $key) }}" class="card">
        @csrf
        @if ($row)
            @method('PUT')
        @endif
        <div class="card-body">
            <div class="row g-3">
                @foreach ($master->formFields() as $field)
                    @php $spec = $master->input($field); $id = 'pm-'.$field; @endphp
                    <div class="col-12 col-md-6 col-lg-4">
                        @if ($spec['type'] === 'bool')
                            <label class="form-check mt-md-4" for="{{ $id }}">
                                <input class="form-check-input" type="checkbox" name="{{ $field }}" id="{{ $id }}" value="1" @checked((bool) $value($field))>
                                <span class="form-check-label">{{ $spec['label'] }}</span>
                            </label>
                        @else
                            <label class="form-label {{ $spec['required'] ? 'required' : '' }}" for="{{ $id }}">{{ $spec['label'] }}</label>
                            @if ($spec['type'] === 'select')
                                <select class="form-select" name="{{ $field }}" id="{{ $id }}" @required($spec['required'])>
                                    <option value="">—</option>
                                    @foreach ($spec['options'] as $option)
                                        <option value="{{ $option }}" @selected((string) $value($field) === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($spec['type'] === 'date')
                                <x-ui.date :name="$field" :id="$id" :value="$value($field) ?? ($field === 'wef_date' ? now()->toDateString() : null)" />
                            @else
                                <input class="form-control" type="{{ $spec['type'] === 'number' ? 'number' : 'text' }}" @if ($spec['type'] === 'number') step="any" @endif
                                    name="{{ $field }}" id="{{ $id }}" value="{{ $value($field) }}" @required($spec['required']) autocomplete="off">
                            @endif
                            @if ($spec['help'])
                                <div class="form-hint">{{ $spec['help'] }}</div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($master->hasWef())
                <div class="form-hint mt-3">Keeping the WEF edits this row. A new WEF keeps the current row as history (expired at the new WEF) and makes the new values live from that date.</div>
            @endif
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="la la-save me-1"></i>Save</button>
            <a href="{{ route('pricing.masters.index', $key) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
</div>
@endsection
