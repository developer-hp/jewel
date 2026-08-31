<?php

namespace App\Providers;

use App\Services\ActivityRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the activity log to Eloquent and to the auth events.
 *
 * Not the package's LogsActivity trait: that is opt-in per model, so a model added
 * next year is silently untracked until somebody remembers. Listening to the model
 * events instead catches every model there is, including changes made from tinker or
 * a console command rather than a form.
 */
class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * The write events worth a row.
     *
     * Named one by one, and NEVER as `eloquent.*`. A bare wildcard also catches
     * `retrieved`, which fires once for every hydrated row — a single DataTables page
     * would log two hundred rows and every listing in the app would crawl.
     */
    private const EVENTS = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function boot(): void
    {
        // The listeners are always registered and ActivityRecorder decides per event
        // whether to write. Gating registration here instead would freeze the choice
        // at boot, so flipping the setting — or a test turning it on — would do
        // nothing until the process restarted.
        foreach (self::EVENTS as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($event) {
                $model = $payload[0] ?? null;

                if ($model instanceof Model) {
                    $this->recordModel($event, $model);
                }
            });
        }

        $this->registerAuthListeners();
    }

    /**
     * One data row for a model write.
     */
    private function recordModel(string $event, Model $model): void
    {
        if ($this->ignored($model)) {
            return;
        }

        app(ActivityRecorder::class)->record(
            log: 'data',
            description: class_basename($model).' '.$event,
            subject: $model,
            properties: array_filter(['ip' => request()->ip()]),
            changes: $this->changes($event, $model),
            event: $event,
        );
    }

    private function ignored(Model $model): bool
    {
        foreach ((array) config('activity-log.ignore', []) as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * What changed, as [field => ['old' => …, 'new' => …]].
     *
     * An update records only the fields that moved. A create records what it was
     * created with, and a delete records nothing — the row itself is the fact, and
     * copying every column of a deleted record into the log would double the table.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changes(string $event, Model $model): array
    {
        $changes = [];

        if ($event === 'updated') {
            foreach ($model->getChanges() as $field => $new) {
                $changes[$field] = ['old' => $model->getOriginal($field), 'new' => $new];
            }
        } elseif ($event === 'created') {
            foreach ($model->getAttributes() as $field => $new) {
                $changes[$field] = ['old' => null, 'new' => $new];
            }
        }

        return $this->redact($changes);
    }

    /**
     * Drop the sensitive fields entirely — not masked, dropped.
     *
     * A password hash must never be written down anywhere. "We starred it out" is a
     * different and weaker promise than "it was never stored", and only the second
     * one is worth making.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function redact(array $changes): array
    {
        foreach ((array) config('activity-log.redact', []) as $field) {
            unset($changes[$field]);
        }

        // Noise, not secrets: the timestamps move on every update and the log row
        // carries its own created_at.
        foreach ((array) config('activity-log.ignore_attributes', []) as $field) {
            unset($changes[$field]);
        }

        return $changes;
    }

    /**
     * Sign in, sign out, and the attempts that failed.
     */
    private function registerAuthListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            $this->recordAuth('Signed in', $event->user, 'login');
        });

        Event::listen(Logout::class, function (Logout $event) {
            $this->recordAuth('Signed out', $event->user, 'logout');
        });

        Event::listen(Failed::class, function (Failed $event) {
            // No user to attribute it to — the username tried is the whole point of
            // the row when somebody is guessing a password.
            app(ActivityRecorder::class)->record(
                log: 'auth',
                description: 'Sign in failed',
                properties: $this->request() + [
                    'username' => $event->credentials['username'] ?? $event->credentials['email'] ?? null,
                ],
                event: 'failed',
            );
        });

        Event::listen(Lockout::class, function () {
            app(ActivityRecorder::class)->record(
                log: 'auth',
                description: 'Too many sign in attempts',
                properties: $this->request(),
                event: 'lockout',
            );
        });
    }

    private function recordAuth(string $description, $user, string $event): void
    {
        app(ActivityRecorder::class)->record(
            log: 'auth',
            description: $description,
            subject: $user instanceof Model ? $user : null,
            properties: $this->request(),
            event: $event,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function request(): array
    {
        return array_filter([
            'ip' => request()->ip(),
            'agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
