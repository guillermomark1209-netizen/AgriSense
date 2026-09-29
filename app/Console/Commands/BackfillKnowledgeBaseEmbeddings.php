<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Services\GeminiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-knowledge-base-embeddings')]
#[Description('Regenerate incompatible knowledge-base embeddings with Gemini.')]
class BackfillKnowledgeBaseEmbeddings extends Command
{
    public function handle(): int
    {
        $count = 0;

        KnowledgeBase::query()->where(function ($query): void {
            $query->whereNull('embedding')
                ->orWhereNull('embedding_model')
                ->orWhere('embedding_model', '!=', config('services.gemini.embedding_model'));
        })->orderBy('id')->each(function (KnowledgeBase $knowledge) use (&$count): void {
            app(GeminiService::class)->storeKnowledgeEmbedding($knowledge);
            $count++;
        });

        $this->info("Regenerated {$count} knowledge-base embeddings with Gemini.");

        return self::SUCCESS;
    }
}
