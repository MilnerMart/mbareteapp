<?php

namespace App\Http\Requests;

use App\SystemRules\SystemRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemRuleRequest extends FormRequest
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
            'rules' => ['required', 'array'],
            'rules.*.is_enabled' => ['required', 'boolean'],
            'rules.*.frequency' => ['required', Rule::in(SystemRule::frequencyList)],
            'rules.*.run_at' => ['required', 'date_format:H:i'],
            'rules.*.retry_minutes' => ['required', 'integer', 'min:0', 'max:'.SystemRule::maxRetryMinutes],
        ];
    }
}
