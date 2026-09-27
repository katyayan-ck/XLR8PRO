@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('utils.settings.index') }}" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search key or label (e.g. sla, docs, whatsapp)">
                <button class="btn btn-primary">Search</button>
                @if ($search !== '')
                    <a href="{{ route('utils.settings.index') }}" class="btn btn-outline-secondary">Clear</a>
                @endif
            </form>
        </div>
    </div>

    @forelse ($groups as $group => $settings)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0 text-capitalize">{{ str_replace('_', ' ', $group) }}</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr><th style="width: 30%;">Setting</th><th>Value</th><th style="width: 16%;">Last changed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($settings as $setting)
                            @php
                                $display = is_bool($setting['value']) ? ($setting['value'] ? 'true' : 'false') : (is_array($setting['value']) ? json_encode($setting['value']) : (string) $setting['value']);
                                $secret = $setting['type'] === 'encrypted';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $setting['label'] }}</div>
                                    <code class="small">{{ $setting['key'] }}</code> <span class="badge bg-secondary-lt">{{ $setting['type'] }}</span>
                                </td>
                                <td>
                                    @if ($canManage && $setting['editable'])
                                        <form method="POST" action="{{ route('utils.settings.update') }}" class="d-flex gap-2">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="key" value="{{ $setting['key'] }}">
                                            @if ($secret)
                                                <input type="hidden" name="keep_if_blank" value="1">
                                                <input type="password" name="value" autocomplete="new-password" class="form-control form-control-sm" placeholder="{{ $display !== '' ? 'Set (leave blank to keep)' : 'Not set' }}">
                                            @elseif ($setting['type'] === 'bool')
                                                <select name="value" class="form-select form-select-sm">
                                                    <option value="1" @selected($setting['value'])>true</option>
                                                    <option value="0" @selected(! $setting['value'])>false</option>
                                                </select>
                                            @else
                                                <input type="text" name="value" value="{{ $display }}" class="form-control form-control-sm">
                                            @endif
                                            <button class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                        <details class="mt-1 small">
                                            <summary class="text-muted">Overrides ({{ count($setting['overrides']) }}) · reset</summary>
                                            @foreach ($setting['overrides'] as $override)
                                                <form method="POST" action="{{ route('utils.settings.reset') }}" class="d-flex align-items-center gap-2 mt-1">
                                                    @csrf
                                                    <input type="hidden" name="key" value="{{ $setting['key'] }}">
                                                    <input type="hidden" name="scope_type" value="{{ $override->scope_type }}">
                                                    <input type="hidden" name="scope_code" value="{{ $override->scope_code }}">
                                                    <span>{{ $override->scope_type }} {{ $override->scope_code }} = <code>{{ $secret ? '••••••' : $override->value }}</code></span>
                                                    <button class="btn btn-link btn-sm p-0 text-danger">Remove</button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('utils.settings.update') }}" class="d-flex gap-2 mt-2">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="key" value="{{ $setting['key'] }}">
                                                <select name="scope_type" class="form-select form-select-sm" style="max-width: 120px;">
                                                    <option value="COMPANY">Company</option>
                                                    <option value="BRANCH">Branch</option>
                                                    <option value="DESK">Desk</option>
                                                </select>
                                                <input type="text" name="scope_code" required maxlength="50" class="form-control form-control-sm" placeholder="Code" style="max-width: 120px;">
                                                <input type="{{ $secret ? 'password' : 'text' }}" name="value" class="form-control form-control-sm" placeholder="Value">
                                                <button class="btn btn-sm btn-outline-primary">Add</button>
                                            </form>
                                            <form method="POST" action="{{ route('utils.settings.reset') }}" class="mt-2" onsubmit="return confirm('Reset {{ $setting['key'] }} to its default?')">
                                                @csrf
                                                <input type="hidden" name="key" value="{{ $setting['key'] }}">
                                                <button class="btn btn-sm btn-outline-danger">Reset to default</button>
                                            </form>
                                        </details>
                                    @else
                                        <code>{{ $display }}</code>
                                        @if (! $setting['editable'])
                                            <span class="badge bg-secondary-lt ms-1">read-only</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $setting['updated_at'] ? site_date($setting['updated_at']) : 'default' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted text-center">No settings match.</div></div>
    @endforelse
</div>
@endsection
