<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CropRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crop = $this->route('crop');

        return $this->user() !== null && (! $crop || $this->user()->can('update', $crop));
    }

    public function rules(): array
    {
        if (! $this->route('crop')) {
            return [
                'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ];
        }

        return [
            'user_id' => [$this->route('crop') || ! $this->user()->isAdmin() ? 'prohibited' : 'sometimes', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'scientific_name' => ['nullable', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'growth_stage' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'planting_date' => ['nullable', 'date'],
            'expected_harvest_date' => ['nullable', 'date', 'after_or_equal:planting_date'],
            'status' => ['required', Rule::in(['healthy', 'warning', 'critical', 'unknown'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = min(5, UploadedFile::getMaxFilesize() / 1024 / 1024);
        $image = $this->file('image');
        $uploadError = $image instanceof UploadedFile ? $image->getError() : UPLOAD_ERR_OK;

        return [
            'image.uploaded' => match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The photo exceeds the upload limit. Choose a file of '.$maxMegabytes.' MB or less.',
                UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Please select the image and try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not receive your photo. Please try again later.',
                default => 'The photo could not be uploaded. Please select the image and try again.',
            },
        ];
    }
}
