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
            Alert::info("{$data['key']} left unchanged.")->flash();

            return back();
        }
        $result = $this->settings->set($data['key'], $data['value'] ?? '', $data['scope_type'] ?? null, $data['scope_code'] ?? null, backpack_user()->id);
        $result->ok ? Alert::success("{$data['key']} saved.")->flash() : Alert::error($result->message)->flash();

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
        $result->ok ? Alert::success("{$data['key']} reset.")->flash() : Alert::error($result->message)->flash();

        return back();
    }
}
