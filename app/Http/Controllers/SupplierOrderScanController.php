<?php

namespace App\Http\Controllers;

use App\Models\SupplierOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Closing a karigar order by scanning the QR on its office copy.
 *
 * The QR carries an opaque token, never a URL and never the id. Nothing about a
 * printed slip is therefore a link that a prefetcher, link previewer or antivirus
 * scanner could follow and act on, and one slip says nothing about any other order.
 *
 * Scanning happens here, inside the app, behind a login that may already delete
 * orders by hand — so the paper on its own grants nobody anything.
 */
class SupplierOrderScanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:supplier_order.delete')];
    }

    public function index(): View
    {
        return view('supplier-orders.scan');
    }

    /**
     * Delete whatever the scanned token points at, and say what went.
     *
     * Deliberately no confirmation step: one scan, gone. The row is soft-deleted and
     * the response carries what the page needs to offer an Undo, which is what makes
     * a mis-scan survivable without slowing the good case down.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $order = SupplierOrder::where('scan_token', trim($validated['token']))->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'message' => 'That code does not match any open order.',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'ok' => true,
            'id' => $order->id,
            'form_no' => $order->form_no,
            'supplier' => $order->supplier_name,
            'message' => "Order {$order->form_no} — {$order->supplier_name} deleted.",
            'undo_url' => route('supplier-orders.scan.restore', $order->id),
        ]);
    }

    /**
     * Put back an order removed by a wrong scan. Only a soft-deleted one can come
     * back, so this cannot resurrect anything that was never scanned away.
     */
    public function restore(int $id): RedirectResponse
    {
        $order = SupplierOrder::onlyTrashed()->find($id);

        if (! $order) {
            return back()->with('error', 'That order is not available to restore.');
        }

        $order->restore();

        return back()->with('success', "Order {$order->form_no} has been restored.");
    }
}
