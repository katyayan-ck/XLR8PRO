<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\Settings\SettingsCatalogue;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Utilities → Settings (DEC-091, to-do W13): the one categorised settings interface — tabs and sections from
 * `config/settings_ui.php` (SettingsCatalogue), each section saved as a form; image upload, per-scope overrides
 * (company / branch / desk) and reset per key. Settings managers (UTL_SETTINGS_MANAGE) see every tab; pricing managers
 * (PRC_WKFL_MANAGE) only the Pricing tab. Every write goes through SettingsService.
 */
class SettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SettingsCatalogue $catalogue,
    ) {}

    public function index(Request $request): View
    {
        $user = backpack_user();
        $tabs = $this->catalogue->tabsFor($user);
        if ($tabs === []) {
            abort(403);
        }
        $active = (string) $request->query('tab', '');

        return view('admin.utils.platform.settings.index', [
            'title' => 'Settings',
            'tabs' => $tabs,
            'active' => array_key_exists($active, $tabs) ? $active : array_key_first($tabs),
        ]);
    }

    /** Save one section's form (only changed values are written; a blank secret keeps the stored one). */
    public function saveSection(Request $request, string $tab, string $section): RedirectResponse
    {
        if (! $this->catalogue->canOpen(backpack_user())) {
            abort(403);
        }
        $input = [];
        foreach ((array) $request->input('settings', []) as $field => $value) {
            $input[str_replace('__', '.', (string) $field)] = $value;
        }

        $result = $this->catalogue->saveSection(backpack_user(), $tab, $section, $input);
        if ($result->code === 'AUTH_FORBIDDEN') {
            abort(403);
        }
        if (! $result->ok) {
            $errors = [];
            foreach ((array) $result->get('errors', []) as $key => $messages) {
                $errors['settings.'.str_replace('.', '__', $key)] = $messages;
            }

            return redirect()->route('utils.settings.index', ['tab' => $tab])->withErrors($errors ?: ['settings' => $result->message])->withInput();
        }

        $saved = count((array) $result->get('saved', []));
        $saved > 0
            ? Alert::success(__('utils.flash.settings_section_saved', ['count' => $saved]))->flash()
            : Alert::info(__('utils.flash.settings_nothing_changed'))->flash();

        return redirect()->route('utils.settings.index', ['tab' => $tab]);
    }

    /** One key, globally or for a scope (the overrides form). */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'value' => 'nullable|string|max:5000',
            'scope_type' => 'nullable|in:COMPANY,BRANCH,DESK',
            'scope_code' => 'nullable|string|max:50|required_with:scope_type',
        ]);
        $this->authorizeKey($data['key']);
        if ($request->boolean('keep_if_blank') && ($data['value'] ?? '') === '') {
            Alert::info(__('utils.flash.left_unchanged', ['key' => $data['key']]))->flash();

            return back();
        }
        $result = $this->settings->set($data['key'], $data['value'] ?? '', $data['scope_type'] ?? null, $data['scope_code'] ?? null, backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.saved', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    /** Upload the image of an image setting (logo, favicon). */
    public function image(Request $request): RedirectResponse
    {
        $maxKb = (int) setting('docs.max_upload_kb', 5120);
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'file' => "required|file|mimes:png,jpg,jpeg,webp,svg,gif,ico|max:{$maxKb}",
        ]);
        $this->authorizeKey($data['key']);
        $result = $this->settings->setImage($data['key'], $data['file'], backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.updated', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    /** Reset a key to its default, or remove one scope override. */
    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'scope_type' => 'nullable|in:COMPANY,BRANCH,DESK',
            'scope_code' => 'nullable|string|max:50|required_with:scope_type',
        ]);
        $this->authorizeKey($data['key']);
        $result = ! empty($data['scope_type'])
            ? $this->settings->clearScope($data['key'], $data['scope_type'], $data['scope_code'])
            : $this->settings->reset($data['key'], backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.reset', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    /** A key may be changed by whoever may open its tab (pricing keys: pricing managers too). */
    private function authorizeKey(string $key): void
    {
        if (! $this->catalogue->canEdit(backpack_user(), $key)) {
            abort(403);
        }
    }
}
