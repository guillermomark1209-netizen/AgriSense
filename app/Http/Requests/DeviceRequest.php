<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->routeIs('devices.register') || $this->user()->isAdmin());
    }

    public function rules(): array
    {
        $selfRegistration = $this->routeIs('devices.register');

        return [
            'user_id' => [$selfRegistration || $this->route('device') ? 'prohibited' : 'required', 'integer', 'exists:users,id'],
            'auth_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'token_hash' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'device_type' => [$selfRegistration ? 'required' : 'sometimes', Rule::in(['ESP32', 'ESP8266', 'Arduino', 'Other'])],
            'location' => [$selfRegistration ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'device_id' => [$this->route('device') ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('devices')->ignore($this->route('device'))],
            'crop_id' => [$selfRegistration ? 'prohibited' : 'nullable', Rule::exists('crops', 'id')->where('user_id', $this->route('device')?->user_id ?? $this->input('user_id', $this->user()->id))],
            'is_active' => [$selfRegistration ? 'prohibited' : 'sometimes', 'boolean'],
        ];
    }
}
