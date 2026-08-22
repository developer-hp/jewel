<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\SupplierOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The karigar receipt: two copies per order, the second of which is the office copy
 * and carries the QR that closes the order when scanned.
 */
class SupplierOrderPrintController extends Controller implements HasMiddleware
{
    /** Printed size of the QR on the office copy. */
    private const QR_MM = 22;

    public static function middleware(): array
    {
        return [new Middleware('permission:supplier_order.print')];
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:supplier_orders,id'],
        ]);

        $orders = SupplierOrder::whereIn('id', $validated['ids'])->orderBy('form_no')->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'Those orders no longer exist.');
        }

        return Pdf::loadView('supplier-orders.print', [
            'orders' => $orders,
            'header' => (string) (AppSetting::current()->supplier_order_header ?? ''),
            'qrMm' => self::QR_MM,
            // Both keyed by id and resolved once per order, not once per copy — the
            // two copies show the same picture and the same code, and base64 is not
            // worth doing twice.
            'qrCodes' => $orders->mapWithKeys(fn (SupplierOrder $order) => [
                $order->id => $this->qrDataUri($order->scan_token),
            ]),
            'photos' => $orders->mapWithKeys(fn (SupplierOrder $order) => [
                $order->id => $order->photoDataUri(),
            ]),
        ])->setPaper('a4', 'portrait')
            ->stream('karigar-'.now()->format('Y-m-d-His').'.pdf', ['Attachment' => false]);
    }

    /**
     * The token as a QR, embedded as a data URI so dompdf never reaches for a file.
     *
     * Same shape as ItemLabelBuilder's: rendered well above the printed size so it
     * survives a laser printer and still reads on a phone camera.
     */
    private function qrDataUri(string $token): string
    {
        $qrCode = new QrCode(
            data: $token,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: max(120, self::QR_MM * 12),
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new PngWriter)->write($qrCode)->getDataUri();
    }
}
