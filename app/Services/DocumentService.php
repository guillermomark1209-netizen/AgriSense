<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentService
{
    public function process(Document $document): void
    {
        $document->update(['status' => 'processing']);
        try {
            $text = trim($document->content ?? '');
            if (mb_strlen($text) < 40) {
                throw new RuntimeException('The document has insufficient extracted text.');
            }
            $chunks = [];
            for ($offset = 0; $offset < mb_strlen($text); $offset += 1200) {
                $content = mb_substr($text, $offset, 1500);
                $chunks[] = ['content' => $content, 'embedding' => app(GeminiService::class)->embed($content, 'RETRIEVAL_DOCUMENT')];
            }
            DB::transaction(function () use ($document, $chunks) {
                $document->chunks()->delete();
                foreach ($chunks as $i => $data) {
                    $chunk = $document->chunks()->create([...$data, 'chunk_index' => $i, 'embedding_model' => config('services.gemini.embedding_model')]);
                    if (DB::getDriverName() === 'pgsql') {
                        DB::update('UPDATE document_chunks SET embedding_vector = ?::vector WHERE id = ?', [json_encode($data['embedding'], JSON_THROW_ON_ERROR), $chunk->id]);
                    }
                }
                $document->update(['status' => 'processed']);
            });
        } catch (\Throwable $e) {
            $document->update(['status' => 'failed']);
            throw $e;
        }
    }
}
