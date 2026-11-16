<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The landing page's "Get the app" section: a QR and a download per Android app. */
class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_scan_or_tap_to_download_the_apks(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('apps/picksell.apk', 'apk-bytes');

        $this->get('/')->assertOk()
            ->assertSee('id="get-the-app"', false)
            ->assertSee(rtrim(config('app.url'), '/').'/download/picksell')
            ->assertSee('<svg xmlns="http://www.w3.org/2000/svg" viewBox=', false)
            ->assertSee('Download APK')
            ->assertSee('Coming soon'); // Pickrush has no build yet

        $this->get('/download/picksell')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertDownload('PickSell.apk');

        $this->get('/download/pickrush')->assertRedirect('/#get-the-app')->assertSessionHas('status', 'The Pickrush app is coming soon.');
        $this->get('/download/other')->assertNotFound();
    }
}
