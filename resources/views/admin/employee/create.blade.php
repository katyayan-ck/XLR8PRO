@extends(backpack_view('blank'))

@section('title', 'Add New Employee')

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
                <div class="card-header text-black">
                    <h2 class="mb-0">Add New Employee</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('employee') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Employee Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Person <span class="text-danger">*</span></label>
                                <select name="person_id" class="form-control form-select" required>
                                    <option value="">Select Person</option>
                                    @foreach($persons as $p)
                                    <option value="{{ $p->id }}" {{ old('person_id')==$p->id ? 'selected' : '' }}>
                                        {{ $p->first_name }} {{ $p->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Designation <span class="text-danger">*</span></label>
                                <select name="designation_id" class="form-control form-select" required>
                                    <option value="">Select Designation</option>
                                    @foreach($designations as $d)
                                    <option value="{{ $d->id }}" {{ old('designation_id')==$d->id ? 'selected' : '' }}>
                                        {{ $d->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Primary Branch <span class="text-danger">*</span></label>
                                <select name="primary_branch_id" class="form-control form-select" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('primary_branch_id')==$b->id ? 'selected' : ''
                                        }}>
                                        {{ $b->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Primary Department <span class="text-danger">*</span></label>
                                <select name="primary_department_id" class="form-control form-select" required>
                                    <option value="">Select Department</option>
                                    @foreach($departments as $d)
                                    <option value="{{ $d->id }}" {{ old('primary_department_id')==$d->id ? 'selected' :
                                        '' }}>
                                        {{ $d->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Joining Date <span class="text-danger">*</span></label>
                                <input type="date" name="joining_date" class="form-control"
                                    value="{{ old('joining_date') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Employment Type <span class="text-danger">*</span></label>
                                <select name="employment_type" class="form-control form-select" required>
                                    <option value="permanent" {{ old('employment_type')=='permanent' ? 'selected' : ''
                                        }}>Permanent</option>
                                    <option value="contract" {{ old('employment_type')=='contract' ? 'selected' : '' }}>
                                        Contract</option>
                                    <option value="temporary" {{ old('employment_type')=='temporary' ? 'selected' : ''
                                        }}>Temporary</option>
                                    <option value="probation" {{ old('employment_type')=='probation' ? 'selected' : ''
                                        }}>Probation</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', true) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Employee
                            </button>
                            <a href="{{ backpack_url('employee') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection