<?php

namespace Tests\Feature;

use App\Models\AIConversation;
use App\Models\Crop;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_assistant_page_and_chat_require_authentication(): void
    {
        $this->get(route('ai.assistant'))->assertRedirect(route('login'));
        $this->postJson(route('ai.assistant.chat'), ['message' => 'Do I need watering?'])->assertUnauthorized();
    }

    public function test_image_diagnosis_requires_verified_retrieved_evidence(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.assistant.chat'), ['message' => 'What disease could this be?'])
            ->assertOk()
            ->assertJsonPath('message', 'I could not find enough verified information in the knowledge base.')
            ->assertJsonPath('sources', []);

        Http::assertNothingSent();
    }

    public function test_general_questions_are_answered_by_gemini_without_rag_or_sources(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"Photosynthesis is the process plants use to turn light into chemical energy."}']]]]]])]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.assistant.chat'), ['message' => 'What is photosynthesis?'])
            ->assertOk()
            ->assertJsonPath('message', 'Photosynthesis is the process plants use to turn light into chemical energy.')
            ->assertJsonPath('sources', []);

        Http::assertSentCount(1);
    }

    public function test_follow_up_context_is_sent_to_gemini(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"You can help prevent it by checking plants regularly."}']]]]]])]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.assistant.chat'), [
                'message' => 'How can I prevent it?',
                'history' => [
                    ['role' => 'user', 'content' => 'What pest could cause these holes?'],
                    ['role' => 'assistant', 'content' => 'Possible causes include leaf-eating insects.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('sources', []);

        Http::assertSent(function ($request): bool {
            return str_contains($request->data()['contents'][0]['parts'][0]['text'], 'What pest could cause these holes?');
        });
    }

    public function test_visual_crop_questions_send_the_stored_image_to_gemini_without_rag(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Storage::fake('local');
        Storage::disk('local')->put('crop-images/local/1/lettuce.jpg', 'stored-lettuce-image');
        $user = User::factory()->create();
        $crop = Crop::query()->create([
            'user_id' => $user->id,
            'name' => 'Lettuce',
            'scientific_name' => 'Lactuca sativa',
            'variety' => 'Green',
            'planting_date' => now()->toDateString(),
            'growth_stage' => 'Vegetative',
            'location' => 'Plot A',
            'image_url' => 'crop-images/local/1/lettuce.jpg',
        ]);
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"Yes, the image appears to show lettuce."}']]]]]])]);

        $this->actingAs($user)
            ->postJson(route('ai.assistant.chat'), ['message' => 'Is this lettuce?', 'crop_id' => $crop->id])
            ->assertOk()
            ->assertJsonPath('message', 'Yes, the image appears to show lettuce.')
            ->assertJsonPath('sources', []);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $parts = $payload['contents'][0]['parts'];

            return $payload['systemInstruction']['parts'][0]['text'] !== ''
                && $parts[1]['inlineData']['data'] === base64_encode('stored-lettuce-image')
                && str_contains($parts[0]['text'], '"question_type":"image_visual"')
                && str_contains($parts[0]['text'], '"rag_evidence":[]');
        });
    }

    public function test_assistant_chat_history_is_saved_and_reloaded_for_the_authenticated_user(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"Plants use sunlight for photosynthesis."}']]]]]])]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.assistant.chat'), ['message' => 'How do plants use sunlight?'])
            ->assertOk()
            ->assertJsonPath('message', 'Plants use sunlight for photosynthesis.');

        $conversation = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'assistant_chat')
            ->firstOrFail();

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'How do plants use sunlight?',
        ]);
        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Plants use sunlight for photosynthesis.',
        ]);

        $this->actingAs($user)
            ->get(route('ai.assistant'))
            ->assertOk()
            ->assertSee('How do plants use sunlight?')
            ->assertSee('Plants use sunlight for photosynthesis.');
    }

    public function test_clearing_assistant_chat_only_removes_the_current_users_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AIConversation::query()->create([
            'user_id' => $user->id,
            'title' => 'AI Assistant',
            'status' => 'assistant_chat',
        ]);
        $otherConversation = AIConversation::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'AI Assistant',
            'status' => 'assistant_chat',
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'My message']);
        $otherConversation->messages()->create(['role' => 'user', 'content' => 'Other user message']);

        $this->actingAs($user)
            ->deleteJson(route('ai.assistant.clear', $conversation))
            ->assertNoContent();

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['conversation_id' => $conversation->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $otherConversation->id]);
        $this->actingAs($user)->get(route('ai.assistant'))->assertDontSee('Other user message');
    }

    public function test_uploaded_assistant_images_are_stored_and_sent_to_gemini(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Config::set('agrisense.supabase_url', 'https://example.supabase.co');
        Config::set('agrisense.supabase_key', 'test-key');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"The image shows a leafy crop."}']]]]]])]);
        $storedImage = UploadedFile::fake()->image('stored-crop.png');
        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('upload')
            ->once()
            ->andReturn('plant-analysis/1/crop.jpg');
        $storage->shouldReceive('download')
            ->once()
            ->with('plant-analysis/1/crop.jpg')
            ->andReturn($storedImage->getContent());
        $this->app->instance(SupabaseStorageService::class, $storage);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('ai.assistant.chat'), [
                'message' => 'What do you see in this image?',
                'image' => UploadedFile::fake()->image('crop.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'The image shows a leafy crop.');

        $this->assertDatabaseHas('ai_messages', [
            'role' => 'user',
            'content' => 'What do you see in this image?',
            'image_url' => 'plant-analysis/1/crop.jpg',
        ]);
        Http::assertSent(fn ($request): bool => isset($request->data()['contents'][0]['parts'][1]['inlineData']));

        $this->actingAs($user)
            ->postJson(route('ai.assistant.chat'), ['message' => 'What problem can you see?'])
            ->assertOk();

        Http::assertSent(function ($request) use ($storedImage): bool {
            $parts = $request->data()['contents'][0]['parts'];

            return ($parts[1]['inlineData']['data'] ?? null) === base64_encode($storedImage->getContent());
        });
    }

    public function test_uploaded_assistant_images_use_local_storage_when_supabase_storage_is_not_configured(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Config::set('agrisense.supabase_url', null);
        Config::set('agrisense.supabase_key', null);
        Storage::fake('local');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"This looks like a crop image."}']]]]]])]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('ai.assistant.chat'), [
                'message' => 'What is this image?',
                'image' => UploadedFile::fake()->image('crop.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $message = AIConversation::query()->where('user_id', $user->id)->firstOrFail()->messages()->where('role', 'user')->firstOrFail();
        Storage::disk('local')->assertExists($message->image_url);
    }

    public function test_users_can_create_and_switch_between_their_own_assistant_conversations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstConversation = AIConversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Lettuce Disease',
            'status' => 'assistant_chat',
        ]);
        $firstConversation->messages()->create(['role' => 'user', 'content' => 'What is this lettuce disease?']);
        $otherConversation = AIConversation::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Private Chat',
            'status' => 'assistant_chat',
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.assistant.create'))
            ->assertCreated()
            ->assertJsonStructure(['id', 'url']);

        $createdConversation = AIConversation::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('New Chat', $createdConversation->title);
        $this->actingAs($user)
            ->get(route('ai.assistant', ['conversation' => $firstConversation->id]))
            ->assertOk()
            ->assertSee('What is this lettuce disease?')
            ->assertDontSee('Private Chat');
        $this->actingAs($user)->get(route('ai.assistant', ['conversation' => $otherConversation->id]))->assertNotFound();
    }

    public function test_users_can_delete_only_their_selected_assistant_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AIConversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Delete me',
            'status' => 'assistant_chat',
        ]);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Answer']);
        $message->sources()->create(['citation_text' => 'Citation', 'citation_url' => 'https://example.com']);
        $otherConversation = AIConversation::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Keep me',
            'status' => 'assistant_chat',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ai.assistant.destroy', $conversation))
            ->assertNoContent();

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('ai_message_sources', ['message_id' => $message->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $otherConversation->id]);
        $this->actingAs($user)->deleteJson(route('ai.assistant.destroy', $otherConversation))->assertNotFound();
    }
}
