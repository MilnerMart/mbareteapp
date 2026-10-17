<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserDeactivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string)$this->input('reason'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.User::minDeactivationReasonLength, 'max:'.User::maxDeactivationReasonLength],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Indica el motivo de la baja',
            'reason.min' => 'El motivo de la baja tiene que tener al menos '.User::minDeactivationReasonLength.' caracteres',
        ];
    }
}
