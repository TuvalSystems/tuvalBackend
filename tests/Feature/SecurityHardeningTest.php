<?php

namespace Tests\Feature;

use App\Models\GpUsuario;
use App\Models\Perfiles;
use App\Models\Section;
use App\Supports\SafeUploadName;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('perfiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform');
            $table->string('state');
            $table->timestamps();
        });
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform');
            $table->string('route_name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('perfil_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_id');
            $table->unsignedBigInteger('section_id');
            $table->boolean('state')->default(true);
            $table->string('home')->nullable();
            $table->timestamps();
        });
        Schema::create('gp_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('USERID');
            $table->unsignedBigInteger('perfil_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    private function user(bool $active = true, string $profileState = 'active'): GpUsuario
    {
        $profile = Perfiles::create(['name' => 'Security fixture', 'platform' => 'web', 'state' => $profileState]);
        $user = new GpUsuario;
        $user->forceFill(['id' => 123, 'USERID' => 'security-fixture', 'status' => $active,
            'perfil_id' => $profile->id, 'must_change_password' => false]);
        return $user;
    }

    private function grant(GpUsuario $user, string $route, bool $active = true): void
    {
        $section = new Section;
        $section->forceFill(['name' => $route, 'platform' => 'web', 'route_name' => $route, 'status' => true]);
        $section->save();
        $user->perfil->sections()->attach($section->id, ['state' => $active]);
    }

    public function test_regular_user_cannot_reset_passwords_or_change_profiles(): void
    {
        Sanctum::actingAs($this->user(), ['web']);
        $this->postJson('/api/usuario/reset-password', ['id' => 999])->assertForbidden();
        $this->postJson('/api/usuario/actualizar-perfiles', ['form' => ['user_id' => 999]])->assertForbidden();
        $this->postJson('/api/perfiles/crear', [])->assertForbidden();
        $this->postJson('/api/admin/app/versions', [])->assertForbidden();
        $this->postJson('/api/admin/lista-logs', [])->assertForbidden();
    }

    public function test_active_section_grant_allows_administration_but_inactive_grant_does_not(): void
    {
        $user = $this->user();
        $this->grant($user, 'sistemas-seguridad-perfiles');
        Sanctum::actingAs($user, ['web']);
        $this->getJson('/api/perfiles/lista')->assertOk();
        $user->perfil->sections()->updateExistingPivot(1, ['state' => false]);
        $this->getJson('/api/perfiles/lista')->assertForbidden();
    }

    public function test_disabled_account_and_disabled_profile_cannot_use_existing_tokens(): void
    {
        Sanctum::actingAs($this->user(false), ['web']);
        $this->getJson('/api/user')->assertForbidden();
        Sanctum::actingAs($this->user(true, 'inactive'), ['web']);
        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_mobile_token_cannot_use_web_api(): void
    {
        Sanctum::actingAs($this->user(), ['mobile']);
        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_non_string_legacy_api_token_cannot_authenticate_to_sanctum(): void
    {
        $this->getJson('/api/user?api_token[0]=0')->assertUnauthorized();
        $this->postJson('/api/usuario/reset-password', ['api_token' => 0])->assertUnauthorized();
    }

    public function test_notifications_cannot_be_read_or_marked_for_another_user(): void
    {
        $user = $this->user();
        DB::table('gp_usuarios')->insert([
            ['id' => 123, 'USERID' => 'self'], ['id' => 456, 'USERID' => 'other'],
        ]);
        $victimId = (string) \Illuminate\Support\Str::uuid();
        foreach ([123 => (string) \Illuminate\Support\Str::uuid(), 456 => $victimId] as $owner => $id) {
            DB::table('notifications')->insert(['id' => $id, 'type' => 'test',
                'notifiable_type' => GpUsuario::class, 'notifiable_id' => $owner,
                'data' => json_encode(['title' => 'Fixture', 'message' => 'search-marker-'.$owner]),
                'created_at' => now(), 'updated_at' => now()]);
        }
        Sanctum::actingAs($user, ['web']);
        $this->postJson('/api/notifications/all', ['userId' => 456, 'search' => 'search-marker'])
            ->assertOk()->assertSee('search-marker-123')->assertDontSee('search-marker-456');
        $this->postJson('/api/notifications/mark-as-read', ['userId' => 456, 'notifyId' => $victimId]);
        $this->assertNull(DB::table('notifications')->where('id', $victimId)->value('read_at'));
        $this->postJson('/api/notifications/mark-all-as-read', ['userId' => 456])->assertOk();
        $this->assertNull(DB::table('notifications')->where('id', $victimId)->value('read_at'));
    }

    public function test_login_is_limited_before_authentication_for_web_and_mobile(): void
    {
        foreach (['/api/login', '/api/movil/login'] as $endpoint) {
            $username = 'security-'.md5($endpoint);
            for ($i = 0; $i < 5; $i++) {
                // Missing password deliberately stops validation before any user lookup.
                $this->postJson($endpoint, ['username' => $username])->assertUnprocessable();
            }
            $this->postJson($endpoint, ['username' => $username])->assertStatus(429);
        }
    }

    public function test_email_validation_rejects_crlf_and_null_bytes(): void
    {
        foreach (["user@example.com\r\nBcc: other@example.com", "user\0@example.com", "user\n@example.com"] as $email) {
            $this->assertTrue(Validator::make(['email' => $email], ['email' => 'email'])->fails());
        }
        $this->assertFalse(Validator::make(['email' => 'valid@example.com'], ['email' => 'email'])->fails());
    }

    public function test_image_with_executable_client_extension_is_stored_as_an_image(): void
    {
        $image = UploadedFile::fake()->image('avatar.png');
        $file = new UploadedFile($image->getPathname(), 'avatar.php', null, null, true);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]+\.(jpg|png)$/', SafeUploadName::image($file));
    }

    public function test_binary_upload_cannot_masquerade_as_pdf(): void
    {
        $this->expectException(ValidationException::class);
        SafeUploadName::pdf(UploadedFile::fake()->createWithContent('document.pdf', '<?php echo 1;'));
    }

    public function test_pdf_with_executable_client_extension_receives_safe_pdf_extension(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n%%EOF\n");
        $name = SafeUploadName::pdf(new UploadedFile($pdf->getPathname(), 'document.php', null, null, true));
        $this->assertMatchesRegularExpression('/^[a-f0-9-]+\.pdf$/', $name);
    }

    public function test_internal_server_errors_do_not_expose_exception_messages(): void
    {
        config(['app.debug' => false]);
        Route::get('/security-test/error', function () {
            return (new class {
                use \App\Traits\JsonResponseTrait;
                public function response() { return $this->buildErrorResponse('SQL password=private-marker', 500); }
            })->response();
        });
        $this->getJson('/security-test/error')->assertStatus(500)->assertDontSee('private-marker');
    }

    public function test_pdf_renderer_keeps_embedded_images_and_restricts_local_files(): void
    {
        $pdf = app('dompdf.wrapper')->loadHTML('<html><body>Security smoke test<img src="data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aC1cAAAAASUVORK5CYII=')).'"></body></html>')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $roots = app('dompdf.wrapper')->getDomPDF()->getOptions()->getChroot();
        $this->assertNotContains(base_path(), $roots);
        $this->assertContains(public_path(), $roots);
    }

    public function test_upgraded_media_library_can_store_and_read_pdf_metadata(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::create('security_media_records', fn (Blueprint $table) => $table->id());
        require_once database_path('migrations/2026_03_16_160117_create_media_table.php');
        (new \CreateMediaTable)->up();
        $owner = SecurityMediaRecord::create([]);
        $temp = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n%%EOF\n");
        $file = new UploadedFile($temp->getPathname(), 'invoice.php', null, null, true);
        $media = $owner->addMedia($file)->usingFileName(SafeUploadName::pdf($file))
            ->toMediaCollection('documentos_pdf', 'public');
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertStringEndsWith('.pdf', $media->file_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }
}

class SecurityMediaRecord extends \Illuminate\Database\Eloquent\Model implements \Spatie\MediaLibrary\HasMedia
{
    use \Spatie\MediaLibrary\InteractsWithMedia;
    protected $guarded = [];
    public $timestamps = false;
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documentos_pdf')->acceptsMimeTypes(['application/pdf']);
    }
}
