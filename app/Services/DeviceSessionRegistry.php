<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads and evicts a user's active sessions.
 *
 * Backed by the `database` session driver, which is the only one that records a
 * user_id per session and therefore the only one that can answer "is this account
 * signed in somewhere else?". With any other driver the single-device feature
 * degrades to a no-op rather than silently half-working.
 */
class DeviceSessionRegistry
{
    public function isSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    private function table(): string
    {
        return config('session.table', 'sessions');
    }

    /**
     * Sessions belonging to the user that are still inside the session lifetime,
     * excluding the given id. Expired rows linger until garbage collection runs,
     * so they are filtered out here rather than being reported as live devices.
     *
     * @return Collection<int, object>
     */
    public function otherSessions(User $user, ?string $exceptId = null): Collection
    {
        if (! $this->isSupported()) {
            return collect();
        }

        return DB::table($this->table())
            ->where('user_id', $user->getAuthIdentifier())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('last_activity', '>=', $this->cutoff())
            ->orderByDesc('last_activity')
            ->get();
    }

    public function hasOtherSessions(User $user, ?string $exceptId = null): bool
    {
        return $this->otherSessions($user, $exceptId)->isNotEmpty();
    }

    /**
     * Evict every session for the user except the one to keep, and cycle the
     * remember token so remember-me cookies on those devices cannot silently
     * re-authenticate them on the next request.
     *
     * @return int number of sessions removed
     */
    public function logoutOthers(User $user, ?string $keepId = null): int
    {
        if (! $this->isSupported()) {
            return 0;
        }

        $removed = DB::table($this->table())
            ->where('user_id', $user->getAuthIdentifier())
            ->when($keepId, fn ($q) => $q->where('id', '!=', $keepId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        return $removed;
    }

    /**
     * A human-readable description of a session row for the conflict screen.
     *
     * @return array{device: string, ip: string, last_active: Carbon}
     */
    public function describe(object $session): array
    {
        return [
            'device' => $this->deviceLabel($session->user_agent ?? ''),
            'ip' => $session->ip_address ?: 'unknown IP',
            'last_active' => Carbon::createFromTimestamp($session->last_activity),
        ];
    }

    /**
     * Best-effort browser and platform from the user agent. Only ever shown to the
     * account's own owner, so a rough label is enough.
     */
    private function deviceLabel(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            Str::contains($userAgent, 'Edg/') => 'Edge',
            Str::contains($userAgent, 'OPR/') => 'Opera',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            default => 'Browser',
        };

        $platform = match (true) {
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Mac OS') => 'macOS',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$platform}";
    }

    private function cutoff(): int
    {
        return now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();
    }
}
