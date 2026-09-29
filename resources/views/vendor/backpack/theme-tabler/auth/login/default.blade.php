@extends(backpack_view('layouts.auth'))

@section('content')
<div class="page page-center" style="background-color:#F2F4F7">
    <div class="container container-tight py-4">
        <div class="text-center mb-4 display-6 auth-logo-container">
            <img src="{{ site_logo_url() }}" alt="{{ backpack_theme_config('project_name') }}" class="xl-site-logo xl-site-logo-lg" style="height:56px;width:auto;max-width:260px">
   {{-- DEC-083 --}}
        </div>
        @if (session('status'))
            <div class="alert alert-info" role="status">{{ session('status') }}</div>   {{-- e.g. the idle sign-out notice --}}
        @endif
        <div class="card card-md">
            <div class="card-body pt-0" {{-- style="background-color: burlywood" --}}>
                @include(backpack_view('auth.login.inc.form'))
            </div>
        </div>
        {{-- @if (config('backpack.base.registration_open'))
        <div class="text-center text-muted mt-4">
            <a tabindex="6" href="{{ route('backpack.auth.register') }}">{{ trans('backpack::base.register') }}</a>
        </div>
        @endif --}}
    </div>
</div>
@endsection
