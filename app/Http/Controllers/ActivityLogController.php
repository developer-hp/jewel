<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Who did what, and when.
 *
 * Read-only apart from the prune. Nothing on this screen edits a row: a log that the
 * people it watches can correct is not a log.
 */
class ActivityLogController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ActivityRecorder $recorder) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:activity_log.view', only: ['index', 'show']),
            new Middleware('permission:activity_log.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        // Buffered rows are not in the table yet, so without this the screen would be
        // up to four hours behind and quietly wrong. Bounded: a huge backlog is the
        // scheduled command's job, not something to make one page load wait for.
        Artisan::call('activity:flush', ['--max-chunks' => 2]);

        return view('activity-log.index', [
            'logs' => ActivityLog::LOGS,
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'pending' => $this->recorder->pending(),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = ActivityLog::query()
            ->select('activity_log.*')
            // Resolving the user per row would be 25 queries a draw.
            ->with('causer:id,name')
            ->ofLog($request->string('log')->toString() ?: null)
            ->betweenDates(
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null,
            )
            ->when($request->filled('causer'), fn ($q) => $q->where('causer_id', $request->integer('causer')));

        return DataTables::eloquent($query)
            ->editColumn('created_at', fn (ActivityLog $row) => $row->created_at->format('d-m-Y H:i:s'))
            ->addColumn('user', fn (ActivityLog $row) => e($row->causer?->name ?? 'System'))
            ->addColumn('type', function (ActivityLog $row) {
                [$label, $class] = $row->logBadge();

                return '<span class="badge '.$class.'">'.e($label).'</span>';
            })
            ->addColumn('subject', fn (ActivityLog $row) => e($row->subjectLabel()))
            ->addColumn('summary', fn (ActivityLog $row) => $this->summary($row))
            ->addColumn('action', fn (ActivityLog $row) => view('activity-log.partials.actions', ['row' => $row])->render())
            // Searching and sorting the computed columns has to be told which real
            // column stands behind them.
            ->filterColumn('user', fn ($q, $term) => $q->whereHas('causer', fn ($u) => $u->where('name', 'like', "%{$term}%")))
            ->filterColumn('summary', fn ($q, $term) => $q->where('description', 'like', "%{$term}%"))
            ->orderColumn('user', 'causer_id $1')
            ->orderColumn('type', 'log_name $1')
            ->orderColumn('summary', 'description $1')
            ->rawColumns(['type', 'summary', 'action'])
            ->toJson();
    }

    /**
     * One row's detail, as a fragment for the modal.
     */
    public function show(ActivityLog $activityLog): View
    {
        return view('activity-log.partials.detail', [
            'row' => $activityLog->load('causer:id,name'),
        ]);
    }

    /**
     * Drop everything older than a date.
     *
     * There is no scheduled clean — keeping the log is the default and losing any of
     * it is a deliberate act, so it happens here and nowhere else.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'before' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $deleted = ActivityLog::whereDate('created_at', '<', $validated['before'])->delete();

        return back()->with('success', number_format($deleted)
            .' activity row(s) before '.$request->date('before')->format('d-m-Y').' have been deleted.');
    }

    /**
     * The one-line summary in the listing.
     */
    private function summary(ActivityLog $row): string
    {
        $text = '<div>'.e($row->description).'</div>';

        $changes = $row->changes();

        if ($changes !== []) {
            $fields = implode(', ', array_slice(array_keys($changes), 0, 4));
            $more = count($changes) > 4 ? ' +'.(count($changes) - 4) : '';

            return $text.'<div class="text-muted fs-12">'.e($fields.$more).'</div>';
        }

        $context = $row->context();

        foreach (['path', 'username', 'filename'] as $key) {
            if (! empty($context[$key])) {
                return $text.'<div class="text-muted fs-12">'.e($context[$key]).'</div>';
            }
        }

        return $text;
    }
}
