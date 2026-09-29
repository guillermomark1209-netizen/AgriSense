<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => [$this->route('device') ? 'prohibited' : 'sometimes', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'device_id' => [$this->route('device') ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('devices')->ignore($this->route('device'))],
            'crop_id' => ['nullable', Rule::exists('crops', 'id')->where('user_id', $this->route('device')?->user_id ?? $this->input('user_id', $this->user()->id))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
