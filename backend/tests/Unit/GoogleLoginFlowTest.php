<?php

namespace Tests\Unit;

use App\Models\{Admin, Student};
use App\Services\GoogleTokenVerifier;
use Firebase\JWT\JWT;
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{Artisan, DB};
use phpseclib3\Crypt\RSA;

class GoogleLoginFlowTest extends \Illuminate\Foundation\Testing\TestCase
{
    private static string $privateKey;
    private static int $port;
    private static $server;
    private static array $pipes = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$privateKey = RSA::createKey(2048)->toString('PKCS8');
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::$privateKey));
        $jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'local-test-key',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])]]];
        self::$server = proc_open(['node', __DIR__.'/../Fixtures/google-certificates.mjs'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], self::$pipes);
        if (!is_resource(self::$server)) throw new \RuntimeException('Unable to start the local certificate fixture.');
        fwrite(self::$pipes[0], json_encode($jwks));
        fclose(self::$pipes[0]);
        stream_set_timeout(self::$pipes[1], 10);
        $ready = json_decode(fgets(self::$pipes[1]) ?: '{}', true);
        if (empty($ready['port'])) throw new \RuntimeException('Local certificate fixture did not start.');
        self::$port = $ready['port'];
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            foreach (self::$pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close(self::$server);
        }
        parent::tearDownAfterClass();
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            fn ($app) => $app['config']->set('cache.default', 'array'));
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'services.google.client_id' => 'local-client.apps.googleusercontent.com']);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        $this->useCertificateEndpoint('/certs');
    }

    private function useCertificateEndpoint(string $path): void
    {
        $stack = HandlerStack::create();
        // Route the real SDK's certificate request to a local HTTP service.
        $stack->push(Middleware::mapRequest(fn ($request) => $request->withUri(new Uri('http://127.0.0.1:'.self::$port.$path))));
        $this->app->instance(GoogleTokenVerifier::class, new GoogleTokenVerifier(new Client(['handler' => $stack, 'timeout' => 3])));
    }

    private function credential(array $changes = []): string
    {
        return JWT::encode(array_replace(['iss' => 'https://accounts.google.com',
            'aud' => 'local-client.apps.googleusercontent.com', 'sub' => 'local-google-subject',
            'email' => 'google-student@example.com', 'email_verified' => true,
            'given_name' => 'Google', 'family_name' => 'Student', 'iat' => time() - 5, 'exp' => time() + 300], $changes),
            self::$privateKey, 'RS256', 'local-test-key');
    }

    public function test_verified_student_sign_in_creates_one_account_and_accesses_profile(): void
    {
        $token = $this->credential();
        $response = $this->postJson('/api/auth/google-login', ['google_token' => $token])->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->withToken($response->json('token'))->getJson('/api/auth/profile')->assertOk()->assertJsonPath('student.email', 'google-student@example.com');
        $this->postJson('/api/auth/google-login', ['google_token' => $token])->assertOk();
        $this->assertSame(1, Student::count());
        $traffic = (new Client())->get('http://127.0.0.1:'.self::$port.'/requests');
        $this->assertGreaterThan(0, json_decode((string) $traffic->getBody(), true)['requests']);
    }

    public function test_invited_admin_signs_in_and_accesses_admin_profile(): void
    {
        Admin::create(['name' => 'Invited Admin', 'email' => 'google-admin@example.com', 'role' => 'exam_manager', 'status' => 'pending']);
        $response = $this->postJson('/api/admin/auth/google-login', ['google_token' => $this->credential(['email' => 'google-admin@example.com'])])
            ->assertOk()->assertJsonPath('admin.status', 'active')->assertJsonPath('admin.role', 'exam_manager');
        $this->withToken($response->json('token'))->getJson('/api/admin/auth/profile')->assertOk();
    }

    public function test_uninvited_and_suspended_admins_remain_blocked(): void
    {
        $credential = $this->credential();
        $this->postJson('/api/admin/auth/google-login', ['google_token' => $credential])->assertForbidden();
        Admin::create(['name' => 'Suspended', 'email' => 'google-student@example.com', 'role' => 'super_admin', 'status' => 'suspended']);
        $this->postJson('/api/admin/auth/google-login', ['google_token' => $credential])->assertForbidden();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_invalid_audience_expiry_and_malformed_credentials_are_rejected(): void
    {
        foreach ([$this->credential(['aud' => 'other-client']), $this->credential(['exp' => time() - 300]), 'not.a.valid-token'] as $credential) {
            foreach (['/api/auth/google-login', '/api/admin/auth/google-login'] as $route) {
                $this->postJson($route, ['google_token' => $credential])->assertUnauthorized();
            }
        }
        $this->assertSame(0, Student::count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_google_outage_is_reported_as_service_unavailable_in_both_portals(): void
    {
        $this->useCertificateEndpoint('/unavailable');
        foreach (['/api/auth/google-login', '/api/admin/auth/google-login'] as $route) {
            $this->postJson($route, ['google_token' => $this->credential()])->assertStatus(503)
                ->assertJsonPath('message', 'Google sign-in is temporarily unavailable. Please try again shortly or contact the portal administrator.');
        }
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }
}
