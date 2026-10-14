<?php

namespace App\Http\Requests;

use App\Models\ProgressEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgressEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los limites de cada valor los valida ProgressEntry segun la metrica.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_on' => ['required', 'date_format:Y-m-d'],
            'metric' => ['required', 'string', Rule::in(array_keys(ProgressEntry::metricMap))],
            'exercise_id' => ['nullable', 'integer'],
            'value' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
