<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaMetadataTest extends TestCase
{
    public function test_app_layout_exposes_pwa_metadata_and_icons(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest" href="'.asset('manifest.webmanifest').'"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", false);

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Ray-Ban Moments', $manifest['name']);
        $this->assertSame('/upload', $manifest['start_url']);
        $this->assertFileExists(public_path('sw.js'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('favicon-32.png'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }
}
