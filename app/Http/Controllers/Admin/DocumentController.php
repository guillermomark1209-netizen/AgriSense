<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Source;
use App\Services\DocumentService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Smalot\PdfParser\Parser;

class DocumentController extends Controller
{
    public function index(): View
    {
        return view('admin.documents.index', ['documents' => Document::with('source')->latest()->paginate(15), 'sources' => Source::orderBy('title')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'source_id' => ['required', 'exists:sources,id'], 'file' => ['required', 'file', 'mimes:pdf,txt', 'max:5120']]);
        try {
            $file = $request->file('file');
            $content = $file->getMimeType() === 'application/pdf' ? (new Parser)->parseFile($file->getRealPath())->getText() : $file->getContent();
            if (mb_strlen(trim($content)) < 40 || mb_strlen($content) > 250000) {
                return back()->withErrors(['file' => 'Upload searchable text between 40 and 250,000 characters. Scanned PDFs require OCR before upload.']);
            }
            $path = app(SupabaseStorageService::class)->upload($file, 'knowledge-documents', $request->user()->id);
            Document::create(['source_id' => $data['source_id'], 'title' => $data['title'], 'content' => $content, 'file_path' => $path, 'uploaded_by' => $request->user()->id, 'status' => 'uploaded']);
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'The document could not be extracted or stored. Check the file and Supabase configuration.']);
        }

        return back()->with('success', 'Document uploaded and text extracted. Process embeddings when ready.');
    }

    public function show(Document $document): View
    {
        return view('admin.documents.show', ['document' => $document->load('source', 'chunks')]);
    }

    public function process(Document $document, DocumentService $service): RedirectResponse
    {
        if (in_array($document->status, ['queued', 'processing'])) {
            return back()->withErrors(['document' => 'This document is already queued or processing.']);
        }
        $document->update(['status' => 'queued']);
        ProcessDocument::dispatch($document->id);

        return back()->with('success', 'Document queued for embedding processing. Refresh this page to check its status.');
    }

    public function download(Document $document): RedirectResponse
    {
        abort_unless($document->file_path, 404);

        return redirect()->away(app(SupabaseStorageService::class)->signedUrl($document->file_path));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        return redirect()->route('admin.documents.index')->with('success', 'Document removed from retrieval.');
    }
}
