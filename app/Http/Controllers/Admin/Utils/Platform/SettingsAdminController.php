<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Settings admin (FRS §1): every setting grouped by key prefix, with search, last change,
 * per-scope overrides (company / branch / desk) and reset to default.
 */
class SettingsAdminController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403);
        }

        return view('admin.utils.platform.settings.index', [
            'title' => 'Settings',
            'search' => (string) $request->query('q', ''),
            'groups' => $this->settings->adminList($request->query('q')),
            'canManage' => backpack_user()->can('UTL_SETTINGS_MANAGE'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403);
        }
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'value' => 'nullable|string|max:5000',
            'scope_type' => 'nullable|in:COMPANY,BRANCH,DESK',
            'scope_code' => 'nullable|string|max:50|required_with:scope_type',
        ]);
        if ($request->boolean('keep_if_blank') && ($data['value'] ?? '') === '') {
            Alert::info(__('utils.flash.left_unchanged', ['key' => $data['key']]))->flash();

            return back();
        }
        $result = $this->settings->set($data['key'], $data['value'] ?? '', $data['scope_type'] ?? null, $data['scope_code'] ?? null, backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.saved', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    /** Upload the image of an image setting (e.g. branding.logo, DEC-083). */
    public function image(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403);
        }
        $maxKb = (int) setting('docs.max_upload_kb', 5120);
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'file' => "required|file|mimes:png,jpg,jpeg,webp,svg,gif|max:{$maxKb}",
        ]);
        $result = $this->settings->setImage($data['key'], $data['file'], backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.updated', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403);
        }
        $data = $request->validate([
            'key' => 'required|string|max:150',
            'scope_type' => 'nullable|in:COMPANY,BRANCH,DESK',
            'scope_code' => 'nullable|string|max:50|required_with:scope_type',
        ]);
        $result = ! empty($data['scope_type'])
            ? $this->settings->clearScope($data['key'], $data['scope_type'], $data['scope_code'])
            : $this->settings->reset($data['key'], backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.reset', ['key' => $data['key']]))->flash() : Alert::error($result->message)->flash();

        return back();
    }
}
