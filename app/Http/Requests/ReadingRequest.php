<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'reading_id' => ['required', 'uuid'],
            'reading_at' => ['required', 'date', 'before_or_equal:now', 'after:'.now()->subDays(30)->toIso8601String()],
            'temperature' => ['nullable', 'numeric', 'between:-100,150'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
            'soil_moisture' => ['nullable', 'numeric', 'between:0,100'],
            'soil_ph' => ['nullable', 'numeric', 'between:0,14'],
            'light_intensity' => ['nullable', 'numeric', 'between:0,999999'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (collect(array_keys(config('agrisense.sensors')))->every(fn ($key) => $this->input($key) === null)) {
                $validator->errors()->add('readings', 'At least one sensor value is required.');
            }
        }];
    }
}
