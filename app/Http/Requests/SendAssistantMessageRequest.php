<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'required_without:message'],
            'conversation_id' => ['nullable', Rule::exists('ai_conversations', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->where('status', 'assistant_chat'))],
            'crop_id' => ['nullable', Rule::exists('crops', 'id')->when(! $this->user()->isAdmin(), fn ($rule) => $rule->where('user_id', $this->user()->id))],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
