<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\DeviceSessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Session key holding a login that passed the password check but is waiting on
     * the user to decide what to do about their other device.
     */
    private const PENDING_KEY = 'auth.pending_login';

    /** A pending decision is only good for a few minutes. */
    private const PENDING_TTL_SECONDS = 300;

    public function __construct(private readonly DeviceSessionRegistry $sessions) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if ($this->shouldAskAboutOtherDevices($user)) {
            $request->session()->put(self::PENDING_KEY, [
                'user_id' => $user->getAuthIdentifier(),
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addSeconds(self::PENDING_TTL_SECONDS)->getTimestamp(),
            ]);

            return redirect()->route('login.conflict');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    /**
     * The "you are signed in elsewhere" prompt.
     */
    public function showConflict(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingLogin($request);

        if (! $pending) {
            return redirect()->route('login');
        }

        $user = User::find($pending['user_id']);

        if (! $user) {
            $request->session()->forget(self::PENDING_KEY);

            return redirect()->route('login');
        }

        return view('auth.session-conflict', [
            'username' => $user->username,
            'devices' => $this->sessions->otherSessions($user, $request->session()->getId())
                ->map(fn ($session) => $this->sessions->describe($session)),
        ]);
    }

    /**
     * Either evict the other device and continue, or abandon this sign-in.
     */
    public function resolveConflict(Request $request): RedirectResponse
    {
        $request->validate(['action' => ['required', 'in:continue,cancel']]);

        $pending = $this->pendingLogin($request);

        if (! $pending) {
            return redirect()->route('login')
                ->withErrors(['username' => 'That sign-in attempt expired. Please try again.']);
        }

        $request->session()->forget(self::PENDING_KEY);

        if ($request->string('action')->toString() === 'cancel') {
            return redirect()->route('login')->with('status', 'Sign-in cancelled. Your other device is still signed in.');
        }

        $user = User::find($pending['user_id']);

        if (! $user || ! $user->is_active) {
            return redirect()->route('login')
                ->withErrors(['username' => 'That account is no longer available.']);
        }

        // Evict first: logoutOthers cycles the remember token, and completeLogin
        // then issues this device a cookie carrying the new one.
        $this->sessions->logoutOthers($user);

        return $this->completeLogin($request, $user, (bool) $pending['remember'], 'Your other device has been signed out.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }

    private function completeLogin(Request $request, User $user, bool $remember, ?string $status = null): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();

        // Start the idle clock from the moment of login.
        $request->session()->put('last_activity_at', now()->getTimestamp());

        return redirect()->intended(route('dashboard'))->with('status', $status);
    }

    private function shouldAskAboutOtherDevices(User $user): bool
    {
        return AppSetting::resolved()->single_device_login
            && $this->sessions->isSupported()
            && $this->sessions->hasOtherSessions($user, session()->getId());
    }

    /**
     * @return array{user_id: int, remember: bool, expires_at: int}|null
     */
    private function pendingLogin(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }
}
