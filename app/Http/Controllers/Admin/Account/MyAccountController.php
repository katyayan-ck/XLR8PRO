<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Services\IAM\MyAccountService;
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
        \Alert::success('Your display name was updated.')->flash();

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
        \Alert::success($photo ? 'Your profile photo was updated.' : 'Your profile photo was removed.')->flash();

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
        \Alert::success('Your password was changed. Other sessions were signed out.')->flash();

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
