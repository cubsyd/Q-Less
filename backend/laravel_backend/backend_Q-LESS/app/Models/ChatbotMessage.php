<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotMessage extends Model
{
    protected $fillable = [
        'chatbot_conversation_id',
        'role',
        'text',
        'reply',
    ];

    protected $casts = [
        'reply' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatbotConversation::class, 'chatbot_conversation_id');
    }
}