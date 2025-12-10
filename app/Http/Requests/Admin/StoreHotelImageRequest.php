<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Optional parallel arrays for per-image metadata
            'alt_text' => ['sometimes', 'array'],
            // alt_text.*, * is for the alt text of the image
            'alt_text.*' => ['nullable', 'string', 'max:500'],

            'display_order' => ['sometimes', 'array'],
            // display_order.*, * is for the display order of the image
            'display_order.*' => ['nullable', 'integer', 'min:1'],

            'is_active' => ['sometimes', 'array'],
            'is_active.*' => ['nullable', 'boolean'],
        ];
    }
}


