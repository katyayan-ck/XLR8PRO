@extends(backpack_view('blank'))

@section('title', $title)

{{-- Support requests (DEC-094, W16e): own requests; support admins see all and assign executives (UTL_SUPP_EXEC only). --}}
@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="page-pretitle"><a href="{{ route('utils.help.index') }}">{{ __('utils.help.centre') }}</a></div>
        <h2 class="page-title">{{ $title }}</h2>
    </div>
    <div class="card">
        @if ($rows->isEmpty())
            <div class="xl-empty"><i class="la la-life-ring" aria-hidden="true"></i>{{ __('utils.support.none') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('utils.support.title') }}</th>
                            <th>{{ __('utils.support.what') }}</th>
                            <th>Status</th>
                            @if ($isAdmin)<th>Requested by</th>@endif
                            <th>Sent</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    @if ($row->ticket)
                                        <a href="{{ route('utils.tickets.show', ['id' => $row->ticket_id]) }}">{{ $row->ticket->number }}</a>
                                        <div class="small text-body-secondary">{{ $row->ticket->title }}</div>
                                    @else
                                        #{{ $row->id }}
                                    @endif
                                </td>
                                <td>{{ __('utils.support.categories.'.$row->category) }}</td>
                                <td><span class="badge bg-secondary-lt">{{ $row->ticket?->status ?? '—' }}</span></td>
                                @if ($isAdmin)<td>{{ $row->requester?->display_name ?? $row->requester?->username ?? '—' }}</td>@endif
                                <td>{{ site_datetime($row->created_at) }}</td>
                                <td class="text-end">
                                    @if ($row->bundle_path)
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-primary" href="{{ route('utils.support.show', ['id' => $row->id]) }}">
                                                <i class="la la-eye me-1" aria-hidden="true"></i>{{ __('utils.support.download') }}
                                            </a>
                                            <a class="btn btn-outline-secondary" href="{{ route('utils.support.download', ['id' => $row->id]) }}" title="{{ __('utils.support.download_zip') }}" aria-label="{{ __('utils.support.download_zip') }}">
                                                <i class="la la-file-archive" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if ($isAdmin && $row->ticket && in_array($row->ticket->status, \App\Services\Platform\Ticket\TicketService::OPEN, true))
                                <tr>
                                    <td colspan="6" class="pt-0">
                                        <form method="POST" action="{{ route('utils.support.assign', ['id' => $row->id]) }}" class="d-flex flex-wrap gap-2 align-items-end">
                                            @csrf
                                            <div class="flex-grow-1">
                                                <label class="form-label small mb-1" for="exec-{{ $row->id }}">{{ __('utils.support.assign') }}</label>
                                                <x-ui.select name="executives[]" id="exec-{{ $row->id }}" :options="$executives" :selected="$assignees[$row->ticket_id] ?? []" multiple />
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('utils.support.assign') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
