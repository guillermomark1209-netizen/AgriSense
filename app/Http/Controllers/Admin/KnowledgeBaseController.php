<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function index(): View
    {
        return view('admin.sources.index', ['sources' => KnowledgeBase::query()->latest()->paginate(15)]);
    }

    public function store(Request $request, GeminiService $gemini): RedirectResponse
    {
        try {
            $data = $this->validated($request);
            $embedding = $gemini->embed($data['content'], 'RETRIEVAL_DOCUMENT');
            DB::transaction(function () use ($data, $embedding, $gemini): void {
                $knowledge = KnowledgeBase::create($data);
                $gemini->storeKnowledgeEmbedding($knowledge, $embedding);
            });
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['content' => 'The source could not be saved with an embedding. Confirm the Gemini integration is configured and try again.']);
        }

        return back()->with('success', 'Knowledge-base source added and embedded.');
    }

    public function update(Request $request, KnowledgeBase $source, GeminiService $gemini): RedirectResponse
    {
        try {
            $data = $this->validated($request);
            $embedding = $gemini->embed($data['content'], 'RETRIEVAL_DOCUMENT');
            DB::transaction(function () use ($source, $data, $embedding, $gemini): void {
                $source->update($data);
                $gemini->storeKnowledgeEmbedding($source, $embedding);
            });
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['content' => 'The source was not updated with an embedding. Confirm the Gemini integration is configured and try again.']);
        }

        return back()->with('success', 'Knowledge-base source updated.');
    }

    public function regenerate(KnowledgeBase $source, GeminiService $gemini): RedirectResponse
    {
        try {
            $gemini->storeKnowledgeEmbedding($source);
        } catch (\Throwable) {
            return back()->withErrors(['content' => 'Embedding generation failed. Confirm the Gemini integration is configured and try again.']);
        }

        return back()->with('success', 'Embedding regenerated.');
    }

    public function destroy(KnowledgeBase $source): RedirectResponse
    {
        $source->delete();

        return back()->with('success', 'Knowledge-base source deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string', 'max:20000'], 'source_url' => ['required', 'url:http,https', 'max:255']]);
        $data['source'] = $data['source_url'];

        return $data;
    }
}
