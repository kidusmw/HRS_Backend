<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'alt_text' => ['sometimes', 'array'],
            'alt_text.*' => ['nullable', 'string', 'max:500'],

            'display_order' => ['sometimes', 'array'],
            'display_order.*' => ['nullable', 'integer', 'min:1'],

            'is_active' => ['sometimes', 'array'],
            'is_active.*' => ['nullable', 'boolean'],
        ];
    }
}


