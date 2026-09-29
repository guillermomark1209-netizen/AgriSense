<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessDocument implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public int $documentId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('document:'.$this->documentId))->dontRelease()->expireAfter(1300)];
    }

    public function handle(DocumentService $service): void
    {
        $document = Document::find($this->documentId);
        if ($document) {
            $service->process($document);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Document::whereKey($this->documentId)->update(['status' => 'failed']);
    }
}
