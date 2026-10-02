<li class="nav-item d-flex align-items-center me-1">
    {{-- F1 help (DEC-094, W16b) — same as pressing F1 --}}
    <a href="{{ route('utils.help.index') }}" class="nav-link px-2" data-xl-help-open title="{{ __('utils.help.title') }} (F1)" aria-label="{{ __('utils.help.title') }}">
        <i class="la la-question-circle fs-2" aria-hidden="true"></i>
    </a>
</li>
<li class="nav-item d-flex align-items-center me-2">
    <x-notify.bell />
</li>
