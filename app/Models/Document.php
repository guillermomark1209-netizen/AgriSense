<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = [
        'source_id',
        'title',
        'file_path',
        'content',
        'status',
        'uploaded_by',
    ];

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
