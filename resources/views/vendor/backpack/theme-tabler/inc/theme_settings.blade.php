{{--
    DEC-067: Appearance panel (Tabler theme settings). Each radio carries data-xl-theme="<key>"; public/js/xl-theme.js applies
    and saves the choice (per browser) and keeps the radios in sync. Colours / base / font / radius are Tabler 1.4 theme
    attributes, the layout is the xl_layout cookie read by App\Http\Middleware\ApplyUiPreferences.
--}}
@php
    $xlPrimary = ['' => 'Default', 'blue' => 'Blue', 'azure' => 'Azure', 'indigo' => 'Indigo', 'purple' => 'Purple', 'pink' => 'Pink', 'red' => 'Red',
        'orange' => 'Orange', 'yellow' => 'Yellow', 'lime' => 'Lime', 'green' => 'Green', 'teal' => 'Teal', 'cyan' => 'Cyan'];
    $xlModes = ['light' => ['Light', 'la-sun'], 'dark' => ['Dark', 'la-moon'], 'system' => ['System', 'la-desktop']];
    $xlBases = ['' => 'Default', 'slate' => 'Slate', 'gray' => 'Gray', 'zinc' => 'Zinc', 'neutral' => 'Neutral', 'stone' => 'Stone'];
    $xlFonts = ['' => 'Sans', 'serif' => 'Serif', 'monospace' => 'Mono', 'comic' => 'Comic'];
    $xlRadii = ['0' => '0', '0.5' => '0.5', '' => '1', '1.5' => '1.5', '2' => '2'];
    $xlLayouts = ['horizontal' => ['Top menu', 'la-window-maximize'], 'vertical' => ['Sidebar', 'la-columns'], 'vertical_dark' => ['Dark sidebar', 'la-columns']];
@endphp
<div class="offcanvas offcanvas-end xl-theme-panel" tabindex="-1" id="xl-theme-settings" aria-labelledby="xl-theme-settings-title">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h3" id="xl-theme-settings-title"><i class="la la-palette me-1"></i> Appearance</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-4">

        <fieldset>
            <legend class="form-label">Colour mode</legend>
            <div class="form-selectgroup form-selectgroup-boxes w-100 xl-theme-grid-3">
                @foreach ($xlModes as $value => [$label, $icon])
                    <label class="form-selectgroup-item">
                        <input type="radio" name="xl-theme-mode" value="{{ $value }}" class="form-selectgroup-input" data-xl-theme="mode">
                        <span class="form-selectgroup-label d-flex flex-column align-items-center gap-1 py-2">
                            <i class="la {{ $icon }} fs-2"></i><span class="small">{{ $label }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="form-label">Colour scheme</legend>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($xlPrimary as $value => $label)
                    <label class="form-colorinput" title="{{ $label }}">
                        <input type="radio" name="xl-theme-primary" value="{{ $value }}" class="form-colorinput-input" data-xl-theme="primary" aria-label="{{ $label }}">
                        <span class="form-colorinput-color {{ $value === '' ? 'xl-swatch-default' : 'bg-'.$value }}"></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="form-label">Theme base</legend>
            <div class="form-selectgroup">
                @foreach ($xlBases as $value => $label)
                    <label class="form-selectgroup-item">
                        <input type="radio" name="xl-theme-base" value="{{ $value }}" class="form-selectgroup-input" data-xl-theme="base">
                        <span class="form-selectgroup-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="form-label">Font family</legend>
            <div class="form-selectgroup">
                @foreach ($xlFonts as $value => $label)
                    <label class="form-selectgroup-item">
                        <input type="radio" name="xl-theme-font" value="{{ $value }}" class="form-selectgroup-input" data-xl-theme="font">
                        <span class="form-selectgroup-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="form-label">Corner radius</legend>
            <div class="form-selectgroup">
                @foreach ($xlRadii as $value => $label)
                    <label class="form-selectgroup-item">
                        <input type="radio" name="xl-theme-radius" value="{{ $value }}" class="form-selectgroup-input" data-xl-theme="radius">
                        <span class="form-selectgroup-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="form-label">Menu layout</legend>
            <div class="form-selectgroup form-selectgroup-boxes w-100 xl-theme-grid-3">
                @foreach ($xlLayouts as $value => [$label, $icon])
                    <label class="form-selectgroup-item">
                        <input type="radio" name="xl-theme-layout" value="{{ $value }}" class="form-selectgroup-input" data-xl-theme="layout">
                        <span class="form-selectgroup-label d-flex flex-column align-items-center gap-1 py-2">
                            <i class="la {{ $icon }} fs-2 @if($value === 'vertical_dark') text-body-emphasis @endif"></i><span class="small">{{ $label }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="form-hint mt-1">Changing the layout reloads the page.</div>
        </fieldset>

        <div class="mt-auto pt-2">
            <button type="button" class="btn w-100" data-xl-theme-reset>
                <i class="la la-undo me-1"></i> Reset to defaults
            </button>
            <div class="form-hint text-center mt-2">Saved in this browser only.</div>
        </div>
    </div>
</div>
