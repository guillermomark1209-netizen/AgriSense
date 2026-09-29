<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderUnavailableException;
use App\Http\Requests\SendAssistantMessageRequest;
use App\Models\Crop;
use App\Models\SensorReading;
use App\Services\GeminiService;
use App\Services\RagService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        $crop = $request->filled('crop_id') ? Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('crop_id')) : null;

        return view('ai.assistant', compact('crop'));
    }

    public function chat(SendAssistantMessageRequest $request, RagService $rag, GeminiService $gemini): JsonResponse
    {
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $crop = $request->filled('crop_id') ? Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('crop_id')) : null;
        $reading = null;

        try {
            $reading = SensorReading::query()
                ->whereHas('device', fn ($query) => $query->where('user_id', $request->user()->id))
                ->when($crop, fn ($query) => $query->where('crop_id', $crop->id))
                ->with('crop:id,name')
                ->latest('reading_at')
                ->first();
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            $question = $request->string('message')->toString();
            $history = collect($request->validated('history', []))->take(-4)->values()->all();
            $questionType = $this->questionType($question, $history);
            $imageAnalysis = null;
            $image = null;
            $usesImage = $crop?->image_url && in_array($questionType, ['image_visual', 'image_agricultural'], true);
            if ($usesImage) {
                $image = $this->cropImage($crop);
                if ($questionType === 'image_agricultural') {
                    $imageAnalysis = $gemini->analyzeCropImageData($image['contents'], $image['mime_type'], $crop->name);
                }
            }

            $chunks = collect();
            $usesRag = in_array($questionType, ['agricultural_knowledge', 'image_agricultural'], true);
            if ($usesRag) {
                $priorities = [$crop?->name, $question, ...collect($history)->pluck('content')->all(), ...($imageAnalysis['visible_symptoms'] ?? []), ...($imageAnalysis['possible_problems'] ?? [])];
                $chunks = $rag->retrieve(implode(' ', array_filter($priorities)), $crop?->name, null, $priorities)
                    ->filter(fn ($source) => filled($source->content) && $this->isVerifiedUrl($source->source_url))
                    ->values();
            }

            Log::info('AI assistant routing', [
                'question_type' => $questionType,
                'image_sent_to_gemini' => $image !== null,
                'rag_used' => $usesRag,
                'rag_chunk_count' => $chunks->count(),
                'source_ids' => $chunks->pluck('id')->all(),
            ]);

            if ($chunks->isEmpty() && $this->requiresVerifiedRag($question, $questionType)) {
                return response()->json(['message' => 'I could not find enough verified information in the knowledge base.', 'sources' => []]);
            }

            $context = [
                'crop' => $crop?->only(['name', 'variety', 'growth_stage']),
                'reading' => $reading?->only([...array_keys(config('agrisense.sensors')), 'reading_at']),
                'stale' => ! $reading || $reading->reading_at->lt(now()->subMinutes(config('agrisense.stale_minutes'))),
            ];
            $evidence = $chunks->map(fn ($source) => [
                'chunk_id' => $source->id,
                'text' => $source->content,
                'title' => $source->title,
                'source_url' => $source->source_url,
            ])->all();
            if ($imageAnalysis) {
                $context['image_observation'] = [
                    'crop' => $imageAnalysis['crop_name'],
                    'visible_symptoms' => $imageAnalysis['visible_symptoms'],
                    'possible_problems' => $imageAnalysis['possible_problems'],
                ];
            }
            $answer = $gemini->generateResponse($this->questionWithImageObservations($question, $imageAnalysis), $evidence, $context, $image, $questionType, $history);
        } catch (AiProviderUnavailableException $exception) {
            report($exception);

            return response()->json(['message' => 'AI Assistant is currently unavailable. Please try again later.'], 503);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The AI response could not be processed. Please try again.'], 422);
        }

        return response()->json([
            'message' => $answer['answer'],
            'sources' => $chunks
                ->filter(fn ($source) => filled($source->title))
                ->unique(fn ($source) => trim($source->title).'|'.($source->source_url ?? ''))
                ->map(fn ($source) => [
                    'title' => trim($source->title),
                    'url' => $source->source_url,
                ])->values(),
        ]);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function questionType(string $question, array $history): string
    {
        if (preg_match('/^\s*(hi|hello|hey|thanks|thank you|how are you)[!.?\s]*$/i', $question) === 1) {
            return 'casual';
        }

        $hasVisualReference = preg_match('/\b(this|my|image|photo|leaves?|leaf|plant|crop)\b/i', $question) === 1;
        $hasVisibleSymptom = preg_match('/\b(holes?|spots?|damage|damaged|yellowing|wilt|curl|discolor)\b/i', $question) === 1;
        if (preg_match('/\b(pest|disease|why|cause|causing|holes?|spots?|damage|damaged|yellowing|wilt|curl|discolor|healthy|health)\b/i', $question) === 1
            && ($hasVisualReference || $hasVisibleSymptom)) {
            return 'image_agricultural';
        }

        if (preg_match('/\b(this crop|this plant|what is this|is this|what do you see|what can you see|identify|healthy|health|leaves?|color)\b/i', $question) === 1) {
            return 'image_visual';
        }

        $historyMentionsAgriculture = collect($history)->pluck('content')->contains(fn (string $content): bool => preg_match('/\b(pest|disease|crop|lettuce|plant|holes?|treat|prevent)\b/i', $content) === 1);
        if (preg_match('/\b(pest|disease|grow|water|fertiliz|harvest|soil|irrigat|planting|prevent|treat|pesticide|chemical|lettuce|cabbage|crop)\b/i', $question) === 1
            || ($historyMentionsAgriculture && preg_match('/\b(it|that|them|this)\b/i', $question) === 1)) {
            return 'agricultural_knowledge';
        }

        return 'general_knowledge';
    }

    private function requiresVerifiedRag(string $question, string $questionType): bool
    {
        return $questionType === 'image_agricultural'
            || preg_match('/\b(treat|treatment|pesticide|chemical|dose|spray|disease|diagnos)\b/i', $question) === 1;
    }

    /**
     * @return array{contents: string, mime_type: string}
     */
    private function cropImage(Crop $crop): array
    {
        abort_unless(str_starts_with((string) $crop->image_url, 'crop-images/'), 404);
        if (str_starts_with($crop->image_url, 'crop-images/local/')) {
            abort_unless(Storage::disk('local')->exists($crop->image_url), 404);
            $contents = Storage::disk('local')->get($crop->image_url);
            $mimeType = Storage::disk('local')->mimeType($crop->image_url) ?: 'application/octet-stream';
        } else {
            $contents = app(SupabaseStorageService::class)->download($crop->image_url);
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        }

        return ['contents' => $contents, 'mime_type' => $mimeType];
    }

    /**
     * @param  array{crop_name: string, visible_symptoms: array<int, string>, possible_problems: array<int, string>}|null  $imageAnalysis
     */
    private function questionWithImageObservations(string $question, ?array $imageAnalysis): string
    {
        if (! $imageAnalysis) {
            return $question;
        }

        return $question."\n\nImage observation (not a diagnosis): crop appears to be {$imageAnalysis['crop_name']}; visible symptoms: ".implode(', ', $imageAnalysis['visible_symptoms']).'; possible visible problems: '.implode(', ', $imageAnalysis['possible_problems']).'. Compare only these observations with the supplied evidence. Use uncertainty for ambiguous symptoms.';
    }

    private function isVerifiedUrl(mixed $url): bool
    {
        return is_string($url) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
