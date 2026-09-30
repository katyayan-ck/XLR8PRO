<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Services\IAM\MyAccountService;
use App\Services\Person\PersonRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * My Account (DEC-072): the signed-in user's profile, organisation, scopes, history and contacts (read-only) plus
 * display name, profile photo and password. No module permission — every action works on backpack_user() only.
 */
class MyAccountController extends Controller
{
    public function __construct(private readonly MyAccountService $account) {}

    public function show(): View
    {
        $user = backpack_user();
        abort_unless($user, 403);

        $profile = $this->account->profile($user);

        return view('admin.account.show', $profile + [
            'access' => $this->account->access($user, $profile['primaries'], $profile['addons']),
            'editable' => $this->account->editablePersonalFields($user),
            'genders' => PersonRecordService::GENDERS,
            'maritalStatuses' => PersonRecordService::MARITAL_STATUSES,
            'title' => 'My Account',
            'scopeLevels' => MyAccountService::SCOPE_LEVELS,
            'imageTypes' => 'image/jpeg,image/png,image/webp',
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = backpack_user();
        abort_unless($user, 403);
        $this->allowed('account.can_change_display_name', 'display name');

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
        ], [], ['display_name' => __('org.fields.display_name')]);

        $this->account->updateDisplayName($user, $validated['display_name']);
        \Alert::success(__('iam.flash.display_name_updated'))->flash();

        return redirect()->route('backpack.account.info');
    }

    /** Personal details the Settings allow users to change themselves (DEC-091, Settings → User behaviour). */
    public function updatePersonal(Request $request): RedirectResponse
    {
        $user = backpack_user();
        abort_unless($user, 403);
        $allowed = $this->account->editablePersonalFields($user);
        if ($allowed === []) {
            abort(403, 'Your personal details are managed by your administrator.');
        }

        $rules = array_intersect_key([
            'email' => ['nullable', 'email', 'max:150'], 'mobile' => ['nullable', 'string', 'max:15'],
            'aadhaar_no' => ['nullable', 'string', 'max:14'], 'pan_no' => ['nullable', 'string', 'max:10'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'], 'joining_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', 'string', 'max:30'],
        ], array_flip($allowed));
        $validated = $request->validateWithBag('personal', $rules, [], array_combine(array_keys($rules), array_map(fn ($f) => __('org.fields.'.$f), array_keys($rules))));

        try {
            $changed = $this->account->updatePersonal($user, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'personal')->withInput();
        }
        $changed === []
            ? \Alert::info(__('utils.flash.settings_nothing_changed'))->flash()
            : \Alert::success(__('iam.flash.personal_details_updated'))->flash();

        return redirect()->route('backpack.account.info');
    }

    public function updatePhoto(Request $request): RedirectResponse|JsonResponse
    {
        $user = backpack_user();
        abort_unless($user, 403);
        $this->allowed('account.can_change_photo', 'profile photo');

        $maxKb = (int) setting('docs.max_upload_kb', 10240);
        $validated = $request->validate([
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', "max:{$maxKb}"],
            'remove' => ['nullable', 'boolean'],
        ], [], ['profile_photo' => 'profile photo']);

        $photo = $request->file('profile_photo');
        if (! $photo && ! $request->boolean('remove')) {
            return back()->withErrors(['profile_photo' => 'Choose a photo to upload.']);
        }

        $person = $this->account->updatePhoto($user, $photo);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'url' => $this->account->photoUrl($person)]);
        }
        \Alert::success(__($photo ? 'iam.flash.profile_photo_updated' : 'iam.flash.profile_photo_removed'))->flash();

        return redirect()->route('backpack.account.info');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $user = backpack_user();
        abort_unless($user, 403);

        $this->allowed('account.can_change_password', 'password');
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', self::passwordRule()],
        ], [], ['current_password' => 'current password', 'new_password' => 'new password']);

        try {
            $this->account->changePassword($user, $validated['current_password'], $validated['new_password']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'password');
        }
        \Alert::success(__('iam.flash.password_changed_other_sessions_were_signed'))->flash();

        return redirect()->route('backpack.account.info');
    }

    /**
     * The password rule from Settings (go-live to-do S5): `account.password_min_length` (8), letters + numbers always,
     * `account.password_require_mixed_case`, `account.password_require_symbols` (both off by default).
     */
    public static function passwordRule(): Password
    {
        $rule = Password::min(max(8, (int) setting('account.password_min_length', 8)))->letters()->numbers();
        if (setting('account.password_require_mixed_case', false)) {
            $rule->mixedCase();
        }
        if (setting('account.password_require_symbols', false)) {
            $rule->symbols();
        }

        return $rule;
    }

    /** Self-service switches (go-live to-do S7); an administrator can always change these on the user record. */
    private function allowed(string $setting, string $what): void
    {
        if (! setting($setting, true)) {
            abort(403, "Your {$what} is managed by your administrator.");
        }
    }
}
