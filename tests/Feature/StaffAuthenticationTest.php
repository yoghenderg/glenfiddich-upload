<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_password_and_non_staff_accounts_cannot_login(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->is_staff = false;
        $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_legacy_session_flag_does_not_grant_access(): void
    {
        $this->withSession(['event_staff.authenticated' => true])->get('/upload')->assertRedirect('/login');
        $this->getJson('/gallery/feed')->assertUnauthorized();
    }

    public function test_non_staff_user_cannot_access_staff_routes(): void
    {
        $this->actingAs(User::factory()->create())->get('/upload')->assertForbidden();
        $this->getJson('/gallery/feed')->assertForbidden();
    }

    public function test_staff_login_honors_intended_destination_and_logout_revokes_access(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        $this->get('/gallery')->assertRedirect('/login');
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password'])->assertRedirect('/gallery');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/gallery')->assertRedirect('/login');
    }

    public function test_repeated_login_attempts_are_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_staff_seeder_creates_and_updates_account_without_duplicates(): void
    {
        config()->set('event-staff', [
            'name' => 'Staff', 'email' => 'STAFF@example.test', 'password' => 'a-long-test-password',
        ]);
        $this->seed();
        $user = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue($user->is_staff);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        $this->seed();
        $this->assertSame($user->password, $user->fresh()->password);
        config()->set('event-staff.password', 'another-test-password');
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('another-test-password', $user->fresh()->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'another-test-password'])->assertRedirect('/upload');
        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_seeder_rejects_missing_credentials(): void
    {
        config()->set('event-staff', ['name' => 'Staff', 'email' => '', 'password' => '']);
        try {
            $this->seed();
            $this->fail('Missing credentials must not seed an account.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('password', $exception->errors());
            $this->assertDatabaseCount('users', 0);
        }
    }
}
