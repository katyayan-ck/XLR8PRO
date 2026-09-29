@extends(backpack_view('layouts.auth'))

{{-- Screen lock (go-live to-do S2): no application content is rendered; unlock with the account password. --}}
@section('content')
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <img src="{{ site_logo_url() }}" alt="{{ backpack_theme_config('project_name') }}" class="xl-site-logo xl-site-logo-lg" style="height:56px;width:auto;max-width:260px">
        </div>
        <form class="card card-md" method="POST" action="{{ route('xl.session.unlock') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="to" value="{{ $to }}">
            <div class="card-body text-center">
                <span class="avatar avatar-xl mb-3 rounded-circle" @if (method_exists($user, 'profilePhotoUrl') && $user->profilePhotoUrl()) style="background-image: url('{{ $user->profilePhotoUrl() }}')" @endif>
                    @unless (method_exists($user, 'profilePhotoUrl') && $user->profilePhotoUrl())
                        <i class="la la-user la-2x" aria-hidden="true"></i>
                    @endunless
                </span>
                <h2 class="card-title mb-1">{{ $user->display_name ?? $user->username }}</h2>
                <p class="text-body-secondary"><i class="la la-lock me-1" aria-hidden="true"></i>Screen locked — enter your password to continue.</p>
                <div class="mb-3 text-start">
                    <label class="form-label" for="lock-password">Password</label>
                    <input type="password" name="password" id="lock-password" class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="la la-unlock me-1"></i>Unlock</button>
            </div>
            <div class="card-footer text-center">
                <a href="{{ backpack_url('logout') }}" class="text-body-secondary">Not you? Sign out</a>
            </div>
        </form>
    </div>
</div>
@endsection
