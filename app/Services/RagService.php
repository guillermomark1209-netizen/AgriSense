<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RagService
{
    public function retrieve(string $question, ?string $crop = null, ?string $topic = null, array $priorities = []): Collection
    {
        if (! Schema::hasTable('knowledge_base')) {
            return collect();
        }

        $base = KnowledgeBase::query()->where('is_active', true)->withEmbedding()
            ->where('embedding_model', config('services.gemini.embedding_model'))
            ->when(filled($crop) && Schema::hasColumn('knowledge_base', 'crop'), fn ($query) => $query->where(fn ($query) => $query->whereRaw('LOWER(crop) = ?', [mb_strtolower($crop)])->orWhere('crop', '*')))
            ->when(filled($topic), fn ($query) => $query->where('category', $topic));
        if (! (clone $base)->exists()) {
            return collect();
        }
        $vector = app(GeminiService::class)->embed($question, 'RETRIEVAL_QUERY');
        if (DB::getDriverName() === 'pgsql') {
            $literal = json_encode($vector, JSON_THROW_ON_ERROR);

            $results = $base->whereRaw('(embedding <=> ?::vector) < 0.45', [$literal])
                ->orderByRaw('embedding <=> ?::vector', [$literal])->limit(15)->get();

            return $this->prioritize($results, $priorities);
        }

        // SQLite is used only by isolated tests, never as the production vector store.
        return $base->get()->map(function ($knowledge) use ($vector) {
            $embedding = $knowledge->embedding ?? [];
            $dot = $a = $b = 0;
            foreach ($vector as $i => $v) {
                $other = $embedding[$i] ?? 0;
                $dot += $v * $other;
                $a += $v * $v;
                $b += $other * $other;
            }
            $knowledge->similarity = $a && $b ? $dot / sqrt($a * $b) : 0;

            return $knowledge;
        })->where('similarity', '>', 0.55)->sortByDesc('similarity')->values();

        return $this->prioritize($results, $priorities);
    }

    private function prioritize(Collection $records, array $priorities): Collection
    {
        $terms = collect($priorities)
            ->filter(fn ($term): bool => is_string($term) && filled($term))
            ->flatMap(fn (string $term) => preg_split('/[^\\pL\\pN]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $term): bool => mb_strlen($term) > 2)
            ->unique()
            ->values();

        return $records->map(function (KnowledgeBase $record) use ($terms): KnowledgeBase {
            $searchable = mb_strtolower(implode(' ', [$record->crop, $record->category, $record->title, $record->content, $record->source_url]));
            $record->priority_score = $terms->sum(fn (string $term): int => substr_count($searchable, $term));
            $record->authority_score = $this->authorityScore($record->source_url);

            return $record;
        })->sortByDesc(fn (KnowledgeBase $record): array => [$record->priority_score, $record->authority_score, $record->similarity ?? 0])
            ->take(5)
            ->values();
    }

    private function authorityScore(mixed $sourceUrl): int
    {
        if (! is_string($sourceUrl)) {
            return 0;
        }

        $host = mb_strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        if (str_ends_with($host, '.gov') || str_ends_with($host, '.edu') || str_contains($host, 'fao.org') || str_contains($host, 'cgiar.org') || str_contains($host, 'irri.org')) {
            return 2;
        }
        if (str_contains($host, 'ucanr.edu') || str_contains($host, 'ipm.ucanr.edu')) {
            return 2;
        }

        return 1;
    }
}
