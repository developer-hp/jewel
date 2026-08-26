<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsAppReceiverRequest;
use App\Models\WhatsAppReceiver;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Who the day opening's reports go to.
 */
class WhatsAppReceiverController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['index']),
            new Middleware('permission:app_setting.edit', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('whatsapp-receivers.index');
    }

    private function data(): JsonResponse
    {
        $query = WhatsAppReceiver::query()->select('whatsapp_receivers.*');

        return DataTables::eloquent($query)
            // Shown as WhatsApp will see it, so an unsendable number is obvious
            // here rather than in a log after an opening.
            ->addColumn('sendable', function (WhatsAppReceiver $receiver) {
                $number = PhoneNumber::toE164($receiver->mobile);

                return $number
                    ? '<code>'.e($number).'</code>'
                    : '<span class="badge bg-danger">Unsendable</span>';
            })
            ->addColumn('status', fn (WhatsAppReceiver $receiver) => view('components.status-badge', ['active' => $receiver->is_active])->render())
            ->addColumn('action', fn (WhatsAppReceiver $receiver) => view('whatsapp-receivers.partials.actions', compact('receiver'))->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['sendable', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('whatsapp-receivers.create', ['receiver' => new WhatsAppReceiver]);
    }

    public function store(WhatsAppReceiverRequest $request): RedirectResponse
    {
        $receiver = WhatsAppReceiver::create($request->validated());

        return redirect()->route('whatsapp-receivers.index')
            ->with('success', "{$receiver->name} will receive the opening reports.");
    }

    public function edit(WhatsAppReceiver $whatsappReceiver): View
    {
        return view('whatsapp-receivers.edit', ['receiver' => $whatsappReceiver]);
    }

    public function update(WhatsAppReceiverRequest $request, WhatsAppReceiver $whatsappReceiver): RedirectResponse
    {
        $whatsappReceiver->update($request->validated());

        return redirect()->route('whatsapp-receivers.index')
            ->with('success', "{$whatsappReceiver->name} has been updated.");
    }

    public function destroy(WhatsAppReceiver $whatsappReceiver): RedirectResponse
    {
        $name = $whatsappReceiver->name;
        $whatsappReceiver->delete();

        return redirect()->route('whatsapp-receivers.index')
            ->with('success', "{$name} will no longer receive the reports.");
    }
}
