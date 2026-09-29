<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $table = 'document_chunks';

    protected $fillable = [
        'embedding_model',
        'document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    protected $casts = ['embedding' => 'array'];
}
