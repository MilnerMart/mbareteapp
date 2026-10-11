<?php

namespace App\Http\Requests;

use App\ServiceApi\ProviderClientRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderRequest extends FormRequest
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
            'type' => [$this->isMethod('PUT') ? 'prohibited' : 'required', 'string', Rule::in(array_keys(ProviderClientRegistry::clientClassMap()))],
            'base_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
