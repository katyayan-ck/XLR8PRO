@php
    $user = backpack_user();
@endphp

<div class="nav-item dropdown pe-3">
    <a href="#" 
       class="nav-link d-flex lh-1 text-reset p-0 align-items-center" 
       data-bs-toggle="dropdown" 
       aria-label="Open user menu">

        {{-- Avatar --}}
        <span class="avatar avatar-sm rounded-circle position-relative" 
              style="width: 36px; height: 36px; background: #0d6efd; color: white; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
            
            @if(backpack_avatar_url($user))
                <img src="{{ backpack_avatar_url($user) }}" 
                     alt="{{ $user->display_name }}" 
                     class="w-100 h-100 object-fit-cover"
                     onerror="this.style.display='none'">
            @endif
            
            {{-- Initials Fallback --}}
            <span class="avatar-initials" 
                  style="{{ backpack_avatar_url($user) ? 'display:none;' : '' }}">
                {{ $user->avatar_initials ?? 'U' }}
            </span>
        </span>

        {{-- Name + Designation --}}
        <div class="d-none d-xl-block ps-2" style="line-height: 1.15;">
            <div style="font-weight: 600; font-size: 13.5px; color: #2c3e50;">
                {{ $user->display_name ?? $user->username }}
            </div>
            <div class="mt-0 small text-muted" style="font-size: 11.5px;">
                {{ $user->primary_designation ?? 'Employee' }}
            </div>
        </div>
    </a>

    {{-- Dropdown Menu --}}
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow shadow-sm" style="min-width: 220px;">
        
        @if(config('backpack.base.setup_my_account_routes'))
            <a href="{{ route('backpack.account.info') }}" class="dropdown-item">
                <i class="la la-user me-2"></i> {{ trans('backpack::base.my_account') }}
            </a>
            <div class="dropdown-divider"></div>
        @endif

        <a href="{{ backpack_url('logout') }}" class="dropdown-item">
            <i class="la la-lock me-2"></i> {{ trans('backpack::base.logout') }}
        </a>
    </div>
</div>