{{-- DEC-067: Tabler-style user block — avatar (photo over initials), name and designation, and the account menu. --}}
@php
    $user = backpack_user();
    $avatarUrl = backpack_avatar_url($user);
@endphp

<div class="nav-item dropdown xl-user">
    <a href="#" class="nav-link d-flex lh-1 text-reset px-2" data-bs-toggle="dropdown" aria-label="Open user menu" aria-expanded="false">
        <span class="avatar avatar-sm bg-primary-lt xl-avatar">
            {{ $user->avatar_initials ?? 'U' }}
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="" onerror="this.remove()">
            @endif
            <span class="badge bg-success"></span>
        </span>
        <div class="d-none d-xl-block ps-2 text-start">
            <div class="fw-medium">{{ $user->display_name ?? $user->username }}</div>
            <div class="mt-1 small text-secondary">{{ $user->primary_designation ?? 'Employee' }}</div>
        </div>
    </a>

    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow xl-user-menu">
        <div class="dropdown-header d-flex align-items-center gap-2">
            <span class="avatar avatar-sm bg-primary-lt xl-avatar">
                {{ $user->avatar_initials ?? 'U' }}
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" onerror="this.remove()">
                @endif
            </span>
            <div class="text-truncate">
                <div class="fw-medium text-body text-truncate">{{ $user->display_name ?? $user->username }}</div>
                <div class="small text-secondary text-truncate">{{ $user->email ?: $user->username }}</div>
            </div>
        </div>
        <div class="dropdown-divider"></div>

        @if (Route::has('backpack.account.info'))
            <a href="{{ route('backpack.account.info') }}" class="dropdown-item">
                <i class="la la-user-circle dropdown-item-icon"></i> {{ trans('backpack::base.my_account') }}
            </a>
        @endif
        <a href="{{ route('utils.inbox.index') }}" class="dropdown-item">
            <i class="la la-inbox dropdown-item-icon"></i> My inbox
        </a>
        @if ($user->can('UTL_TASK_VIEW'))
            <a href="{{ route('utils.tasks.index') }}" class="dropdown-item">
                <i class="la la-tasks dropdown-item-icon"></i> My tasks
            </a>
        @endif
        <a href="#xl-theme-settings" class="dropdown-item" data-bs-toggle="offcanvas" role="button" aria-controls="xl-theme-settings">
            <i class="la la-palette dropdown-item-icon"></i> Appearance
        </a>
        @if (config('platform.dev_ui_kit'))
            <a href="{{ route('dev.ui.show') }}" class="dropdown-item">
                <i class="la la-swatchbook dropdown-item-icon"></i> UI kit <span class="badge bg-azure-lt ms-auto">dev</span>
            </a>
        @endif

        <div class="dropdown-divider"></div>
        <a href="{{ backpack_url('logout') }}" class="dropdown-item text-danger">
            <i class="la la-sign-out-alt dropdown-item-icon"></i> {{ trans('backpack::base.logout') }}
        </a>
    </div>
</div>
