<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIMessageSource extends Model
{
    protected $table = 'ai_message_sources';

    protected $fillable = [
        'message_id',
        'source_id',
        'document_id',
        'chunk_id',
        'citation_text',
        'citation_url',
    ];

    public function message()
    {
        return $this->belongsTo(AIMessage::class, 'message_id');
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function chunk()
    {
        return $this->belongsTo(DocumentChunk::class, 'chunk_id');
    }
}
