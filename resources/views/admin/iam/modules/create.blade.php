@extends(backpack_view('blank'))

@section('title', 'Add New Module')

@section('content')

    <div class="container-fluid">

        <div class="row">

            <div class="col-12">

                <div class="card">

                    <div class="card-header text-body">
                        <h2 class="mb-0">
                            Add New Module
                        </h2>
                    </div>

                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('iam/module') }}">

                            @csrf

                            <div class="row">

                                <div class="col-md-4 mb-3">

                                    <label>
                                        Module Code
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code') }}" maxlength="50" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>
                                        Module Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Is Active?
                                    </label>

                                    <div class="form-check form-switch">

                                        <input type="hidden" name="is_active" value="0">

                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
        old('is_active', true)
        ? 'checked'
        : ''
                                            }}>

                                    </div>

                                </div>

                                <div class="col-md-12 mb-3">

                                    <label>
                                        Description
                                    </label>

                                    <textarea name="description" class="form-control"
                                        rows="4">{{ old('description') }}</textarea>

                                </div>

                            </div>

                            <div class="mt-4">

                                <button type="submit" class="btn btn-success btn-lg px-5">

                                    <i class="la la-save"></i>
                                    Create Module

                                </button>

                                <a href="{{ backpack_url('iam/module') }}" class="btn btn-secondary btn-lg">

                                    Cancel

                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

@endsection