<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the credentials without starting a session.
     *
     * Login is a two-step affair when single-device mode is on: the password has
     * to be proven before we can tell the user that their account is signed in
     * elsewhere, but we must not log them in until they have chosen what to do.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'username' => $this->string('username')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            // Recorded here rather than through Illuminate's Failed event: that only
            // fires from Auth::attempt(), and this path uses Auth::validate() so the
            // single-device check can run before the session is started. The event
            // would never arrive, and a failed sign-in is the one row worth having.
            app(ActivityRecorder::class)->record(
                log: 'auth',
                description: 'Sign in failed',
                properties: array_filter([
                    'username' => $credentials['username'],
                    'ip' => $this->ip(),
                    'agent' => substr((string) $this->userAgent(), 0, 255),
                ]),
                event: 'failed',
            );

            throw ValidationException::withMessages([
                'username' => $this->failureMessage(),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return Auth::getLastAttempted();
    }

    /**
     * Distinguish a deactivated account from bad credentials, but only once the
     * password actually checks out — otherwise this would leak account state.
     */
    private function failureMessage(): string
    {
        $user = User::where('username', $this->string('username')->toString())->first();

        if ($user && ! $user->is_active && Hash::check($this->string('password')->toString(), $user->password)) {
            return 'Your account has been deactivated. Please contact an administrator.';
        }

        return 'These credentials do not match our records.';
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
