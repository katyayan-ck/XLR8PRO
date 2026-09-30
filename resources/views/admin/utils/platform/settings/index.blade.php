@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
{{-- The one categorised settings interface (DEC-091): tabs / sections from config/settings_ui.php, one form per section. --}}
<div class="xl-page-head">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Utilities</div>
        <h2 class="mb-0 fw-bold">{{ $title }}</h2>
    </div>
    <div class="xl-toolbar">
        <label for="settingsSearch" class="visually-hidden">Search settings</label>
        <input type="search" id="settingsSearch" class="form-control form-control-sm xl-toolbar-search" placeholder="Search all settings…">
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ __('errors.VALIDATION_FAILED') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-md-3">
        <div class="list-group" role="tablist" id="settingsTabs">
            @foreach ($tabs as $tabKey => $tab)
                <a href="#tab-{{ $tabKey }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2 @if ($tabKey === $active) active @endif"
                   data-bs-toggle="list" role="tab" data-tab="{{ $tabKey }}" aria-controls="tab-{{ $tabKey }}">
                    <i class="la {{ $tab['icon'] }}" aria-hidden="true"></i> {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="col-12 col-md-9">
        <div class="tab-content">
            @foreach ($tabs as $tabKey => $tab)
                <div class="tab-pane fade @if ($tabKey === $active) show active @endif" id="tab-{{ $tabKey }}" role="tabpanel">
                    @foreach ($tab['sections'] as $sectionKey => $section)
                        @php
                            $fields = array_filter($section['keys'], fn ($s) => $s['input'] !== 'image');
                            $images = array_filter($section['keys'], fn ($s) => $s['input'] === 'image');
                            $overrides = array_filter($section['keys'], fn ($s) => ! empty($s['overrides']));
                        @endphp
                        <div class="card mb-3 xl-settings-section">
                            <div class="card-header"><h3 class="card-title mb-0">{{ $section['label'] }}</h3></div>
                            <div class="card-body">
                                @if ($fields !== [])
                                    <form method="POST" action="{{ route('utils.settings.section', [$tabKey, $sectionKey]) }}" class="xl-settings-form">
                                        @csrf @method('PUT')
                                        @foreach ($fields as $s)
                                            @php
                                                $field = str_replace('.', '__', $s['key']);
                                                $name = "settings[{$field}]";
                                                $id = 'set-'.$field;
                                                $value = old("settings.{$field}", is_array($s['value']) ? json_encode($s['value'], JSON_PRETTY_PRINT) : $s['value']);
                                                $error = $errors->first("settings.{$field}");
                                            @endphp
                                            <div class="row g-2 align-items-start mb-2 xl-setting" data-search="{{ strtolower($s['label'].' '.$s['key'].' '.$section['label']) }}">
                                                <div class="col-12 col-lg-5">
                                                    <label for="{{ $id }}" class="form-label mb-0 fw-medium">{{ $s['label'] }}</label>
                                                    <div class="small text-body-secondary"><code>{{ $s['key'] }}</code>@if ($s['help']) · {{ $s['help'] }}@endif</div>
                                                </div>
                                                <div class="col-12 col-lg-7">
                                                    @switch($s['input'])
                                                        @case('switch')
                                                            <input type="hidden" name="{{ $name }}" value="0">
                                                            <label class="form-check form-switch mb-0">
                                                                <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" class="form-check-input" @checked((bool) $value)>
                                                                <span class="form-check-label">{{ (bool) $value ? 'On' : 'Off' }}</span>
                                                            </label>
                                                            @break
                                                        @case('select')
                                                            <select id="{{ $id }}" name="{{ $name }}" class="form-select form-select-sm @if ($error) is-invalid @endif">
                                                                @foreach ($s['options'] as $optValue => $optLabel)
                                                                    <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                            @break
                                                        @case('textarea')
                                                        @case('json')
                                                            <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="form-control form-control-sm @if ($s['input'] === 'json') font-monospace @endif @if ($error) is-invalid @endif">{{ $value }}</textarea>
                                                            @break
                                                        @case('secret')
                                                            <input type="password" id="{{ $id }}" name="{{ $name }}" autocomplete="new-password" class="form-control form-control-sm @if ($error) is-invalid @endif"
                                                                   placeholder="{{ $s['value'] !== '' ? 'Set — leave blank to keep' : 'Not set' }}">
                                                            @break
                                                        @case('readonly')
                                                            <div id="{{ $id }}" class="form-control-plaintext form-control-sm">{{ $value !== '' && $value !== null ? $value : '—' }}</div>
                                                            @break
                                                        @default
                                                            <input type="{{ in_array($s['input'], ['url', 'email', 'number'], true) ? $s['input'] : 'text' }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
                                                                   @if ($s['min'] !== null) min="{{ $s['min'] }}" @endif @if ($s['max'] !== null) max="{{ $s['max'] }}" @endif
                                                                   class="form-control form-control-sm @if ($error) is-invalid @endif">
                                                    @endswitch
                                                    @if ($error)
                                                        <div class="invalid-feedback d-block">{{ $error }}</div>
                                                    @endif
                                                    @if ($s['updated_at'])
                                                        <div class="small text-body-secondary mt-1">Changed {{ site_datetime($s['updated_at']) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="la la-save me-1"></i> Save {{ strtolower($section['label']) }}</button>
                                        </div>
                                    </form>
                                @endif

                                @foreach ($images as $s)
                                    <div class="row g-2 align-items-start mt-2 pt-2 border-top xl-setting" data-search="{{ strtolower($s['label'].' '.$s['key'].' '.$section['label']) }}">
                                        <div class="col-12 col-lg-5">
                                            <div class="fw-medium">{{ $s['label'] }}</div>
                                            <div class="small text-body-secondary"><code>{{ $s['key'] }}</code>@if ($s['help']) · {{ $s['help'] }}@endif</div>
                                        </div>
                                        <div class="col-12 col-lg-7">
                                            {{-- Current image: the uploaded one, else the built-in one (owner 30-09) --}}
                                            @php($current = $s['value'] ?: $s['default_image'])
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                @if ($current)
                                                    <img src="{{ $current }}" alt="{{ $s['label'] }} (current)" class="xl-site-logo border rounded p-1">
                                                @endif
                                                <span class="badge {{ $s['value'] ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ $s['value'] ? 'Uploaded' : 'Built-in' }}</span>
                                                @if ($s['value'])
                                                    <form method="POST" action="{{ route('utils.settings.reset') }}" class="ms-auto" onsubmit="return confirm('Remove this image and use the built-in one?')">
                                                        @csrf
                                                        <input type="hidden" name="key" value="{{ $s['key'] }}">
                                                        <button class="btn btn-sm btn-outline-danger"><i class="la la-trash me-1"></i> Remove</button>
                                                    </form>
                                                @endif
                                            </div>
                                            <form method="POST" action="{{ route('utils.settings.image') }}" enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="key" value="{{ $s['key'] }}">
                                                <x-ui.upload name="file" accept="image/*" :id="'img-'.str_replace('.', '-', $s['key'])" required />
                                                <button class="btn btn-sm btn-primary mt-2"><i class="la la-upload me-1"></i> Upload new {{ strtolower($s['label']) }}</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach

                                <details class="mt-2 small">
                                    <summary class="text-body-secondary">Branch / desk overrides ({{ array_sum(array_map(fn ($s) => count($s['overrides']), $section['keys'])) }})</summary>
                                    @foreach ($overrides as $s)
                                        @foreach ($s['overrides'] as $override)
                                            <form method="POST" action="{{ route('utils.settings.reset') }}" class="d-flex align-items-center gap-2 mt-1">
                                                @csrf
                                                <input type="hidden" name="key" value="{{ $s['key'] }}">
                                                <input type="hidden" name="scope_type" value="{{ $override->scope_type }}">
                                                <input type="hidden" name="scope_code" value="{{ $override->scope_code }}">
                                                <span>{{ $s['label'] }} — {{ $override->scope_type }} {{ $override->scope_code }} = <code>{{ $s['input'] === 'secret' ? '••••••' : $override->value }}</code></span>
                                                <button class="btn btn-link btn-sm p-0 text-danger">Remove</button>
                                            </form>
                                        @endforeach
                                    @endforeach
                                    <form method="POST" action="{{ route('utils.settings.update') }}" class="row g-2 mt-2">
                                        @csrf @method('PUT')
                                        <div class="col-12 col-lg-4">
                                            <label class="visually-hidden" for="ov-key-{{ $tabKey }}-{{ $sectionKey }}">Setting</label>
                                            <select id="ov-key-{{ $tabKey }}-{{ $sectionKey }}" name="key" class="form-select form-select-sm">
                                                @foreach ($fields as $s)
                                                    @if (! in_array($s['input'], ['readonly', 'json'], true))
                                                        <option value="{{ $s['key'] }}">{{ $s['label'] }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-lg-2">
                                            <label class="visually-hidden" for="ov-type-{{ $tabKey }}-{{ $sectionKey }}">Scope</label>
                                            <select id="ov-type-{{ $tabKey }}-{{ $sectionKey }}" name="scope_type" class="form-select form-select-sm">
                                                <option value="COMPANY">Company</option><option value="BRANCH">Branch</option><option value="DESK">Desk</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-lg-2">
                                            <label class="visually-hidden" for="ov-code-{{ $tabKey }}-{{ $sectionKey }}">Code</label>
                                            <input id="ov-code-{{ $tabKey }}-{{ $sectionKey }}" type="text" name="scope_code" required maxlength="50" class="form-control form-control-sm" placeholder="Code">
                                        </div>
                                        <div class="col-8 col-lg-3">
                                            <label class="visually-hidden" for="ov-val-{{ $tabKey }}-{{ $sectionKey }}">Value</label>
                                            <input id="ov-val-{{ $tabKey }}-{{ $sectionKey }}" type="text" name="value" class="form-control form-control-sm" placeholder="Value">
                                        </div>
                                        <div class="col-4 col-lg-1"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
                                    </form>
                                </details>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div id="settingsNoMatch" class="xl-empty d-none">No setting matches the search.</div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    (function () {
        const url = new URL(window.location.href);
        // Remember the open tab in the address (a reload or a save comes back to it).
        document.querySelectorAll('#settingsTabs [data-tab]').forEach((a) => a.addEventListener('shown.bs.tab', () => {
            url.searchParams.set('tab', a.dataset.tab);
            window.history.replaceState(null, '', url);
        }));
        // Search across every tab: matching settings stay, empty sections / tabs hide.
        const search = document.getElementById('settingsSearch');
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let any = false;
            document.querySelectorAll('.tab-pane').forEach((pane) => {
                let paneHit = false;
                pane.querySelectorAll('.xl-settings-section').forEach((card) => {
                    let hit = false;
                    card.querySelectorAll('.xl-setting').forEach((row) => {
                        const ok = q === '' || row.dataset.search.includes(q);
                        row.classList.toggle('d-none', !ok);
                        hit = hit || ok;
                    });
                    card.classList.toggle('d-none', !hit);
                    paneHit = paneHit || hit;
                });
                if (q !== '') {
                    pane.classList.toggle('show', paneHit);
                    pane.classList.toggle('active', paneHit);
                }
                any = any || paneHit;
            });
            if (q === '') {
                const current = url.searchParams.get('tab') || document.querySelector('#settingsTabs .active')?.dataset.tab;
                document.querySelectorAll('.tab-pane').forEach((pane) => {
                    const on = pane.id === 'tab-' + current;
                    pane.classList.toggle('show', on);
                    pane.classList.toggle('active', on);
                });
            }
            document.getElementById('settingsNoMatch').classList.toggle('d-none', any);
        });
        // Unsaved changes guard.
        let dirty = false;
        document.querySelectorAll('.xl-settings-form').forEach((form) => {
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('submit', () => { dirty = false; });
        });
        window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    }());
</script>
@endpush
