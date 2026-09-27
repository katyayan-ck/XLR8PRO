@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php $labels = ['CREATED' => ['Created', 'la-pen'], 'ASSIGNED' => ['Assigned', 'la-user-check'], 'FOLLOWED' => ['Followed', 'la-bell'], 'SNOOPED' => ['Snooped', 'la-eye']]; @endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach ($labels as $code => [$label, $icon])
                    <li class="nav-item">
                        <a class="nav-link {{ $box === $code ? 'active' : '' }}" href="{{ route('utils.tasks.index', ['box' => $code]) }}">
                            <i class="la {{ $icon }} me-1"></i>{{ $label }}
                            @if ($counts[$code]) <span class="badge bg-blue text-white ms-1">{{ $counts[$code] }}</span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex gap-2">
                <form method="GET" action="{{ route('utils.tasks.index') }}" class="d-flex gap-2">
                    <input type="hidden" name="box" value="{{ $box }}">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Any status</option>
                        @foreach (\App\Services\Platform\Task\TaskService::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search title">
                    <button class="btn btn-sm btn-outline-primary">Filter</button>
                </form>
                @can('UTL_TASK_CREATE')
                    <a href="{{ route('utils.tasks.create') }}" class="btn btn-sm btn-primary"><i class="la la-plus me-1"></i>New task</a>
                @endcan
            </div>
        </div>
        <x-task.inbox :box="$box" :rows="$rows" />
        @if ($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
