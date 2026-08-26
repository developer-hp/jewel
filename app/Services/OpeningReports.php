<?php

namespace App\Services;

use App\Models\Item;
use App\Support\PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The three PDFs the day opening sends out.
 *
 * All three cover the same window — everything since the last opening — because that
 * is what "the day" means once the opening itself defines the boundary. Nothing here
 * deletes anything; it only reads and renders, so it can be run on its own to see
 * what an opening *would* report.
 */
class OpeningReports
{
    /** Where the PDFs are written so WhatsApp can fetch them. */
    public const DIRECTORY = 'opening-reports';

    /**
     * Render the three reports and put them where they can be linked to.
     *
     * @return array<int, array{key: string, title: string, link: string, filename: string, count: int}>
     */
    public function build(Carbon $since, Carbon $until): array
    {
        $sold = $this->soldItems($since, $until);
        $added = $this->addedItems($since, $until);

        return [
            $this->render('sold', 'Sold Items', 'opening-reports.items', [
                'heading' => 'Sold Items',
                'items' => $sold,
                'since' => $since,
                'until' => $until,
                'showPhotos' => false,
                'dateColumn' => 'sold',
            ]),
            $this->render('sold-photos', 'Sold Items with Photos', 'opening-reports.items', [
                'heading' => 'Sold Items',
                'items' => $sold,
                'since' => $since,
                'until' => $until,
                'showPhotos' => true,
                'dateColumn' => 'sold',
            ]),
            $this->render('added', 'Added Items', 'opening-reports.items', [
                'heading' => 'Added Items',
                'items' => $added,
                'since' => $since,
                'until' => $until,
                'showPhotos' => false,
                'dateColumn' => 'added',
            ]),
        ];
    }

    /**
     * Everything sold in the window.
     *
     * Half-open on the left and closed on the right, so a piece is reported by
     * exactly one opening however close to the boundary it fell.
     */
    public function soldItems(Carbon $since, Carbon $until)
    {
        return Item::query()
            ->with(['itemGroup:id,name', 'metalType:id,name', 'purity:id,name'])
            ->whereNotNull('sold_at')
            ->where('sold_at', '>', $since)
            ->where('sold_at', '<=', $until)
            ->orderBy('sold_at')
            ->get();
    }

    public function addedItems(Carbon $since, Carbon $until)
    {
        return Item::query()
            ->with(['itemGroup:id,name', 'metalType:id,name', 'purity:id,name'])
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $until)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key: string, title: string, link: string, filename: string, count: int}
     */
    private function render(string $key, string $title, string $view, array $data): array
    {
        $pdf = PdfDocument::render($view, $data, PdfDocument::a4());

        // Unguessable, because the link is what WhatsApp fetches and anyone holding
        // it can read the file.
        $stored = self::DIRECTORY.'/'.Str::random(40).'.pdf';

        Storage::disk('public')->put($stored, $pdf);

        $filename = Str::slug($title).'-'.$data['until']->format('Y-m-d').'.pdf';

        return [
            'key' => $key,
            'title' => $title,
            'link' => $this->absoluteUrl($stored),
            'filename' => $filename,
            'count' => $data['items']->count(),
        ];
    }

    /**
     * WhatsApp fetches this from its own network, so a relative path is no use —
     * which is what the local disk hands back.
     */
    private function absoluteUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
