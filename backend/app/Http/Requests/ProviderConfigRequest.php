<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProviderConfigRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'credentials' => ['nullable', 'array', 'max:20'],
            'credentials.*' => ['nullable', 'string', 'max:4000'],
            'settings' => ['nullable', 'array', 'max:20'],
            'settings.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
