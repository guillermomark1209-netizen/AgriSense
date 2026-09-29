<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
}
