@extends(backpack_view('blank'))

@section('title', $title)

{{-- My support tickets (DEC-094, owner 03-10): every signed-in user — the tickets they raised and a new one. --}}
@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="page-pretitle"><a href="{{ route('backpack.account.info') }}">{{ trans('backpack::base.my_account') }}</a></div>
        <h2 class="page-title">{{ $title }}</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4 order-lg-2">
            <form method="POST" action="{{ route('utils.support.open') }}" class="card">
                @csrf
                <div class="card-header"><h3 class="card-title">{{ __('utils.support.new_ticket') }}</h3></div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label" for="sup-category">{{ __('utils.support.what') }}</label>
                        <select id="sup-category" name="category" class="form-select" required>
                            @foreach ($categories as $code => $label)
                                <option value="{{ $code }}" @selected(old('category') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-check">
                            <input type="hidden" name="urgent" value="0">
                            <input type="checkbox" class="form-check-input" name="urgent" value="1" @checked(old('urgent'))>
                            <span class="form-check-label">{{ __('utils.support.urgent') }}</span>
                        </label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="sup-subject">{{ __('utils.support.subject') }}</label>
                        <input id="sup-subject" name="subject" class="form-control @error('subject') is-invalid @enderror" maxlength="200" required value="{{ old('subject') }}">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="sup-description">{{ __('utils.support.description') }}</label>
                        <textarea id="sup-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="5" maxlength="5000" required>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card-footer text-end"><button type="submit" class="btn btn-primary">{{ __('utils.support.send') }}</button></div>
            </form>
        </div>

        <div class="col-12 col-lg-8 order-lg-1">
            <div class="card">
                @if ($tickets->isEmpty())
                    <div class="xl-empty"><i class="la la-life-ring" aria-hidden="true"></i>{{ __('utils.support.no_tickets') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr><th>Ticket</th><th>Status</th><th>Priority</th><th>Raised</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>
                                            <a href="{{ route('utils.tickets.show', ['id' => $ticket->id]) }}">{{ $ticket->number }}</a>
                                            <div class="small text-body-secondary text-truncate">{{ $ticket->title }}</div>
                                        </td>
                                        <td><span class="badge bg-secondary-lt">{{ $ticket->status }}</span></td>
                                        <td>{{ $ticket->priority }}</td>
                                        <td>{{ site_datetime($ticket->created_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">{{ $tickets->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
