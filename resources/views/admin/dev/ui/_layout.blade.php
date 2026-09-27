{{-- Dev UI kit shell (DEC-067): page header + section switcher. Pages @extends this and fill @section('kit'). --}}
@extends(backpack_view('blank'))

@section('title', $title)

@section('header')
    <div class="page-header d-print-none mb-3">
        <div class="container-fluid">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Developer reference · static samples, no data</div>
                    <h2 class="page-title">{{ $pages[$page][0] }}</h2>
                </div>
                <div class="col-auto d-flex gap-2">
                    <a href="#xl-theme-settings" class="btn" data-bs-toggle="offcanvas" role="button" aria-controls="xl-theme-settings">
                        <i class="la la-palette me-1"></i> Appearance
                    </a>
                    <button type="button" class="btn btn-icon" onclick="XL.theme.set('mode', document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark')" aria-label="Toggle dark mode">
                        <i class="la la-adjust"></i>
                    </button>
                </div>
            </div>
            <nav class="mt-3 overflow-auto" aria-label="UI kit sections">
                <div class="nav nav-segmented flex-nowrap" role="tablist">
                    @foreach ($pages as $key => [$label, $icon])
                        <a href="{{ route('dev.ui.show', $key === 'index' ? [] : ['page' => $key]) }}" class="nav-link text-nowrap {{ $key === $page ? 'active' : '' }}" @if($key === $page) aria-current="page" @endif>
                            <i class="la {{ $icon }} me-1"></i>{{ $label }}
                        </a>
                    @endforeach
                </div>
            </nav>
        </div>
    </div>
@endsection

@section('content')
    <div class="container-fluid">
        @yield('kit')
    </div>
@endsection
