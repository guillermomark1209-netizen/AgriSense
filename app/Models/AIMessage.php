<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIMessage extends Model
{
    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'image_url',
        'confidence',
    ];

    public function conversation()
    {
        return $this->belongsTo(AIConversation::class, 'conversation_id');
    }

    public function sources()
    {
        return $this->hasMany(AIMessageSource::class, 'message_id');
    }
}
