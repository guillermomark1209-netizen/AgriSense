<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = ['title', 'crop', 'category', 'content', 'source', 'source_url', 'is_active', 'embedding_model'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeWithEmbedding(Builder $query): void
    {
        $query->whereNotNull('embedding');
    }

    public function setEmbeddingVector(array $embedding): void
    {
        DB::update('UPDATE knowledge_base SET embedding = ?::vector, embedding_model = ? WHERE id = ?', [json_encode($embedding, JSON_THROW_ON_ERROR), config('services.gemini.embedding_model'), $this->id]);
    }
}
