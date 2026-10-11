<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:100',
            'slug' => 'nullable|min:3|max:50|alpha_dash',
            'muscle_id' => 'required|exists:muscles,id',
            'description' => 'required|min:5|max:255',
            'recommended_rest_time' => 'required|integer|min:1|max:600',
            'is_public' => ['sometimes', 'boolean'],
            // al crear se sube una imagen o se elige una del proveedor de recursos publicos; al editar ambas son opcionales
            'image' => [$this->isMethod('post') ? 'required_without:public_resource_key' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'public_resource_key' => ['nullable', 'string', 'max:200', 'prohibits:image'],
            'public_resource_media_keys' => ['nullable', 'array', 'max:10'],
            'public_resource_media_keys.*' => ['string', 'max:200'],
        ];
    }
}
