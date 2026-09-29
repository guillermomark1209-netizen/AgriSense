<?php

namespace Tests\Feature;

use App\Services\GeminiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_it_returns_an_answer_that_omits_citations(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fakeSequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => '{"answer":"Keep soil consistently moist.","citations":[]}']]]]]]);

        $answer = app(GeminiService::class)->generateResponse('How should I water it?', [[
            'chunk_id' => 42,
            'text' => 'Keep soil consistently moist.',
            'title' => 'Watering guide',
            'source_url' => null,
        ]], []);

        $this->assertSame(['answer' => 'Keep soil consistently moist.'], $answer);
        Http::assertSentCount(1);
    }

    public function test_it_extracts_visible_symptoms_before_rag_retrieval(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"crop_name":"Lettuce","visible_symptoms":["holes in leaves","chewed edges"],"possible_problems":["possible chewing damage"]}']]]]]])]);

        $analysis = app(GeminiService::class)->analyzeCropImage(UploadedFile::fake()->create('lettuce.jpg', 100, 'image/jpeg'));

        $this->assertSame('Lettuce', $analysis['crop_name']);
        $this->assertSame(['holes in leaves', 'chewed edges'], $analysis['visible_symptoms']);
        $this->assertSame('holes in leaves, chewed edges', $analysis['visible_evidence']);
    }

    public function test_it_sends_stored_crop_image_data_and_crop_context_to_gemini(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Config::set('services.gemini.model', 'gemini-test');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"crop_name":"Lettuce","visible_symptoms":["small holes"],"possible_problems":["possible chewing damage"]}']]]]]])]);

        app(GeminiService::class)->analyzeCropImageData('stored-image-bytes', 'image/jpeg', 'Lettuce');

        Http::assertSent(function ($request): bool {
            $parts = $request->data()['contents'][0]['parts'];

            return $parts[0]['text'] === 'Describe the visible crop symptoms. The crop record is named Lettuce. Treat that as context, but still identify and analyze only what is visible.'
                && $parts[1]['inlineData']['mimeType'] === 'image/jpeg'
                && $parts[1]['inlineData']['data'] === base64_encode('stored-image-bytes');
        });
    }
}
