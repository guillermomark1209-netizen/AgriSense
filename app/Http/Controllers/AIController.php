<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderUnavailableException;
use App\Http\Requests\AskQuestionRequest;
use App\Models\AIAuditLog;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\Crop;
use App\Services\GeminiService;
use App\Services\RagService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AIController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = $request->user()->conversations()->latest('updated_at')->paginate(12);
        $selected = $request->filled('conversation') ? $request->user()->conversations()->with('messages.sources.source')->findOrFail($request->integer('conversation')) : null;
        $crops = Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->get();

        return view('ai.index', compact('conversations', 'selected', 'crops'));
    }

    public function store(AskQuestionRequest $request, RagService $rag, GeminiService $gemini): RedirectResponse
    {
        $crop = $request->filled('crop_id') ? Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('crop_id')) : null;
        $conversation = $request->filled('conversation_id') ? $request->user()->conversations()->findOrFail($request->integer('conversation_id')) : null;
        if ($conversation && $conversation->crop_id != $crop?->id) {
            return back()->withErrors(['crop_id' => 'Start a new conversation to change crops.']);
        }
        $imagePath = null;
        try {
            $image = $request->file('image');
            $imageAnalysis = $gemini->analyzeCropImage($image);
            $cropName = $crop?->name ?? $imageAnalysis['crop_name'];
            $question = $request->string('question')->toString();
            $retrievalQuery = implode(' ', array_filter([
                $cropName,
                $question,
                ...$imageAnalysis['visible_symptoms'],
                ...$imageAnalysis['possible_problems'],
                'pest disease symptoms visible damage causes treatment control prevention',
            ]));
            $chunks = $rag->retrieve($retrievalQuery, $cropName, null, [
                $cropName,
                $question,
                ...$imageAnalysis['visible_symptoms'],
                ...$imageAnalysis['possible_problems'],
                'pest disease symptoms visible damage causes treatment control prevention',
            ])->filter(fn ($chunk) => filled($chunk->content) && is_string($chunk->source_url) && in_array(parse_url($chunk->source_url, PHP_URL_SCHEME), ['http', 'https'], true))->values();
            if ($chunks->isEmpty()) {
                $answer = ['answer' => 'I could not find enough verified information in the knowledge base.'];
            } else {
                $evidence = $chunks->map(fn ($c) => ['chunk_id' => $c->id, 'text' => $c->content, 'title' => $c->title, 'source_url' => $c->source_url])->all();
                $question = "{$question}\n\nImage assessment for {$cropName}: visible symptoms: {$imageAnalysis['visible_evidence']}; possible visible problems: {$imageAnalysis['possible_problem']}. Compare these observations with the retrieved evidence. Use exactly these sections: Possible problem:, Possible pests:, Visible symptoms:, Explanation:, Suggested actions:, Prevention:. If more than one pest matches, list them as possible pests and do not claim certainty.";
                $answer = $gemini->generateResponse($question, $evidence, [], $image);
            }
            $imagePath = app(SupabaseStorageService::class)->upload($image, 'plant-analysis', $request->user()->id);
            $conversation = DB::transaction(function () use ($request, $crop, $conversation, $answer, $chunks, $imagePath) {
                $conversation ??= AIConversation::create(['user_id' => $request->user()->id, 'crop_id' => $crop?->id, 'title' => 'Crop image analysis', 'status' => 'active']);
                $conversation->messages()->create(['role' => 'user', 'content' => 'Crop image analysis', 'image_url' => $imagePath]);
                $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $answer['answer'], 'confidence' => null]);
                foreach ($chunks as $chunk) {
                    abort_unless($chunk->is_active && filled($chunk->embedding), 409, 'A source changed during generation. Please retry.');
                    if (filled($chunk->title) && is_string($chunk->source_url) && in_array(parse_url($chunk->source_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                        $message->sources()->create(['citation_text' => $chunk->title, 'citation_url' => $chunk->source_url]);
                    }
                }
                $conversation->touch();
                AIAuditLog::create(['user_id' => $request->user()->id, 'event_type' => 'answer_generated', 'details' => ['conversation_id' => $conversation->id, 'chunk_ids' => $chunks->pluck('id')->all(), 'model' => config('services.gemini.model')], 'ip_address' => $request->ip()]);

                return $conversation;
            });
        } catch (AiProviderUnavailableException $e) {
            AIAuditLog::create(['user_id' => $request->user()->id, 'event_type' => 'answer_failed', 'details' => ['exception' => get_class($e)], 'ip_address' => $request->ip()]);

            return back()->withInput($request->except('image'))->withErrors(['question' => 'AI Assistant is currently unavailable. Please try again later.']);
        } catch (\Throwable $e) {
            AIAuditLog::create(['user_id' => $request->user()->id, 'event_type' => 'answer_failed', 'details' => ['exception' => get_class($e)], 'ip_address' => $request->ip()]);

            return back()->withInput($request->except('image'))->withErrors(['question' => 'The evidence service could not complete this request. Check integration settings or try again. No unverified answer was saved.']);
        }

        return redirect()->route('ai.index', ['conversation' => $conversation->id]);
    }

    public function image(Request $request, AIMessage $message): RedirectResponse
    {
        abort_unless($message->conversation->user_id === $request->user()->id && $message->image_url, 403);

        return redirect()->away(app(SupabaseStorageService::class)->signedUrl($message->image_url));
    }
}
