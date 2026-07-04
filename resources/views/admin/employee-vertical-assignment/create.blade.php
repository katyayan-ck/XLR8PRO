@extends(backpack_view('blank'))

@section('title', 'Assign Vertical')

@push('after_styles')
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Assign Vertical to Employee</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('employee-vertical-assignment') }}">
                        @csrf

                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label>Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-control form-select" required>
                                    <option value="">-- Select Employee --</option>
                                    @foreach(App\Models\Core\Employee::with('person')->orderBy('code')->get() as $emp)
                                    <option value="{{ $emp->id }}">
                                        {{ $emp->code }} -
                                        {{ $emp->person ? trim($emp->person->first_name.' '.$emp->person->last_name) :
                                        'N/A' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Vertical <span class="text-danger">*</span></label>
                                <select name="vertical_id" class="form-control form-select" required>
                                    <option value="">-- Select Vertical --</option>
                                    @foreach(App\Models\Core\Vertical::orderBy('name')->get() as $vertical)
                                    <option value="{{ $vertical->id }}">{{ $vertical->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>From Date <span class="text-danger">*</span></label>
                                <input type="date" name="from_date" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>To Date</label>
                                <input type="date" name="to_date" class="form-control">
                                <small class="text-muted">Leave empty if ongoing</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Is Current Assignment?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_current" value="0">
                                    <input type="checkbox" name="is_current" value="1" class="form-check-input" checked>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Assign Vertical
                            </button>
                            <a href="{{ backpack_url('employee-vertical-assignment') }}"
                                class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection