<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'question' => ['nullable', 'string', 'max:2000'],
            'crop_id' => ['nullable', Rule::exists('crops', 'id')->when(! $this->user()->isAdmin(), fn ($rule) => $rule->where('user_id', $this->user()->id))],
            'conversation_id' => ['nullable', Rule::exists('ai_conversations', 'id')->where('user_id', $this->user()->id)],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
