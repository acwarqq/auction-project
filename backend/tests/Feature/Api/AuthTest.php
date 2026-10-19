<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only attaches session/cookie middleware to requests coming
        // "from the frontend" (matched by Referer/Origin against
        // sanctum.stateful). Send an Origin header so /api/login etc. get a
        // session in tests, exactly like a browser would.
        $stateful = array_filter(array_map('trim', (array) config('sanctum.stateful')));
        $host = reset($stateful) ?: 'localhost';
        $this->withHeader('Origin', 'http://'.$host);
    }

    /*
    |---------------------------------------------------------------------
    | Helpers
    |---------------------------------------------------------------------
    |
    | In real HTTP each request boots a fresh application, so auth guards
    | are re-created (and the default guard reset) on every request. The
    | test process keeps one application alive between requests, and
    | auth:sanctum switches the default guard to "sanctum" (which caches
    | the resolved user). Forget all guards before each request to mimic
    | the production lifecycle; assert against the "web" guard explicitly.
    |
    */

    private function apiPost(string $uri, array $payload = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson($uri, $payload);
    }

    private function apiGet(string $uri): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson($uri);
    }

    private function login(User $user, string $password = 'password'): TestResponse
    {
        return $this->apiPost('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    private function validRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_register_creates_user_with_pending_status(): void
    {
        $response = $this->apiPost('/api/register', $this->validRegisterPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user',
            'status' => 'pending',
        ]);

        $this->assertGuest('web');
    }

    public function test_role_and_status_are_not_mass_assignable(): void
    {
        // API payload trying to escalate privileges is ignored.
        $this->apiPost('/api/register', $this->validRegisterPayload([
            'role' => 'admin',
            'status' => 'approved',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user',
            'status' => 'pending',
        ]);

        // Model level: fill() cannot touch role/status either.
        $user = User::factory()->create();
        $user->fill(['role' => 'admin', 'status' => 'approved']);
        $user->save();
        $user->refresh();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_pending_user_cannot_login_and_gets_403_with_status(): void
    {
        $user = User::factory()->create(['email' => 'pending@example.com']);

        $this->login($user)
            ->assertStatus(403)
            ->assertJsonPath('status', 'pending')
            ->assertJsonStructure(['message', 'status']);

        $this->assertGuest('web');
    }

    public function test_unapproved_login_logs_out_existing_session(): void
    {
        // An approved user is authenticated...
        $user = User::factory()->approved()->create();
        $this->login($user)->assertOk();
        $this->apiGet('/api/me')->assertOk();

        // ...then gets demoted to pending.
        $user->status = UserStatus::Pending;
        $user->save();

        // Re-login returns 403 AND wipes the previously valid session,
        // so /api/me must no longer answer 200.
        $this->login($user)
            ->assertStatus(403)
            ->assertJsonPath('status', 'pending');

        $this->assertGuest('web');
        $this->apiGet('/api/me')->assertStatus(401);
    }

    public function test_rejected_and_banned_users_cannot_login(): void
    {
        $rejected = User::factory()->rejected()->create();
        $this->login($rejected)->assertStatus(403)->assertJsonPath('status', 'rejected');

        $banned = User::factory()->banned()->create();
        $this->login($banned)->assertStatus(403)->assertJsonPath('status', 'banned');

        $this->assertGuest('web');
    }

    public function test_approved_user_can_login_me_and_logout(): void
    {
        $user = User::factory()->approved()->create(['email' => 'ok@example.com']);

        $this->login($user)
            ->assertOk()
            ->assertJsonPath('data.email', 'ok@example.com')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.status', 'approved');

        $this->apiGet('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ok@example.com');

        $this->apiPost('/api/logout')->assertOk();
        $this->assertGuest('web');

        $this->apiGet('/api/me')->assertStatus(401);
    }

    public function test_wrong_password_returns_401(): void
    {
        $user = User::factory()->approved()->create();

        $this->apiPost('/api/login', [
            'email' => $user->email,
            'password' => 'totally-wrong',
        ])->assertStatus(401);

        $this->assertGuest('web');
    }

    public function test_me_requires_authentication(): void
    {
        $this->apiGet('/api/me')->assertStatus(401);
    }

    public function test_login_is_throttled_after_six_attempts_per_minute(): void
    {
        $user = User::factory()->approved()->create();

        for ($i = 1; $i <= 6; $i++) {
            $this->login($user)->assertOk();
        }

        $this->login($user)->assertStatus(429);
    }

    public function test_register_is_throttled_after_five_attempts_per_minute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->apiPost('/api/register', $this->validRegisterPayload([
                'email' => "user{$i}@example.com",
            ]))->assertStatus(201);
        }

        $this->apiPost('/api/register', $this->validRegisterPayload([
            'email' => 'user6@example.com',
        ]))->assertStatus(429);
    }
}
