<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMediaPrototypeTest extends TestCase
{
    use RefreshDatabase;

    private function asEventStaff(): self
    {
        return $this->actingAs(User::factory()->create(['is_staff' => true]));
    }

    public function test_staff_login_protects_upload_and_gallery_but_not_guest_media(): void
    {
        $this->get('/upload')->assertRedirect(route('login'));
        $this->get('/gallery')->assertRedirect(route('login'));
        $this->get('/media/moment-001')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_staff_can_sign_in_with_database_credentials(): void
    {
        User::factory()->create(['email' => 'staff@example.test', 'password' => 'test-password', 'is_staff' => true]);

        $this->post('/login', ['email' => 'staff@example.test', 'password' => 'test-password'])
            ->assertRedirect(route('upload'));

        $this->get('/upload')->assertOk();
    }

    public function test_upload_gallery_and_guest_pages_render(): void
    {
        $this->asEventStaff()->get('/upload')->assertOk()->assertSee('Share your photo');
        $this->asEventStaff()->get('/gallery')->assertOk()->assertSee('Media Gallery')->assertSee('No media yet')->assertDontSee('moment-001')->assertDontSee('media/paddock.jpg')->assertDontSee('Open on phone');
        $this->get('/media/moment-001')->assertOk()->assertSee('Download photo')->assertDontSee('Share / AirDrop');
        $this->get('/media/moment-032')->assertOk()->assertSee('Download photo');
        $this->get('/media/moment-003')->assertOk()->assertSee('<video', false);
    }

    public function test_gallery_states_and_feed_are_available(): void
    {
        $this->asEventStaff()->get('/gallery?state=empty')->assertOk()->assertSee('No media yet');
        $this->asEventStaff()->get('/gallery?state=error')->assertOk()->assertSee('Gallery unavailable');
        $this->asEventStaff()->get('/gallery?state=reconnecting')->assertOk()->assertSee('Reconnecting to gallery');
        $this->asEventStaff()->get('/gallery?state=loading')->assertOk()->assertSee('Loading media');
        $this->asEventStaff()->getJson('/gallery/feed')->assertOk()->assertJsonCount(0, 'items');
        $this->asEventStaff()->get('/gallery?sort=today')->assertOk()->assertSee('No media yet')->assertDontSee('moment-003');
        $this->asEventStaff()->get('/gallery?sort=yesterday')->assertOk()->assertSee('No media yet')->assertDontSee('moment-001');
        $this->asEventStaff()->getJson('/gallery/feed?sort=today')->assertOk()->assertJsonCount(0, 'items');
        $this->asEventStaff()->getJson('/gallery/feed?sort=yesterday')->assertOk()->assertJsonCount(0, 'items');
        $this->get('/media/not-found')->assertNotFound();
    }
}
