@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $v = $version;
    $editable = $canEdit && (! $v || $v->status === 'DRAFT');
    $channel = old('channel', $template?->channel ?? 'EMAIL');
@endphp
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('utils.templates.save') }}" class="card">
                @csrf
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">{{ $v ? 'v'.$v->version.' · '.$v->status : 'New template' }}</h3>
                    @if ($v && $v->status !== 'DRAFT' && $canEdit)
                        <span class="small text-muted">Saving creates a new draft; this version is never changed.</span>
                    @endif
                </div>
                <div class="card-body row g-2">
                    <div class="col-md-5"><label class="form-label required">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', $template?->code) }}" @readonly($template) placeholder="quote.customer.send" required></div>
                    <div class="col-md-3">
                        <label class="form-label required">Channel</label>
                        <select name="channel" class="form-select" @disabled($template)>
                            @foreach (\App\Models\Comms\CommTemplate::CHANNELS as $c) <option value="{{ $c }}" @selected($channel === $c)>{{ $c }}</option> @endforeach
                        </select>
                        @if ($template) <input type="hidden" name="channel" value="{{ $template->channel }}"> @endif
                    </div>
                    <div class="col-md-2"><label class="form-label">Locale</label><input type="text" name="locale" class="form-control" value="{{ old('locale', $template?->locale ?? 'en-IN') }}" @readonly($template)></div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            @foreach (\App\Models\Comms\CommTemplate::CATEGORIES as $c) <option value="{{ $c }}" @selected(old('category', $template?->category) === $c)>{{ $c }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label required">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $template?->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="{{ old('description', $template?->description) }}"></div>
                    <div class="col-12"><label class="form-label">Subject (email)</label><input type="text" name="subject" class="form-control" value="{{ old('subject', $v?->subject) }}"></div>
                    <div class="col-12"><label class="form-label">HTML body (email)</label><textarea name="body_html" rows="6" class="form-control font-monospace small">{{ old('body_html', $v?->body_html) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Text body (SMS / WhatsApp / email text part)</label><textarea name="body_text" rows="4" class="form-control font-monospace small">{{ old('body_text', $v?->body_text) }}</textarea>
                        <div class="form-text">Placeholders: <code>@{{name}}</code>. HTML values are escaped automatically.</div></div>
                    <div class="col-md-6"><label class="form-label">Variables (JSON)</label><textarea name="variables_json" rows="5" class="form-control font-monospace small" placeholder='[{"name":"cust_name","required":true,"sample":"Ravi","pii":true}]'>{{ old('variables_json', $v?->variables ? json_encode($v->variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Sample values (JSON)</label><textarea name="sample_json" rows="5" class="form-control font-monospace small">{{ old('sample_json', $v?->sample_vars ? json_encode($v->sample_vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea></div>
                    <div class="col-md-4"><label class="form-label">Provider template id</label><input type="text" name="provider_template_id" class="form-control" value="{{ old('provider_template_id', $v?->provider_template_id) }}"></div>
                    <div class="col-md-4"><label class="form-label">DLT entity id</label><input type="text" name="dlt_entity_id" class="form-control" value="{{ old('dlt_entity_id', $v?->dlt_entity_id) }}"></div>
                    <div class="col-md-4"><label class="form-label">DLT header</label><input type="text" name="dlt_header" class="form-control" value="{{ old('dlt_header', $v?->dlt_header) }}"></div>
                    <div class="col-12"><label class="form-label">WhatsApp components (JSON)</label><textarea name="wa_components_json" rows="3" class="form-control font-monospace small">{{ old('wa_components_json', $v?->wa_components ? json_encode($v->wa_components, JSON_PRETTY_PRINT) : '') }}</textarea></div>
                </div>
                @if ($canEdit)
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ $editable ? 'Save draft' : 'Save as new draft' }}</button></div>
                @endif
            </form>
        </div>

        <div class="col-lg-5">
            @if ($template)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Versions</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach ($template->versions as $ver)
                            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 {{ $v && $ver->id === $v->id ? 'bg-primary-lt' : '' }}">
                                <div>
                                    <a href="{{ route('utils.templates.edit', ['id' => $template->id, 'version' => $ver->id]) }}">v{{ $ver->version }}</a>
                                    <span class="badge bg-secondary-lt">{{ $ver->status }}</span>
                                    <span class="small text-muted">{{ $ver->usage_count }} send(s)</span>
                                    @if ($v && $ver->id !== $v->id) · <a href="{{ route('utils.templates.edit', ['id' => $template->id, 'version' => $v->id, 'compare' => $ver->id]) }}" class="small">diff</a> @endif
                                </div>
                                <div class="d-flex gap-1">
                                    @if ($ver->status === 'DRAFT' && $canEdit)
                                        <form method="POST" action="{{ route('utils.templates.submit', $ver->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Submit</button></form>
                                    @endif
                                    @if (in_array($ver->status, ['DRAFT', 'IN_REVIEW'], true) && $canActivate)
                                        <form method="POST" action="{{ route('utils.templates.approve', $ver->id) }}" onsubmit="return confirm('Approve without an approval request? Use only when no approval rule covers COMMS.TEMPLATE.')">@csrf<button class="btn btn-sm btn-outline-secondary">Approve</button></form>
                                    @endif
                                    @if ($ver->status === 'APPROVED' && $canActivate)
                                        <form method="POST" action="{{ route('utils.templates.activate', $ver->id) }}">@csrf<button class="btn btn-sm btn-success">Activate</button></form>
                                    @endif
                                    @if ($ver->approval_request_id)
                                        <a href="{{ route('utils.approvals.show', $ver->approval_request_id) }}" class="btn btn-sm btn-ghost-secondary" title="Approval request"><i class="la la-gavel"></i></a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($diff !== null)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Diff</h3></div>
                    <div class="card-body small">
                        @forelse ($diff as $field => [$a, $b])
                            <div class="mb-2"><strong>{{ $field }}</strong>
                                <div class="text-danger text-decoration-line-through text-wrap">{{ is_array($a) ? json_encode($a) : $a }}</div>
                                <div class="text-success text-wrap">{{ is_array($b) ? json_encode($b) : $b }}</div>
                            </div>
                        @empty
                            <div class="text-muted">No differences.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($v)
                <x-template.preview :version="$v" />
            @endif
        </div>
    </div>
</div>
@endsection
