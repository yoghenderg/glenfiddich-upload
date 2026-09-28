<?php

namespace Tests\Feature;

use Tests\TestCase;

class EventMediaPrototypeTest extends TestCase
{
    public function test_upload_gallery_and_guest_pages_render(): void
    {
        $this->get('/upload')->assertOk()->assertSee('Share your photo');
        $this->get('/gallery')->assertOk()->assertSee('Media Gallery')->assertSee(today()->format('d-m-Y'))->assertSee('02:35 PM')->assertDontSee('Open on phone');
        $this->get('/media/moment-001')->assertOk()->assertSee('Download photo')->assertDontSee('Share / AirDrop');
        $this->get('/media/moment-003')->assertOk()->assertSee('<video', false);
    }

    public function test_gallery_states_and_feed_are_available(): void
    {
        $this->get('/gallery?state=empty')->assertOk()->assertSee('No media yet');
        $this->get('/gallery?state=error')->assertOk()->assertSee('Gallery unavailable');
        $this->get('/gallery?state=reconnecting')->assertOk()->assertSee('Reconnecting to gallery');
        $this->get('/gallery?state=loading')->assertOk()->assertSee('Loading media');
        $this->getJson('/gallery/feed')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('items.0.time_display', '02:35 PM');
        $this->get('/gallery?sort=today')->assertOk()->assertSee('id="media-count">2', false)->assertDontSee('moment-003');
        $this->get('/gallery?sort=yesterday')->assertOk()->assertSee('id="media-count">1', false)->assertDontSee('moment-001');
        $this->getJson('/gallery/feed?sort=today')->assertOk()->assertJsonCount(2, 'items');
        $this->getJson('/gallery/feed?sort=yesterday')->assertOk()->assertJsonCount(1, 'items');
        $this->get('/media/not-found')->assertNotFound();
    }
}
