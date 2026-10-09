<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\AIMessage;
use App\Models\Crop;
use App\Models\Device;
use App\Models\Source;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\DocumentService;
use App\Services\GeminiService;
use App\Services\RagService;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgriSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function crop(User $user, array $extra = []): Crop
    {
        return $user->crops()->create([...['name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma', 'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A', 'status' => 'unknown'], ...$extra]);
    }

    private function device(User $user, Crop $crop): array
    {
        $device = $user->devices()->create(['crop_id' => $crop->id, 'device_id' => 'ESP-'.Str::random(8), 'name' => 'Field monitor', 'status' => 'offline', 'is_active' => true]);

        return [$device, app(DeviceService::class)->rotateToken($device)];
    }

    private function payload(Device $device, array $extra = []): array
    {
        return [...['device_id' => $device->device_id, 'reading_id' => (string) Str::uuid(), 'reading_at' => now()->subSecond()->toIso8601String(), 'temperature' => 28, 'humidity' => 65, 'soil_moisture' => 40, 'soil_ph' => 6.5, 'light_intensity' => 720], ...$extra];
    }

    private function source(array $extra = []): Source
    {
        return Source::create([...['title' => 'Test fixture reference', 'organization' => 'Test institution', 'crop' => 'Tomato', 'topic' => 'Irrigation', 'type' => 'academic', 'url' => 'https://example.org/reference', 'verification_status' => 'verified', 'is_active' => true], ...$extra]);
    }

    public function test_registration_creates_farmer_profile_and_cannot_self_assign_admin(): void
    {
        $this->post('/register', ['name' => 'Farmer', 'email' => 'farmer@example.test', 'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password', 'role' => 'admin'])->assertRedirect('/dashboard');
        $user = User::first();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertNotNull($user->profile);
    }

    public function test_farmers_cannot_access_another_farmers_records_or_admin(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $crop = $this->crop($two);
        [$device] = $this->device($two, $crop);
        $this->actingAs($one)->get('/crops/'.$crop->id)->assertForbidden();
        $this->get('/devices/'.$device->id)->assertForbidden();
        $this->get('/admin/sources')->assertForbidden();
        $this->getJson('/monitoring/data?crop_id='.$crop->id)->assertUnprocessable();
        $this->post('/devices', ['name' => 'Bad assignment', 'device_id' => 'BAD', 'crop_id' => $crop->id])->assertForbidden();
        $this->post('/ai', ['question' => 'Explain', 'crop_id' => $crop->id])->assertSessionHasErrors('crop_id');
    }

    public function test_device_readings_require_valid_token_and_values(): void
    {
        $user = User::factory()->create();
        [$device,$token] = $this->device($user, $this->crop($user));
        $payload = $this->payload($device);
        $this->postJson('/api/device/readings', $payload)->assertUnauthorized();
        $this->withToken('wrong')->postJson('/api/device/readings', $payload)->assertUnauthorized();
        $this->withToken($token)->postJson('/api/device/readings', [...$payload, 'humidity' => 101])->assertUnprocessable();
        $this->assertDatabaseCount('sensor_readings', 0);
        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertCreated()->assertJsonPath('duplicate', false);
        $this->assertDatabaseCount('sensor_readings', 1);
        $this->assertTrue($device->fresh()->online);
    }

    public function test_extended_sensor_fields_are_validated_and_persisted_without_unit_conversion(): void
    {
        $user = User::factory()->create();
        [$device, $token] = $this->device($user, $this->crop($user));
        $payload = $this->payload($device, [
            'soil_temperature' => 21.3,
            'air_temperature' => 27.2,
            'air_humidity' => 70,
            'light_percent' => 45,
            'soil_raw' => 2200,
            'ldr_raw' => 900,
            'ph_raw' => 6.8,
            'pump' => false,
        ]);

        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertCreated();

        $this->assertDatabaseHas('sensor_readings', [
            'reading_id' => $payload['reading_id'],
            'soil_temperature' => 21.3,
            'air_temperature' => 27.2,
            'air_humidity' => 70,
            'light_percent' => 45,
            'soil_raw' => 2200,
            'ldr_raw' => 900,
            'ph_raw' => 6.8,
            'pump' => false,
        ]);
    }

    public function test_offset_reading_timestamps_are_normalized_to_utc_before_storage(): void
    {
        $user = User::factory()->create();
        [$device, $token] = $this->device($user, $this->crop($user));
        $observedAt = now()->subMinute()->setTimezone('Asia/Manila');
        $payload = $this->payload($device, [
            'reading_at' => $observedAt->toIso8601String(),
        ]);

        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertCreated();

        $storedReading = $device->readings()->where('reading_id', $payload['reading_id'])->firstOrFail();

        $this->assertSame(
            $observedAt->copy()->utc()->toIso8601String(),
            $storedReading->reading_at->toIso8601String()
        );
        $this->assertSame(
            $observedAt->utc()->format('Y-m-d H:i:s'),
            $storedReading->reading_at->format('Y-m-d H:i:s')
        );
    }

    public function test_replayed_readings_are_idempotent_and_preserve_observation_time(): void
    {
        $user = User::factory()->create();
        [$device,$token] = $this->device($user, $this->crop($user));
        $payload = $this->payload($device, ['reading_at' => now()->subDays(2)->toIso8601String()]);
        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('sensor_readings', 1);
        $this->assertTrue($device->readings()->first()->reading_at->equalTo($payload['reading_at']));
    }

    public function test_disabled_or_cross_owner_devices_cannot_submit(): void
    {
        $user = User::factory()->create();
        $crop = $this->crop($user);
        [$device,$token] = $this->device($user, $crop);
        $device->update(['is_active' => false]);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device))->assertStatus(409);
        $other = User::factory()->create();
        $device->update(['is_active' => true, 'crop_id' => $this->crop($other)->id]);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device))->assertStatus(409);
    }

    public function test_a_replay_uuid_cannot_replace_an_existing_observation(): void
    {
        $user = User::factory()->create();
        [$device, $token] = $this->device($user, $this->crop($user));
        $payload = $this->payload($device);
        $this->withToken($token)->postJson('/api/device/readings', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/device/readings', [...$payload, 'temperature' => 77])->assertStatus(409);
        $this->assertDatabaseHas('sensor_readings', ['reading_id' => $payload['reading_id'], 'temperature' => 28]);
    }

    public function test_document_processing_is_dispatched_to_the_queue(): void
    {
        Queue::fake();
        $user = User::factory()->admin()->create();
        $document = $this->source()->documents()->create(['title' => 'Test document', 'content' => 'Test evidence', 'status' => 'uploaded']);
        $this->actingAs($user)->post('/admin/documents/'.$document->id.'/process')->assertRedirect();
        $this->assertSame('queued', $document->fresh()->status);
        Queue::assertPushed(ProcessDocument::class, fn ($job) => $job->documentId === $document->id);
    }

    public function test_alerts_use_verified_context_and_resolve_without_duplicate_alerts(): void
    {
        $user = User::factory()->create();
        $crop = $this->crop($user);
        [$device,$token] = $this->device($user, $crop);
        $source = $this->source();
        $crop->thresholds()->create(['sensor_type' => 'soil_moisture', 'min_value' => 30, 'max_value' => 60, 'variety' => 'Roma', 'growth_stage' => 'Flowering', 'source_id' => $source->id, 'source_reference' => 'Test fixture only']);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device, ['soil_moisture' => 20]))->assertCreated();
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device, ['soil_moisture' => 22]))->assertCreated();
        $this->assertDatabaseCount('alerts', 1);
        $this->assertDatabaseHas('alerts', ['status' => 'active', 'threshold_value' => 30]);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device))->assertCreated();
        $this->assertDatabaseHas('alerts', ['status' => 'resolved']);
    }

    public function test_stale_readings_and_unverified_thresholds_do_not_create_live_alerts(): void
    {
        $user = User::factory()->create();
        $crop = $this->crop($user);
        [$device,$token] = $this->device($user, $crop);
        $source = $this->source(['verification_status' => 'pending']);
        $crop->thresholds()->create(['sensor_type' => 'soil_moisture', 'min_value' => 30, 'variety' => 'Roma', 'growth_stage' => 'Flowering', 'source_id' => $source->id]);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device, ['soil_moisture' => 10]))->assertCreated();
        $source->update(['verification_status' => 'verified']);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device, ['soil_moisture' => 10, 'reading_at' => now()->subDay()->toIso8601String()]))->assertCreated();
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_ai_without_evidence_returns_a_direct_response_without_provider_call(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/ai', ['question' => 'Why are my leaves yellow?'])->assertRedirect();
        $message = AIMessage::where('role', 'assistant')->firstOrFail();
        $this->assertStringContainsString('insufficient verified evidence', $message->content);
        $this->assertNull($message->confidence);
        Http::assertNothingSent();
    }

    public function test_gemini_rag_page_and_assistant_remain_available(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('ai.index'))->assertOk()->assertSee('Evidence is the starting point.');
        $this->get(route('ai.assistant'))->assertOk()->assertSee('Your field companion');
    }

    public function test_retrieval_excludes_rejected_expired_and_inactive_sources(): void
    {
        foreach ([['verification_status' => 'rejected'], ['is_active' => false], ['expires_at' => now()->subDay()->toDateString()]] as $state) {
            $document = $this->source($state)->documents()->create(['title' => 'Fixture', 'content' => 'Fixture evidence', 'status' => 'processed']);
            $document->chunks()->create(['chunk_index' => 0, 'content' => 'Fixture evidence', 'embedding' => array_fill(0, 768, 0.1), 'embedding_model' => config('services.gemini.embedding_model')]);
        }
        $this->assertCount(0, app(RagService::class)->retrieve('Question', 'Tomato'));
        Http::assertNothingSent();
    }

    public function test_document_embeddings_and_retrieval_are_connected(): void
    {
        config(['services.gemini.key' => 'test-only']);
        Http::fake(['*embedContent' => Http::response(['embedding' => ['values' => array_fill(0, 768, 0.1)]])]);
        $document = $this->source()->documents()->create(['title' => 'Test document', 'content' => str_repeat('Test verified evidence text. ', 25), 'status' => 'uploaded']);
        app(DocumentService::class)->process($document);
        $this->assertSame('processed', $document->fresh()->status);
        $this->assertNotEmpty($document->chunks);
        $chunks = app(RagService::class)->retrieve('Question', 'Tomato');
        $this->assertSame($document->id, $chunks->first()->document_id);
    }

    public function test_gemini_rejects_citations_outside_retrieved_evidence(): void
    {
        config(['services.gemini.key' => 'test-only']);
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['answer' => 'Test text', 'citations' => [999]])]]]]]])]);
        $this->expectException(\RuntimeException::class);
        app(GeminiService::class)->generateResponse('Question', [['chunk_id' => 1, 'text' => 'Test evidence']], []);
    }

    public function test_gemini_generates_a_valid_grounded_response(): void
    {
        config(['services.gemini.key' => 'test-only']);
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['answer' => 'Brassica rapa belongs to the Brassicaceae family.', 'citations' => [1]])]]]]]])]);

        $answer = app(GeminiService::class)->generateResponse('Question', [['chunk_id' => 1, 'text' => 'Verified evidence']], []);

        $this->assertSame('Brassica rapa belongs to the Brassicaceae family.', $answer['answer']);
        $this->assertSame([1], $answer['citations']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), ':generateContent')
            && ! array_key_exists('sensor_context', json_decode($request->data()['contents'][0]['parts'][0]['text'], true)));
    }

    public function test_ai_sends_verified_rag_evidence_and_source_url_to_gemini(): void
    {
        config(['services.gemini.key' => 'test-only']);
        $user = User::factory()->create();
        $document = $this->source()->documents()->create(['title' => 'Verified irrigation guide', 'content' => 'Verified irrigation evidence.', 'status' => 'processed']);
        $chunk = $document->chunks()->create(['chunk_index' => 0, 'content' => 'Verified irrigation evidence.', 'embedding' => array_fill(0, 768, 0.1), 'embedding_model' => config('services.gemini.embedding_model')]);
        Http::fake(function ($request) use ($chunk) {
            if (str_contains($request->url(), ':embedContent')) {
                return Http::response(['embedding' => ['values' => array_fill(0, 768, 0.1)]]);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['answer' => 'Use the verified evidence.', 'citations' => [$chunk->id]])]]]]]]);
        });

        $this->actingAs($user)->post('/ai', ['question' => 'How should I irrigate tomatoes?'])->assertRedirect();

        $this->assertDatabaseHas('ai_message_sources', ['chunk_id' => $chunk->id, 'source_id' => $document->source_id]);
        Http::assertSent(function ($request) use ($document): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $payload = json_decode($request->data()['contents'][0]['parts'][0]['text'], true);

            return $payload['evidence'][0]['text'] === 'Verified irrigation evidence.'
                && $payload['evidence'][0]['source_url'] === $document->source->url
                && ! array_key_exists('sensor_context', $payload);
        });
    }

    public function test_monitoring_ignores_other_users_and_keeps_null_values(): void
    {
        $user = User::factory()->create();
        $crop = $this->crop($user);
        [$device,$token] = $this->device($user, $crop);
        $this->withToken($token)->postJson('/api/device/readings', $this->payload($device, ['temperature' => null]))->assertCreated();
        $other = User::factory()->create();
        [$otherDevice] = $this->device($other, $this->crop($other));
        $otherDevice->readings()->create(['crop_id' => $otherDevice->crop_id, 'temperature' => 99, 'reading_at' => now()]);
        $response = $this->actingAs($user)->getJson('/monitoring/data');
        $response->assertOk()->assertJsonCount(1, 'points')->assertJsonPath('points.0.value', null);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_every_farmer_page_renders_with_empty_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['dashboard', 'crops', 'devices', 'monitoring', 'alerts', 'ai-assistant', 'history', 'profile', 'settings', 'help'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        $this->get('/settings')->assertSee('data-theme-toggle', false)->assertSee('Appearance');
    }

    public function test_detail_and_admin_pages_render(): void
    {
        $user = User::factory()->admin()->create();
        $crop = $this->crop($user);
        [$device] = $this->device($user, $crop);
        $document = $this->source()->documents()->create(['title' => 'Document fixture', 'content' => 'Text fixture', 'status' => 'uploaded']);
        $this->actingAs($user);
        foreach (['crops/'.$crop->id, 'crops/'.$crop->id.'/edit', 'devices/'.$device->id, 'devices/'.$device->id.'/edit', 'admin/dashboard', 'admin/sources', 'admin/documents', 'admin/documents/'.$document->id, 'admin/users', 'admin/crops', 'admin/devices', 'admin/sensor-data', 'admin/alerts', 'admin/conversations', 'admin/audit-logs', 'admin/settings'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
    }

    public function test_profile_and_crop_updates_validate_and_persist(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $this->post('/crops', ['name' => 'Incomplete'])->assertSessionHasErrors('variety');
        $crop = $this->crop($user);
        $this->put('/crops/'.$crop->id, [...$crop->only('name', 'scientific_name', 'variety', 'growth_stage', 'location', 'planting_date', 'status'), 'growth_stage' => 'Fruiting'])->assertRedirect();
        $this->assertDatabaseHas('crop_events', ['crop_id' => $crop->id, 'event' => 'Crop details updated: Fruiting']);
        $this->post('/profile', ['name' => 'Changed', 'email' => $user->email])->assertRedirect();
        $this->assertSame('Changed', $user->fresh()->name);
    }

    public function test_password_reset_flow_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'new-long-test-password', 'password_confirmation' => 'new-long-test-password'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-long-test-password', $user->fresh()->password));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_storage_upload_uses_backend_credentials_and_private_object_path(): void
    {
        config(['agrisense.supabase_url' => 'https://storage.example.test', 'agrisense.supabase_key' => 'test-server-key']);
        Http::fake(['*' => Http::response(['Key' => 'private'], 200)]);
        $file = UploadedFile::fake()->create('document.txt', 1, 'text/plain');
        $path = app(SupabaseStorageService::class)->upload($file, 'knowledge-documents', 7);
        $this->assertStringStartsWith('knowledge-documents/7/', $path);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-server-key') && str_contains($request->url(), '/storage/v1/object/knowledge-documents/7/'));
    }

    public function test_crop_photo_can_be_uploaded_and_viewed_without_supabase(): void
    {
        config(['agrisense.supabase_url' => null, 'agrisense.supabase_key' => null]);
        Storage::fake('local');
        $user = User::factory()->admin()->create();
        $photo = UploadedFile::fake()->image('tomato.png');

        $response = $this->actingAs($user)->post(route('crops.store'), [
            'name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma',
            'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A',
            'status' => 'unknown', 'image' => $photo,
        ]);

        $response->assertSessionHasNoErrors();
        $crop = $user->crops()->firstOrFail();
        $response->assertRedirect(route('crops.show', $crop));
        $this->assertStringStartsWith('crop-images/local/'.$user->id.'/', $crop->image_url);
        Storage::disk('local')->assertExists($crop->image_url);
        $this->get(route('crops.image', $crop))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertStreamedContent($photo->getContent());
        foreach (['crops.index', 'crops.show', 'crops.edit'] as $route) {
            $this->get(route($route, $route === 'crops.index' ? [] : $crop))
                ->assertOk()->assertSee(route('crops.image', $crop));
        }
        Http::assertNothingSent();
    }

    public function test_crop_photo_can_be_replaced_and_is_preserved_when_no_photo_is_selected(): void
    {
        config(['agrisense.supabase_url' => null, 'agrisense.supabase_key' => null]);
        Storage::fake('local');
        $user = User::factory()->admin()->create();
        $crop = $this->crop($user, ['image_url' => 'crop-images/1/old.png']);
        $data = $crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status');
        $photo = UploadedFile::fake()->image('replacement.png');

        $this->actingAs($user)->post(route('crops.update', $crop), [...$data, '_method' => 'PUT', 'image' => $photo])
            ->assertSessionHasNoErrors()->assertRedirect(route('crops.show', $crop));
        $path = $crop->fresh()->image_url;
        Storage::disk('local')->assertExists($path);
        $this->get(route('crops.image', $crop))->assertOk()->assertStreamedContent($photo->getContent());
        $this->put(route('crops.update', $crop), $data)->assertSessionHasNoErrors();
        $this->assertSame($path, $crop->fresh()->image_url);
        Http::assertNothingSent();
    }

    public function test_crop_photos_require_authorization_and_missing_files_return_not_found(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $path = 'crop-images/local/'.$owner->id.'/tomato.png';
        Storage::disk('local')->put($path, 'private image');
        $crop = $this->crop($owner, ['image_url' => $path]);

        $this->get(route('crops.image', $crop))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('crops.image', $crop))->assertForbidden();
        Storage::disk('local')->delete($path);
        $this->actingAs($owner)->get(route('crops.image', $crop))->assertNotFound();
        $crop->update(['image_url' => null]);
        $this->get(route('crops.image', $crop))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_crop_photo_validation_rejects_invalid_and_oversized_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->admin()->create();
        $crop = $this->crop($user);
        $data = $crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status');
        $this->actingAs($user);

        foreach ([UploadedFile::fake()->create('photo.txt', 1, 'text/plain'), UploadedFile::fake()->image('large.png')->size(5121), new UploadedFile('', 'too-large.png', 'image/png', UPLOAD_ERR_INI_SIZE, true)] as $photo) {
            $this->put(route('crops.update', $crop), [...$data, 'image' => $photo])->assertSessionHasErrors('image');
        }
        $this->assertNull($crop->fresh()->image_url);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Http::assertNothingSent();
    }

    public function test_crop_photos_still_use_supabase_when_configured(): void
    {
        config(['agrisense.supabase_url' => 'https://storage.example.test', 'agrisense.supabase_key' => 'test-server-key']);
        Http::fake([
            '*/object/sign/*' => Http::response(['signedURL' => '/object/sign/crop-images/1/photo.png?token=test']),
            '*' => Http::response(['Key' => 'private']),
        ]);
        $user = User::factory()->admin()->create();
        $crop = $this->crop($user);
        $data = $crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status');

        $this->actingAs($user)->put(route('crops.update', $crop), [...$data, 'image' => UploadedFile::fake()->image('tomato.png')])
            ->assertSessionHasNoErrors();
        $this->assertStringStartsWith('crop-images/'.$user->id.'/', $crop->fresh()->image_url);
        $this->get(route('crops.image', $crop))
            ->assertRedirect('https://storage.example.test/storage/v1/object/sign/crop-images/1/photo.png?token=test');
    }

    public function test_crop_photo_server_and_partial_upload_errors_are_not_reported_as_size_errors(): void
    {
        $user = User::factory()->admin()->create();
        $crop = $this->crop($user);
        $data = $crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status');
        $this->actingAs($user);

        foreach ([
            UPLOAD_ERR_NO_TMP_DIR => 'The server could not receive your photo. Please try again later.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not receive your photo. Please try again later.',
            UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Please select the image and try again.',
        ] as $error => $message) {
            $photo = new UploadedFile('', 'tomato.png', 'image/png', $error, true);
            $this->put(route('crops.update', $crop), [...$data, 'image' => $photo])
                ->assertSessionHasErrors(['image' => $message]);
        }
        $this->assertNull($crop->fresh()->image_url);
        Http::assertNothingSent();
    }

    public function test_users_can_upload_jpeg_png_and_webp_crop_photos(): void
    {
        config(['agrisense.supabase_url' => null, 'agrisense.supabase_key' => null]);
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mimeType) {
            $photo = UploadedFile::fake()->image('tomato.'.$extension);
            $this->post(route('crops.store'), [
                'name' => 'Tomato '.$extension, 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma',
                'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A',
                'status' => 'unknown', 'image' => $photo,
            ])->assertSessionHasNoErrors();
            $crop = $user->crops()->where('name', 'Tomato '.$extension)->firstOrFail();
            Storage::disk('local')->assertExists($crop->image_url);
            $this->get(route('crops.image', $crop))->assertOk()->assertHeader('Content-Type', $mimeType)
                ->assertStreamedContent($photo->getContent());
        }
        Http::assertNothingSent();
    }
}
