<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Somewhere Meta can read the PDF from.
 *
 * The message carries a link rather than an uploaded file, which means the document
 * has to be fetchable from the public internet for as long as Meta takes to collect
 * it. Two consequences worth being clear about:
 *
 *  - It will not work while the app answers to a local hostname. Meta resolves
 *    APP_URL from its own network, so jewel.local is unreachable and the send fails.
 *  - Anyone holding the link can read the file. The stored name is forty random
 *    characters, so it cannot be guessed or enumerated, but it is not access
 *    controlled. Sweep the directory periodically; nothing here expires on its own.
 */
class WhatsAppDocumentStore
{
    public const DIRECTORY = 'whatsapp-documents';

    /**
     * Store the upload and return the link and the name the customer will see.
     *
     * @return array{link: string, filename: string, path: string}
     */
    public function put(UploadedFile $file): array
    {
        // Unguessable on disk; the original name is what WhatsApp displays.
        $stored = Str::random(40).'.pdf';

        $path = self::DIRECTORY.'/'.$stored;

        $file->storeAs(self::DIRECTORY, $stored, 'public');

        return [
            'link' => $this->absoluteUrl($path),
            'filename' => $this->displayName($file),
            'path' => $path,
        ];
    }

    /**
     * Meta fetches this from its own network, so a relative path is no use — which
     * is what the local disk returns. A remote disk already gives a full URL, so
     * only a relative one is completed against APP_URL.
     */
    private function absoluteUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /**
     * What the customer sees in WhatsApp: the name they were sent, kept safe and
     * always ending .pdf.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = trim(preg_replace('/[^\w \-.]+/u', '', $name) ?? '');

        return Str::limit($name === '' ? 'document' : $name, 60, '').'.pdf';
    }
}
