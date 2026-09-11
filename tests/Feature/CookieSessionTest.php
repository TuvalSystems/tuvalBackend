<?php
namespace Tests\Feature;

use App\Http\Controllers\Admin\AuthController;
use App\Http\Middleware\EnsureWebRequestsAreStateful;
use App\Http\Middleware\RequireMobileToken;
use App\Models\GpUsuario;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CookieSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'sanctum.stateful' => ['localhost:3000']]);
    }

    public function test_csrf_endpoint_sets_session_and_xsrf_cookies(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');
        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
        $response->assertCookie(config('session.cookie'));
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) $this->assertTrue($cookie->isHttpOnly());
        }
    }

    public function test_production_cookie_attributes_support_both_subdomains(): void
    {
        config([
            'session.domain' => '.tuvalsa.com',
            'session.secure' => true,
        ]);

        $response = $this->get('/sanctum/csrf-cookie');
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        $sessionCookie = $cookies->get(config('session.cookie'));
        $xsrfCookie = $cookies->get('XSRF-TOKEN');

        $this->assertSame('.tuvalsa.com', $sessionCookie->getDomain());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('.tuvalsa.com', $xsrfCookie->getDomain());
        $this->assertTrue($xsrfCookie->isSecure());
        $this->assertFalse($xsrfCookie->isHttpOnly());
    }

    public function test_production_frontend_origin_is_allowed_with_credentials(): void
    {
        config(['cors.allowed_origins' => ['https://web.tuvalsa.com']]);

        $this->withHeaders([
            'Origin' => 'https://web.tuvalsa.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/login')
            ->assertHeader('Access-Control-Allow-Origin', 'https://web.tuvalsa.com')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_web_login_creates_session_without_returning_a_token(): void
    {
        $user = new GpUsuario();
        $user->id = 77;
        $service = Mockery::mock(UserServiceInterface::class);
        $service->shouldReceive('authenticateUserWeb')->once()->with('tester', 'secret')->andReturnUsing(function () use ($user) {
            Auth::guard('web')->login($user);
            return ['user' => ['id' => 77], 'menu' => []];
        });
        $this->app->instance(UserServiceInterface::class, $service);
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', ['username' => 'tester', 'password' => 'secret'])
            ->assertOk()->assertJsonPath('data.user.id', 77)->assertJsonMissingPath('data.token');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guest_session_endpoint_is_unauthorized(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')->getJson('/api/session')->assertUnauthorized();
    }

    public function test_guest_cannot_use_session_migration_endpoint(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/session/migrate')
            ->assertUnauthorized();
    }

    public function test_legacy_web_token_is_exchanged_and_revoked(): void
    {
        $user = new GpUsuario();
        $user->id = 77;
        $token = Mockery::mock(PersonalAccessToken::class)->makePartial();
        $token->shouldReceive('can')->with('web')->andReturnTrue();
        $token->shouldReceive('delete')->once()->andReturnTrue();
        $user->withAccessToken($token);

        $service = Mockery::mock(UserServiceInterface::class);
        $service->shouldReceive('getWebSessionContext')->once()->with($user)
            ->andReturn(['user' => ['id' => 77], 'menu' => []]);

        $session = $this->app['session']->driver();
        $session->start();
        $oldId = $session->getId();
        $request = Request::create('/api/session/migrate', 'POST');
        $request->headers->set('Authorization', 'Bearer legacy-web-token');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = (new AuthController($service))->migrateSession($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($oldId, $session->getId());
    }

    public function test_untrusted_origin_does_not_receive_a_stateful_api_session(): void
    {
        $request = Request::create('/api/login', 'POST');
        $request->headers->set('Origin', 'https://untrusted.invalid');

        (new EnsureWebRequestsAreStateful())->handle($request, function ($request) {
            $this->assertFalse($request->hasSession());
            return response('ok');
        });
    }

    public function test_web_scoped_token_is_rejected_by_mobile(): void
    {
        $user = new GpUsuario();
        $user->withAccessToken(new PersonalAccessToken(['abilities' => ['web']]));
        $request = Request::create('/api/movil/menu');
        $request->headers->set('Authorization', 'Bearer example');
        $request->setUserResolver(fn () => $user);

        try {
            (new RequireMobileToken())->handle($request, fn () => response('unexpected'));
            $this->fail('Expected forbidden');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_mobile_token_is_accepted_without_a_session(): void
    {
        $user = new GpUsuario();
        $user->withAccessToken(new PersonalAccessToken(['abilities' => ['mobile']]));
        $request = Request::create('/api/movil/menu');
        $request->headers->set('Authorization', 'Bearer example');
        $request->setUserResolver(fn () => $user);
        $response = (new RequireMobileToken())->handle($request, fn () => response('ok'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_web_cookie_cannot_satisfy_mobile_token_requirement(): void
    {
        $user = new GpUsuario();
        $user->withAccessToken(new TransientToken());
        $request = Request::create('/api/movil/menu');
        $request->setUserResolver(fn () => $user);
        try {
            (new RequireMobileToken())->handle($request, fn () => response('unexpected'));
            $this->fail('Expected forbidden');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_mobile_requests_skip_spa_session_middleware_even_with_origin(): void
    {
        $request = Request::create('/api/movil/login', 'POST');
        $request->headers->set('Origin', 'http://localhost:3000');
        (new EnsureWebRequestsAreStateful())->handle($request, function ($request) {
            $this->assertFalse($request->hasSession());
            return response('ok');
        });
    }

    public function test_logout_invalidates_session_and_regenerates_csrf(): void
    {
        $user = new GpUsuario();
        $user->id = 77;
        Auth::guard('web')->login($user);
        $session = $this->app['session']->driver();
        $session->start();
        $session->put('probe', 'value');
        $oldId = $session->getId();
        $oldToken = $session->token();
        $request = Request::create('/api/logout', 'POST');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        $controller = new AuthController(Mockery::mock(UserServiceInterface::class));
        $this->assertSame(200, $controller->logout($request)->getStatusCode());
        $this->assertGuest('web');
        $this->assertNull($session->get('probe'));
        $this->assertNotSame($oldId, $session->getId());
        $this->assertNotSame($oldToken, $session->token());
    }
}
