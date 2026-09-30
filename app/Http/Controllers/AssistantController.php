<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderUnavailableException;
use App\Http\Requests\SendAssistantMessageRequest;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\Crop;
use App\Models\SensorReading;
use App\Services\GeminiService;
use App\Services\RagService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantController extends Controller
{
    private const ConversationStatus = 'assistant_chat';

    public function index(Request $request): View
    {
        $crop = $request->filled('crop_id') ? Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('crop_id')) : null;
        $conversation = $request->filled('conversation')
            ? $request->user()->conversations()->where('status', self::ConversationStatus)->findOrFail($request->integer('conversation'))
            : $request->user()->conversations()->where('status', self::ConversationStatus)->latest('updated_at')->first();
        if (! $conversation) {
            $conversation = $request->user()->conversations()->create([
                'title' => 'New Chat',
                'status' => self::ConversationStatus,
            ]);
        }
        $recents = $request->user()->conversations()
            ->where('status', self::ConversationStatus)
            ->latest('updated_at')
            ->get(['id', 'title', 'updated_at']);
        $messages = $conversation?->messages()
            ->with('sources')
            ->get()
            ->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'image_url' => $message->image_url ? route('ai.assistant.image', $message) : null,
                'created_at' => $message->created_at->toIso8601String(),
                'sources' => $message->sources
                    ->filter(fn ($source) => filled($source->citation_text))
                    ->map(fn ($source) => [
                        'title' => $source->citation_text,
                        'url' => $source->citation_url,
                    ])->values(),
            ])->values();

        return view('ai.assistant', compact('crop', 'conversation', 'messages', 'recents'));
    }

    public function chat(SendAssistantMessageRequest $request, RagService $rag, GeminiService $gemini): JsonResponse
    {
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $crop = $request->filled('crop_id') ? Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('crop_id')) : null;
        $uploadedImage = $request->file('image');
        $question = $request->string('message')->trim()->toString();
        if (blank($question)) {
            $question = 'Please identify and analyze this crop image.';
        }
        $imagePath = null;
        if ($uploadedImage) {
            try {
                $imagePath = $this->storeAssistantImage($uploadedImage, $request->user()->id);
            } catch (\Throwable $exception) {
                Log::error('AI assistant image could not be stored', [
                    'user_id' => $request->user()->id,
                    'exception' => $exception,
                ]);

                return response()->json(['message' => 'The image could not be uploaded. Please try again.'], 422);
            }
        }
        $conversation = $this->assistantConversation($request, true);
        $isFirstUserMessage = ! $conversation->messages()->where('role', 'user')->exists();
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
            'image_url' => $imagePath,
        ]);
        if ($isFirstUserMessage) {
            $conversation->update(['title' => $this->conversationTitle($question, $uploadedImage !== null)]);
        }
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
            $history = collect($request->validated('history', []))->take(-4)->values()->all();
            $questionType = $this->questionType($question, $history);
            $imageAnalysis = null;
            $image = null;
            if ($uploadedImage) {
                $questionType = $questionType === 'agricultural_knowledge' ? 'image_agricultural' : 'image_visual';
                $image = $uploadedImage;
            }
            $conversationImage = ! $uploadedImage ? $this->conversationImage($conversation) : null;
            $usesConversationImage = $conversationImage && ($this->referencesConversationImage($question)
                || in_array($questionType, ['image_visual', 'image_agricultural'], true));
            if ($usesConversationImage) {
                if ($questionType === 'agricultural_knowledge' || $this->isImageManagementQuestion($question)) {
                    $questionType = 'image_agricultural';
                }
                $image = $conversationImage;
            }
            $usesCropImage = ! $uploadedImage && ! $usesConversationImage && $crop?->image_url && in_array($questionType, ['image_visual', 'image_agricultural'], true);
            if ($usesCropImage) {
                $image = $this->cropImage($crop);
            }
            if ($image && $questionType === 'image_agricultural') {
                $imageAnalysis = $image instanceof UploadedFile
                    ? $gemini->analyzeCropImage($image)
                    : $gemini->analyzeCropImageData($image['contents'], $image['mime_type'], $crop?->name);
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
                $answer = ['answer' => 'I could not find enough verified information in the knowledge base.'];
            } else {
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
            }
        } catch (AiProviderUnavailableException $exception) {
            report($exception);

            return response()->json(['message' => 'AI Assistant is currently unavailable. Please try again later.'], 503);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The AI response could not be processed. Please try again.'], 422);
        }

        $sources = $chunks
            ->filter(fn ($source) => filled($source->title))
            ->unique(fn ($source) => trim($source->title).'|'.($source->source_url ?? ''))
            ->map(fn ($source) => [
                'title' => trim($source->title),
                'url' => $source->source_url,
            ])->values();
        try {
            DB::transaction(function () use ($conversation, $answer, $sources): void {
                $message = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $answer['answer'],
                ]);
                foreach ($sources as $source) {
                    $message->sources()->create([
                        'citation_text' => $source['title'],
                        'citation_url' => $source['url'],
                    ]);
                }
                $conversation->touch();
            });
        } catch (\Throwable $exception) {
            Log::error('AI assistant response could not be saved', [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return response()->json(['message' => 'The AI response could not be saved. Please try again.'], 500);
        }

        return response()->json([
            'message' => $answer['answer'],
            'sources' => $sources,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $conversation = $request->user()->conversations()->create([
            'title' => 'New Chat',
            'status' => self::ConversationStatus,
        ]);

        return response()->json([
            'id' => $conversation->id,
            'url' => route('ai.assistant', ['conversation' => $conversation->id]),
        ], 201);
    }

    public function clear(Request $request, AIConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id && $conversation->status === self::ConversationStatus, 404);
        $conversation->messages()->delete();
        $conversation->touch();

        return response()->json([], 204);
    }

    public function destroy(Request $request, AIConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id && $conversation->status === self::ConversationStatus, 404);

        $imagePaths = $conversation->messages()
            ->whereNotNull('image_url')
            ->pluck('image_url')
            ->filter(fn (string $path): bool => str_starts_with($path, 'plant-analysis/'))
            ->unique()
            ->values();
        $sharedImagePaths = AIMessage::query()
            ->whereIn('image_url', $imagePaths)
            ->where('conversation_id', '!=', $conversation->id)
            ->pluck('image_url');
        $exclusiveImagePaths = $imagePaths->diff($sharedImagePaths);

        DB::transaction(fn () => $conversation->delete());

        foreach ($exclusiveImagePaths as $imagePath) {
            try {
                $this->deleteAssistantImage($imagePath);
            } catch (\Throwable $exception) {
                Log::warning('AI assistant image could not be deleted', [
                    'conversation_id' => $conversation->id,
                    'image_path' => $imagePath,
                    'exception' => $exception,
                ]);
            }
        }

        return response()->json([], 204);
    }

    public function image(Request $request, AIMessage $message): RedirectResponse|StreamedResponse
    {
        abort_unless($message->conversation->user_id === $request->user()->id
            && $message->conversation->status === self::ConversationStatus
            && $message->image_url
            && str_starts_with($message->image_url, 'plant-analysis/'), 404);

        if (str_starts_with($message->image_url, 'plant-analysis/local/')) {
            abort_unless(Storage::disk('local')->exists($message->image_url), 404);

            return Storage::disk('local')->response($message->image_url);
        }

        return redirect()->away(app(SupabaseStorageService::class)->signedUrl($message->image_url));
    }

    private function assistantConversation(Request $request, bool $create = false): ?AIConversation
    {
        $conversation = $request->filled('conversation_id')
            ? $request->user()->conversations()->where('status', self::ConversationStatus)->findOrFail($request->integer('conversation_id'))
            : $request->user()->conversations()->where('status', self::ConversationStatus)->latest('updated_at')->first();

        if ($conversation || ! $create) {
            return $conversation;
        }

        return $request->user()->conversations()->create([
            'title' => 'New Chat',
            'status' => self::ConversationStatus,
        ]);
    }

    private function conversationTitle(string $question, bool $hasImage): string
    {
        if ($hasImage && $question === 'Please identify and analyze this crop image.') {
            return 'Crop Image Analysis';
        }

        $crop = collect(['lettuce', 'cabbage', 'tomato', 'pepper', 'eggplant', 'rice', 'corn'])
            ->first(fn (string $crop): bool => str_contains(mb_strtolower($question), $crop));
        if ($crop && preg_match('/\bdisease|pest|holes?|spots?|damage\b/i', $question) === 1) {
            return ucfirst($crop).' Disease';
        }
        if ($crop && preg_match('/\bwater|watering|irrigat\b/i', $question) === 1) {
            return ucfirst($crop).' Watering';
        }

        $title = preg_replace('/[^\pL\pN\s]/u', '', $question) ?: 'New Chat';

        return mb_strimwidth(mb_convert_case(trim($title), MB_CASE_TITLE), 0, 48, '…');
    }

    private function storeAssistantImage(UploadedFile $image, int $ownerId): string
    {
        if (config('agrisense.supabase_url') && config('agrisense.supabase_key')) {
            return app(SupabaseStorageService::class)->upload($image, 'plant-analysis', $ownerId);
        }

        $path = $image->store('plant-analysis/local/'.$ownerId, 'local');
        if ($path === false) {
            throw new \RuntimeException('The assistant image could not be written to local storage.');
        }

        return $path;
    }

    private function deleteAssistantImage(string $imagePath): void
    {
        if (str_starts_with($imagePath, 'plant-analysis/local/')) {
            Storage::disk('local')->delete($imagePath);

            return;
        }

        app(SupabaseStorageService::class)->delete($imagePath);
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

    private function referencesConversationImage(string $question): bool
    {
        return preg_match('/\b(this plant|this crop|the image|this image|the photo|this photo|the leaves?|this leaf|it|that|them)\b/i', $question) === 1;
    }

    private function isImageManagementQuestion(string $question): bool
    {
        return preg_match('/\b(pest|disease|cause|treat|treatment|manage|management|prevent|control|what should i do|how (?:can|should) i)\b/i', $question) === 1;
    }

    /**
     * @return array{contents: string, mime_type: string}|null
     */
    private function conversationImage(AIConversation $conversation): ?array
    {
        $message = $conversation->messages()
            ->where('role', 'user')
            ->whereNotNull('image_url')
            ->latest('id')
            ->first();
        if (! $message || ! str_starts_with($message->image_url, 'plant-analysis/')) {
            return null;
        }

        try {
            if (str_starts_with($message->image_url, 'plant-analysis/local/')) {
                if (! Storage::disk('local')->exists($message->image_url)) {
                    return null;
                }
                $contents = Storage::disk('local')->get($message->image_url);
                $mimeType = Storage::disk('local')->mimeType($message->image_url) ?: 'application/octet-stream';
            } else {
                $contents = app(SupabaseStorageService::class)->download($message->image_url);
                $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
            }

            return str_starts_with($mimeType, 'image/') ? ['contents' => $contents, 'mime_type' => $mimeType] : null;
        } catch (\Throwable $exception) {
            Log::warning('AI assistant conversation image could not be loaded', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'exception' => $exception,
            ]);

            return null;
        }
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
