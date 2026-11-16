<?php

namespace App\Http\Controllers;

use App\Pickrush\Support\Codes;
use Illuminate\Support\Facades\Storage;

/** The landing page's Android app downloads (config/mobile-apps.php), and the QR codes that point at them. */
class AppDownloadController extends Controller
{
    private const APK_TYPE = 'application/vnd.android.package-archive';

    /** GET /download/{app}: the APK, or back to the landing page's apps when it isn't published yet. */
    public function __invoke(string $app)
    {
        $config = config("mobile-apps.$app") ?? abort(404);
        $disk = Storage::disk('local');
        if (! $disk->exists($config['file'])) {
            return redirect('/#get-the-app')->with('status', "The {$config['name']} app is coming soon.");
        }

        return $disk->download($config['file'], $config['download_as'], ['Content-Type' => self::APK_TYPE]);
    }

    /**
     * The apps for the landing page: name, download link, whether it is out yet, and its QR as inline SVG.
     *
     * @return list<array{key: string, name: string, tagline: string, description: string, url: string, available: bool, qr: string, size: ?string}>
     */
    public static function apps(): array
    {
        $disk = Storage::disk('local');

        return collect(config('mobile-apps'))->map(function (array $app, string $key) use ($disk) {
            // Always the marketplace's address (APP_URL), so the QR works from the logistics site too.
            $url = rtrim(config('app.url'), '/').'/download/'.$key;
            $available = $disk->exists($app['file']);

            return $app + [
                'key' => $key,
                'url' => $url,
                'available' => $available,
                'size' => $available ? number_format($disk->size($app['file']) / 1048576, 0).' MB' : null,
                'qr' => self::qrSvg($url),
            ];
        })->values()->all();
    }

    /** A QR code as a crisp, scalable SVG (one path, so it stays light in the page). */
    private static function qrSvg(string $text): string
    {
        $rows = Codes::qr($text);
        $size = count($rows);
        $path = '';
        foreach ($rows as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="crispEdges" role="img" aria-hidden="true">'
            .'<rect width="100%" height="100%" fill="#fff"/><path fill="#1b1b1b" d="'.$path.'"/></svg>';
    }
}
