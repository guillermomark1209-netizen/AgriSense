@extends('layouts.app')
@section('title', __('navigation.ai_assistant'))
@section('subtitle', 'Ask practical questions about your crops and latest field conditions.')
@section('content')
<section class="assistant-chat agri-card" data-ai-chat data-endpoint="{{ route('ai.assistant.chat') }}" @if($crop) data-crop-id="{{ $crop->id }}" @endif>
<div class="assistant-chat-header"><div><span class="section-kicker">AGRISENSE AI</span><h2>Your field companion</h2>@if($crop)<p class="muted text-sm mt-1">Using the record and latest reading for <strong>{{ $crop->name }}</strong>@if($crop->variety) · {{ $crop->variety }}@endif.</p>@if($crop->image_url && str_starts_with($crop->image_url, 'crop-images/'))<img class="upload-preview mt-3" src="{{ route('crops.image', $crop) }}" alt="{{ $crop->name }} reference photo sent to the AI">@endif @endif</div><button class="agri-btn agri-btn-secondary" type="button" data-ai-clear><i data-lucide="trash-2"></i>Clear Chat</button></div>
<div class="assistant-chat-messages" data-ai-messages aria-live="polite" aria-label="AI Assistant conversation"></div>
<form class="assistant-chat-form" data-ai-form><label class="sr-only" for="assistant-message">Ask the AI Assistant</label><textarea id="assistant-message" name="message" rows="2" maxlength="2000" required placeholder="Ask about watering, Chinese cabbage, or your sensor readings…" data-ai-input></textarea><button class="agri-btn agri-btn-primary" type="submit" data-ai-send><i data-lucide="send"></i><span>Send</span></button></form>
<p class="muted text-sm" data-ai-status role="status"></p>
</section>
@endsection
